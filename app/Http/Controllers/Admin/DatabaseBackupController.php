<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Services\System\DatabaseBackupService;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Backup and restore for the whole application database.
 *
 * Restore is the destructive half and is gated four ways: the database_backup
 * permission, the admin's own password, a typed confirmation token, and an
 * automatic pre-restore dump. The dump work itself lives in
 * DatabaseBackupService — this controller only guards the door.
 */
class DatabaseBackupController extends Controller
{
    /** Typed by the admin to arm a restore. Not translated: it is a token, not copy. */
    private const RESTORE_TOKEN = 'RESTORE';

    public function __construct(private readonly DatabaseBackupService $service)
    {
    }

    /* ------------------------------------------------------------------
       Backup
       ------------------------------------------------------------------ */

    public function backupIndex()
    {
        $backups = $this->service->backups();
        $stats = $this->service->stats($backups);

        return view('admin-views.business-settings.db-backup', compact('backups', 'stats'));
    }

    public function store(Request $request)
    {
        if ($blocked = $this->blockedInDemo()) {
            return $blocked;
        }

        $request->validate([
            'compress' => 'nullable|in:1,0',
            'scope' => 'nullable|in:full,structure',
            'note' => 'nullable|string|max:190',
        ]);

        $this->raiseLimits();

        try {
            $manifest = $this->service->withLock(fn () => $this->service->create([
                'compress' => $request->boolean('compress'),
                'structure_only' => $request->input('scope') === 'structure',
                'note' => $request->input('note'),
            ]));
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());

            return back();
        }

        Toastr::success(translate('Backup created') . ': ' . $manifest['file'] . ' (' . $this->service->humanBytes($manifest['size']) . ')');

        return back();
    }

    public function download(string $file): Response
    {
        if ($blocked = $this->blockedInDemo()) {
            return $blocked;
        }

        try {
            $path = $this->service->path($file);
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());

            return back();
        }

        // A dump is the whole platform in one file, so the download is logged
        // with the same weight as a restore.
        $this->service->audit('backup.downloaded', ['file' => $file]);

        return response()->download($path, $file, [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(string $file)
    {
        if ($blocked = $this->blockedInDemo()) {
            return $blocked;
        }

        try {
            $this->service->delete($file);
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());

            return back();
        }

        Toastr::success(translate('Backup deleted.'));

        return back();
    }

    /* ------------------------------------------------------------------
       Restore
       ------------------------------------------------------------------ */

    public function restoreIndex()
    {
        $backups = $this->service->backups();
        $stats = $this->service->stats($backups);
        $selected = request('file');

        return view('admin-views.business-settings.db-restore', compact('backups', 'stats', 'selected'));
    }

    public function upload(Request $request)
    {
        if ($blocked = $this->blockedInDemo()) {
            return $blocked;
        }

        $request->validate([
            'backup_file' => 'required|file|max:'.$this->maxUploadKilobytes(),
        ], [
            'backup_file.max' => translate('The backup file is larger than this server accepts.') . ' ' . translate('Maximum size') . ': ' . $this->service->humanBytes($this->maxUploadKilobytes() * 1024),
        ]);

        $file = $request->file('backup_file');

        if (! in_array(strtolower($file->getClientOriginalExtension()), ['sql', 'gz'], true)) {
            Toastr::error(translate('Only .sql and .sql.gz backup files can be uploaded.'));

            return back();
        }

        try {
            $manifest = $this->service->import($file);
        } catch (\Throwable $e) {
            Toastr::error($e->getMessage());

            return back();
        }

        Toastr::success(translate('Backup uploaded') . ': ' . $manifest['file'] . ' (' . $this->service->humanBytes($manifest['size']) . ')');

        return redirect()->route('admin.business-settings.database.restore', ['file' => $manifest['file']]);
    }

    public function restore(Request $request)
    {
        if ($blocked = $this->blockedInDemo()) {
            return $blocked;
        }

        $request->validate([
            'file' => 'required|string',
            'confirmation' => 'required|string',
            'password' => 'required|string',
        ]);

        if (! $this->service->isValidName($request->input('file'))) {
            Toastr::error(translate('The backup file name is not valid.'));

            return back();
        }

        if (trim($request->input('confirmation')) !== self::RESTORE_TOKEN) {
            throw ValidationException::withMessages([
                'confirmation' => translate('Type this exactly to confirm') . ': ' . self::RESTORE_TOKEN,
            ]);
        }

        $admin = auth('admin')->user();

        if (! $admin || ! Hash::check($request->input('password'), $admin->password)) {
            $this->service->audit('restore.rejected', ['file' => $request->input('file'), 'reason' => 'password']);

            throw ValidationException::withMessages([
                'password' => translate('That password is not correct.'),
            ]);
        }

        $this->raiseLimits();

        try {
            $result = $this->service->withLock(fn () => $this->service->restore($request->input('file'), [
                'safety_backup' => $request->boolean('safety_backup'),
                'wipe' => $request->boolean('wipe'),
            ]));
        } catch (\Throwable $e) {
            Toastr::error(translate('Restore failed — the database was left as it was found where possible.').' '.$e->getMessage());

            return back()->withInput($request->except(['password', 'confirmation']));
        }

        $this->flushCaches();

        Toastr::success(translate('Database restored') . ': ' . $result['file'] . ' — ' . translate('Statements') . ': ' . number_format($result['statements']) . ', ' . translate('Duration') . ': ' . \Carbon\CarbonInterval::seconds((int) round($result['duration']))->cascade()->forHumans());

        if (! $this->currentAdminSurvived($admin->id)) {
            auth('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            Toastr::warning(translate('The restored backup does not contain your admin account. Sign in with an account from that backup.'));

            return redirect()->route('login', [Helpers::get_login_url(
                ($admin->role_id ?? 0) == 1 ? 'admin_login_url' : 'admin_employee_login_url'
            )]);
        }

        return redirect()->route('admin.business-settings.database.backup');
    }

    /* ------------------------------------------------------------------
       Guards
       ------------------------------------------------------------------ */

    private function blockedInDemo()
    {
        if (getEnvMode() !== 'demo') {
            return null;
        }

        Toastr::info(translate('messages.Update option is disable for demo'));

        return back();
    }

    /**
     * A dump of a live marketplace routinely runs past the default 30 seconds,
     * and a request abandoned halfway leaves a truncated file in the vault.
     */
    private function raiseLimits(): void
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        // Only ever raise it. A server already configured above this would be
        // cut down to 512M by an unconditional ini_set, which is the opposite
        // of what this method is for.
        $current = trim((string) ini_get('memory_limit'));

        if ($current !== '-1' && $this->toBytes($current) < 512 * 1024 * 1024) {
            @ini_set('memory_limit', '512M');
        }
    }

    private function maxUploadKilobytes(): int
    {
        $limits = array_filter([
            (int) ($this->toBytes((string) ini_get('upload_max_filesize')) / 1024),
            (int) ($this->toBytes((string) ini_get('post_max_size')) / 1024),
        ]);

        return $limits ? min($limits) : 8192;
    }

    /** Reads a php.ini shorthand size ("8M", "1G", "512K") as bytes. */
    private function toBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function currentAdminSurvived(?int $id): bool
    {
        if (! $id) {
            return false;
        }

        try {
            return DB::table('admins')->where('id', $id)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function flushCaches(): void
    {
        try {
            Cache::flush();
        } catch (\Throwable) {
            // The cache store lives in the database that was just replaced; a
            // failure here is not worth losing the success message over.
        }
    }
}
