<?php

namespace App\Services\System;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;

/**
 * Creates, stores and restores full MySQL dumps of the application database.
 *
 * The vault lives under storage/app/backups/database, which is outside the
 * document root — a dump is the whole platform in one file, so it must never be
 * reachable by URL. Downloads are streamed back through the controller instead,
 * behind the admin guard and the database_backup permission.
 *
 * Every dump is written next to a .json manifest holding the row/table counts,
 * the author and a sha256 of the file itself. The checksum is what lets a
 * restore refuse a dump that was truncated in transit — a half-written dump
 * restores as a half-empty database, and by then the original is gone.
 */
class DatabaseBackupService
{
    /** Dumps are written in this many rows per INSERT. */
    private const INSERT_CHUNK = 200;

    /** Read size for the restore scanner. */
    private const READ_CHUNK = 262144;

    /** A dump smaller than this cannot hold a schema; treat it as corrupt. */
    private const MIN_DUMP_BYTES = 32;

    /**
     * Tables whose rows are dumped as structure only. Nothing here is worth
     * carrying across a restore, and jobs/sessions restored out of their moment
     * would fire against a database that has since moved on.
     */
    private const TRANSIENT_TABLES = [
        'failed_jobs', 'jobs', 'job_batches', 'sessions', 'cache', 'cache_locks',
        'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring',
    ];

    public function __construct(private readonly ?PDO $connection = null)
    {
    }

    /* ------------------------------------------------------------------
       Vault
       ------------------------------------------------------------------ */

    public function vaultPath(string $file = ''): string
    {
        $base = storage_path('app/backups/database');

        return $file === '' ? $base : $base.DIRECTORY_SEPARATOR.$file;
    }

    public function ensureVault(): void
    {
        $base = $this->vaultPath();

        if (! is_dir($base)) {
            mkdir($base, 0755, true);
        }

        // Belt and braces: the vault is already outside the document root, but a
        // deployment that symlinks storage/ into public would otherwise expose it.
        $guards = [
            '.gitignore' => "*\n!.gitignore\n!index.html\n!.htaccess\n",
            'index.html' => '',
            '.htaccess' => "Deny from all\n",
        ];

        foreach ($guards as $name => $body) {
            $path = $base.DIRECTORY_SEPARATOR.$name;
            if (! file_exists($path)) {
                file_put_contents($path, $body);
            }
        }
    }

    /**
     * A dump name is used to build a filesystem path, so it is whitelisted
     * rather than sanitised — anything that is not exactly the shape this
     * service writes is refused outright.
     */
    public function isValidName(string $name): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,120}\.sql(\.gz)?$/', $name)
            && ! str_contains($name, '..');
    }

    public function path(string $name): string
    {
        if (! $this->isValidName($name)) {
            throw new RuntimeException(translate('The backup file name is not valid.'));
        }

        $path = $this->vaultPath($name);

        if (! is_file($path)) {
            throw new RuntimeException(translate('That backup is no longer in the vault.'));
        }

        return $path;
    }

    /* ------------------------------------------------------------------
       Listing
       ------------------------------------------------------------------ */

    /** @return array<int, array<string, mixed>> newest first */
    public function backups(): array
    {
        $this->ensureVault();

        $list = [];

        foreach ((array) glob($this->vaultPath('*.sql*')) as $path) {
            $name = basename($path);

            if (! is_file($path) || ! $this->isValidName($name)) {
                continue;
            }

            $manifest = $this->manifest($name);
            $size = (int) filesize($path);

            $list[] = [
                'name' => $name,
                'size' => $size,
                'size_human' => $this->humanBytes($size),
                'compressed' => str_ends_with($name, '.gz'),
                'created_at' => Carbon::createFromTimestamp($manifest['created_at'] ?? filemtime($path)),
                'type' => $manifest['type'] ?? 'manual',
                'driver' => $manifest['driver'] ?? null,
                'tables' => $manifest['tables'] ?? null,
                'rows' => $manifest['rows'] ?? null,
                'structure_only' => (bool) ($manifest['structure_only'] ?? false),
                'database' => $manifest['database'] ?? null,
                'author' => $manifest['author'] ?? null,
                'note' => $manifest['note'] ?? null,
                'checksum' => $manifest['checksum'] ?? null,
                'verified' => isset($manifest['checksum']),
            ];
        }

        usort($list, fn ($a, $b) => $b['created_at']->getTimestamp() <=> $a['created_at']->getTimestamp());

        return $list;
    }

    /** @return array<string, mixed> */
    public function stats(?array $backups = null): array
    {
        $backups ??= $this->backups();

        return [
            'count' => count($backups),
            'bytes' => array_sum(array_column($backups, 'size')),
            'latest' => $backups[0]['created_at'] ?? null,
            'database' => $this->databaseName(),
            'db_bytes' => $this->databaseBytes(),
            'tables' => count($this->tableNames()),
            'free_bytes' => @disk_free_space($this->vaultPath()) ?: null,
        ];
    }

    /** @return array<string, mixed> */
    public function manifest(string $name): array
    {
        $path = $this->vaultPath($this->manifestName($name));

        if (! is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);

        return is_array($data) ? $data : [];
    }

    private function manifestName(string $name): string
    {
        return preg_replace('/\.sql(\.gz)?$/', '', $name).'.json';
    }

    /**
     * Serialises backup and restore against each other. Two dumps racing would
     * interleave nothing worse than disk I/O, but a restore running while a dump
     * is being read produces a backup of a half-restored database — which is the
     * one file the admin would reach for when the restore goes wrong.
     */
    public function withLock(callable $callback): mixed
    {
        $this->ensureVault();

        $handle = fopen($this->vaultPath('.lock'), 'c');

        if (! $handle || ! flock($handle, LOCK_EX | LOCK_NB)) {
            if ($handle) {
                fclose($handle);
            }

            throw new RuntimeException(translate('Another backup or restore is already running. Try again once it finishes.'));
        }

        try {
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /* ------------------------------------------------------------------
       Create
       ------------------------------------------------------------------ */

    /**
     * @param  array{compress?: bool, structure_only?: bool, type?: string, note?: string|null}  $options
     * @return array<string, mixed> the manifest of the dump just written
     */
    public function create(array $options = []): array
    {
        $this->ensureVault();

        $compress = (bool) ($options['compress'] ?? true);
        $structureOnly = (bool) ($options['structure_only'] ?? false);
        $type = $options['type'] ?? 'manual';

        $name = sprintf(
            '%s-%s-%s.sql%s',
            $type === 'manual' ? 'backup' : Str::slug($type),
            Str::limit(Str::slug($this->databaseName()) ?: 'database', 32, ''),
            now()->format('Ymd-His'),
            $compress ? '.gz' : ''
        );

        $target = $this->vaultPath($name);
        $started = microtime(true);

        try {
            $counts = $this->writeDump($target, $compress, $structureOnly);
        } catch (\Throwable $e) {
            @unlink($target);
            throw new RuntimeException(
                translate('The backup could not be written.').' '.$e->getMessage(),
                0,
                $e
            );
        }

        $admin = auth('admin')->user();

        $manifest = [
            'file' => $name,
            'created_at' => now()->getTimestamp(),
            'type' => $type,
            'driver' => 'php',
            'database' => $this->databaseName(),
            'tables' => $counts['tables'],
            'rows' => $counts['rows'],
            'views' => $counts['views'],
            'triggers' => $counts['triggers'],
            'structure_only' => $structureOnly,
            'compressed' => $compress,
            'size' => (int) filesize($target),
            'checksum' => hash_file('sha256', $target),
            'duration' => round(microtime(true) - $started, 2),
            'note' => $options['note'] ?? null,
            'author' => $admin ? trim(($admin->f_name ?? '').' '.($admin->l_name ?? '')).' <'.($admin->email ?? '').'>' : null,
        ];

        file_put_contents(
            $this->vaultPath($this->manifestName($name)),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->audit('backup.created', ['file' => $name, 'type' => $type, 'size' => $manifest['size']]);

        return $manifest;
    }

    /**
     * @return array{tables: int, rows: int, views: int, triggers: int}
     */
    private function writeDump(string $target, bool $compress, bool $structureOnly): array
    {
        $handle = $compress ? gzopen($target, 'wb6') : fopen($target, 'wb');

        if (! $handle) {
            throw new RuntimeException(translate('The backup folder is not writable.'));
        }

        $write = $compress
            ? static fn (string $sql) => gzwrite($handle, $sql)
            : static fn (string $sql) => fwrite($handle, $sql);

        $counts = ['tables' => 0, 'rows' => 0, 'views' => 0, 'triggers' => 0];

        try {
            $write($this->header($structureOnly));

            foreach ($this->tableNames() as $table) {
                $counts['tables']++;
                $write($this->tableStructure($table));

                if ($structureOnly || in_array($table, self::TRANSIENT_TABLES, true)) {
                    continue;
                }

                $counts['rows'] += $this->writeTableRows($write, $table);
            }

            foreach ($this->viewNames() as $view) {
                $counts['views']++;
                $write($this->viewStructure($view));
            }

            $counts['triggers'] = $this->writeTriggers($write);

            $write($this->footer());
        } finally {
            $compress ? gzclose($handle) : fclose($handle);
        }

        return $counts;
    }

    private function header(bool $structureOnly): string
    {
        return implode("\n", [
            '-- 6amMart database backup',
            '-- Database: '.$this->databaseName(),
            '-- Generated: '.now()->toDateTimeString().' ('.config('app.timezone').')',
            '-- Contents: '.($structureOnly ? 'structure only' : 'structure and data'),
            '--',
            '',
            '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;',
            '/*!40101 SET NAMES utf8mb4 */;',
            '/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;',
            "/*!40103 SET TIME_ZONE='+00:00' */;",
            '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;',
            "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;",
            '',
            '',
        ]);
    }

    private function footer(): string
    {
        return implode("\n", [
            '',
            '/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;',
            '/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;',
            '/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;',
            '/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;',
            '',
            '-- Backup complete.',
            '',
        ]);
    }

    private function tableStructure(string $table): string
    {
        // fetchAll rather than fetch: the connection is unbuffered, so a cursor
        // left open on SHOW CREATE would block the next query on it.
        $rows = $this->pdo()->query('SHOW CREATE TABLE '.$this->quoteName($table))->fetchAll(PDO::FETCH_ASSOC);
        $create = $rows[0]['Create Table'] ?? '';

        return "\n--\n-- Table structure for `{$table}`\n--\n\n"
            .'DROP TABLE IF EXISTS '.$this->quoteName($table).";\n"
            .$create.";\n";
    }

    private function viewStructure(string $view): string
    {
        $rows = $this->pdo()->query('SHOW CREATE VIEW '.$this->quoteName($view))->fetchAll(PDO::FETCH_ASSOC);
        $create = $rows[0]['Create View'] ?? '';

        // The definer is dropped: a restore onto another host would fail outright
        // on a user that does not exist there.
        $create = preg_replace('/DEFINER=`[^`]*`@`[^`]*`\s*/', '', (string) $create);

        return "\n--\n-- View `{$view}`\n--\n\n"
            .'DROP VIEW IF EXISTS '.$this->quoteName($view).";\n"
            .$create.";\n";
    }

    private function writeTriggers(callable $write): int
    {
        try {
            $triggers = $this->pdo()->query('SHOW TRIGGERS')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable) {
            return 0;
        }

        $count = 0;

        foreach ($triggers as $trigger) {
            $name = $trigger['Trigger'] ?? null;

            if (! $name) {
                continue;
            }

            $count++;
            $write(
                "\nDROP TRIGGER IF EXISTS ".$this->quoteName($name).";\n"
                ."DELIMITER ;;\n"
                .'CREATE TRIGGER '.$this->quoteName($name).' '.$trigger['Timing'].' '.$trigger['Event']
                .' ON '.$this->quoteName($trigger['Table']).' FOR EACH ROW '.$trigger['Statement'].";;\n"
                ."DELIMITER ;\n"
            );
        }

        return $count;
    }

    private function writeTableRows(callable $write, string $table): int
    {
        $pdo = $this->pdo();
        $quoted = $this->quoteName($table);

        // Unbuffered so a million row table streams instead of being materialised.
        $statement = $pdo->query('SELECT * FROM '.$quoted);
        $written = 0;
        $buffer = [];
        $columns = null;

        $flush = function () use (&$buffer, &$columns, $write, $quoted) {
            if (! $buffer) {
                return;
            }

            $write('INSERT INTO '.$quoted.' ('.implode(', ', $columns).') VALUES '.implode(",\n\t", $buffer).";\n");
            $buffer = [];
        };

        foreach ($statement as $row) {
            if ($columns === null) {
                $columns = array_map(fn ($c) => $this->quoteName($c), array_keys($row));
                $write("\n--\n-- Data for `{$table}`\n--\n\n");
            }

            $buffer[] = '('.implode(', ', array_map(fn ($v) => $this->quoteValue($v), $row)).')';
            $written++;

            if (count($buffer) >= self::INSERT_CHUNK) {
                $flush();
            }
        }

        $flush();
        $statement->closeCursor();

        return $written;
    }

    private function quoteValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $value = (string) $value;

        // Binary columns cannot survive a round trip through a quoted string, so
        // anything that is not valid UTF-8 goes out as a hex literal.
        if ($value !== '' && ! preg_match('//u', $value)) {
            return '0x'.bin2hex($value);
        }

        return $this->pdo()->quote($value);
    }

    private function quoteName(string $name): string
    {
        return '`'.str_replace('`', '``', $name).'`';
    }

    /* ------------------------------------------------------------------
       Import / delete
       ------------------------------------------------------------------ */

    public function import(UploadedFile $file, ?string $note = null): array
    {
        $this->ensureVault();

        $extension = strtolower($file->getClientOriginalExtension());
        $compressed = $extension === 'gz';

        if (! in_array($extension, ['sql', 'gz'], true)) {
            throw new RuntimeException(translate('Only .sql and .sql.gz backup files can be uploaded.'));
        }

        if (! $this->looksLikeDump($file->getRealPath(), $compressed)) {
            throw new RuntimeException(translate('That file does not look like a SQL backup.'));
        }

        $name = sprintf(
            'uploaded-%s-%s.sql%s',
            Str::limit(Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'backup', 40, ''),
            now()->format('Ymd-His'),
            $compressed ? '.gz' : ''
        );

        $file->move($this->vaultPath(), $name);

        $target = $this->vaultPath($name);
        $admin = auth('admin')->user();

        $manifest = [
            'file' => $name,
            'created_at' => now()->getTimestamp(),
            'type' => 'uploaded',
            'driver' => 'upload',
            'database' => null,
            'tables' => null,
            'rows' => null,
            'compressed' => $compressed,
            'size' => (int) filesize($target),
            'checksum' => hash_file('sha256', $target),
            'note' => $note,
            'author' => $admin ? trim(($admin->f_name ?? '').' '.($admin->l_name ?? '')).' <'.($admin->email ?? '').'>' : null,
        ];

        file_put_contents(
            $this->vaultPath($this->manifestName($name)),
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->audit('backup.uploaded', ['file' => $name, 'size' => $manifest['size']]);

        return $manifest;
    }

    public function delete(string $name): void
    {
        $path = $this->path($name);

        @unlink($path);
        @unlink($this->vaultPath($this->manifestName($name)));

        $this->audit('backup.deleted', ['file' => $name]);
    }

    /* ------------------------------------------------------------------
       Restore
       ------------------------------------------------------------------ */

    /**
     * @param  array{safety_backup?: bool, wipe?: bool}  $options
     * @return array<string, mixed>
     */
    public function restore(string $name, array $options = []): array
    {
        $path = $this->path($name);
        $compressed = str_ends_with($name, '.gz');

        if (filesize($path) < self::MIN_DUMP_BYTES) {
            throw new RuntimeException(translate('That backup file is empty.'));
        }

        if (! $this->looksLikeDump($path, $compressed)) {
            throw new RuntimeException(translate('That file does not look like a SQL backup.'));
        }

        $manifest = $this->manifest($name);

        // A dump that was truncated on upload restores as a half-empty database,
        // and the original is gone by the time anyone notices.
        if (! empty($manifest['checksum']) && hash_file('sha256', $path) !== $manifest['checksum']) {
            throw new RuntimeException(translate('This backup failed its integrity check and was not restored.'));
        }

        $safety = null;

        if ($options['safety_backup'] ?? true) {
            $safety = $this->create(['compress' => true, 'type' => 'pre-restore', 'note' => translate('Taken automatically before restoring').': '.$name]);
        }

        $started = microtime(true);
        $pdo = $this->pdo();

        $this->audit('restore.started', ['file' => $name, 'safety' => $safety['file'] ?? null]);

        try {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            $pdo->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");

            if ($options['wipe'] ?? false) {
                $this->dropEverything($pdo);
            }

            $executed = $this->runDump($path, $compressed, $pdo);
        } catch (\Throwable $e) {
            $this->audit('restore.failed', ['file' => $name, 'error' => $e->getMessage()]);

            throw new RuntimeException($e->getMessage(), 0, $e);
        } finally {
            try {
                $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            } catch (\Throwable) {
                // The connection is already gone; nothing useful left to do here.
            }
        }

        $result = [
            'file' => $name,
            'statements' => $executed,
            'duration' => round(microtime(true) - $started, 2),
            'safety_backup' => $safety['file'] ?? null,
        ];

        $this->audit('restore.completed', $result);

        return $result;
    }

    private function dropEverything(PDO $pdo): void
    {
        foreach ($this->viewNames() as $view) {
            $pdo->exec('DROP VIEW IF EXISTS '.$this->quoteName($view));
        }

        foreach ($this->tableNames() as $table) {
            $pdo->exec('DROP TABLE IF EXISTS '.$this->quoteName($table));
        }
    }

    /**
     * Streams the dump statement by statement. Reading the whole file into
     * memory is not an option — a dump is routinely larger than memory_limit —
     * and neither is exploding on ";", which would cut every INSERT holding a
     * semicolon inside a string.
     */
    private function runDump(string $path, bool $compressed, PDO $pdo): int
    {
        $handle = $compressed ? gzopen($path, 'rb') : fopen($path, 'rb');

        if (! $handle) {
            throw new RuntimeException(translate('That backup could not be read.'));
        }

        $ctx = ['buffer' => '', 'quote' => null, 'block' => false, 'delimiter' => ';'];
        $executed = 0;
        $line = 0;

        $run = function (string $sql) use ($pdo, &$executed, &$line) {
            $sql = trim($sql);

            if ($sql === '' || $sql === ';') {
                return;
            }

            try {
                $pdo->exec($sql);
                $executed++;
            } catch (\PDOException $e) {
                throw new RuntimeException(translate('Line').' '.$line.' — '.$e->getMessage().' | '.Str::limit(preg_replace('/\s+/', ' ', $sql), 160));
            }
        };

        try {
            $carry = '';

            while (! ($compressed ? gzeof($handle) : feof($handle))) {
                $chunk = $compressed ? gzread($handle, self::READ_CHUNK) : fread($handle, self::READ_CHUNK);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $carry .= $chunk;
                $cut = strrpos($carry, "\n");

                if ($cut === false) {
                    continue;
                }

                $ready = substr($carry, 0, $cut + 1);
                $carry = substr($carry, $cut + 1);

                foreach (explode("\n", rtrim($ready, "\n")) as $text) {
                    $line++;
                    $this->scanLine($text, $ctx, $run);
                }
            }

            if ($carry !== '') {
                $line++;
                $this->scanLine($carry, $ctx, $run);
            }

            // A dump whose last statement has no trailing delimiter.
            if (trim($ctx['buffer']) !== '') {
                $run($ctx['buffer']);
            }
        } finally {
            $compressed ? gzclose($handle) : fclose($handle);
        }

        if ($executed === 0) {
            throw new RuntimeException(translate('No SQL statements were found in that backup.'));
        }

        return $executed;
    }

    /**
     * Appends one line to the statement buffer, emitting whole statements as the
     * delimiter is reached. strcspn does the walking so the scanner jumps between
     * the handful of characters that can change state rather than stepping over
     * every byte of a multi-megabyte INSERT.
     *
     * @param  array{buffer: string, quote: string|null, block: bool, delimiter: string}  $ctx
     */
    private function scanLine(string $line, array &$ctx, callable $emit): void
    {
        $line = rtrim($line, "\r");
        $length = strlen($line);
        $i = 0;

        // DELIMITER is a client instruction, not SQL — it never reaches the server.
        if ($ctx['quote'] === null && ! $ctx['block'] && trim($ctx['buffer']) === ''
            && preg_match('/^\s*DELIMITER\s+(\S+)/i', $line, $m)) {
            $ctx['delimiter'] = $m[1];
            $ctx['buffer'] = '';

            return;
        }

        while ($i < $length) {
            if ($ctx['quote'] !== null) {
                $quote = $ctx['quote'];
                $skip = strcspn($line, $quote === '`' ? '`' : '\\'.$quote, $i);
                $ctx['buffer'] .= substr($line, $i, $skip);
                $i += $skip;

                if ($i >= $length) {
                    break;
                }

                $char = $line[$i];

                if ($char === '\\' && $quote !== '`') {
                    $ctx['buffer'] .= substr($line, $i, 2);
                    $i += 2;

                    continue;
                }

                if (($line[$i + 1] ?? '') === $quote) {
                    $ctx['buffer'] .= $quote.$quote;
                    $i += 2;

                    continue;
                }

                $ctx['buffer'] .= $quote;
                $ctx['quote'] = null;
                $i++;

                continue;
            }

            if ($ctx['block']) {
                $end = strpos($line, '*/', $i);

                if ($end === false) {
                    break;
                }

                $ctx['block'] = false;
                $i = $end + 2;

                continue;
            }

            $delimiter = $ctx['delimiter'];
            $stops = "'\"`-#/".$delimiter[0];
            $skip = strcspn($line, $stops, $i);
            $ctx['buffer'] .= substr($line, $i, $skip);
            $i += $skip;

            if ($i >= $length) {
                break;
            }

            $char = $line[$i];
            $next = $line[$i + 1] ?? '';

            if ($i + strlen($delimiter) <= $length && substr_compare($line, $delimiter, $i, strlen($delimiter)) === 0) {
                $emit($ctx['buffer']);
                $ctx['buffer'] = '';
                $i += strlen($delimiter);

                continue;
            }

            if ($char === "'" || $char === '"' || $char === '`') {
                $ctx['quote'] = $char;
                $ctx['buffer'] .= $char;
                $i++;

                continue;
            }

            // A line comment runs to the end of the line. MySQL needs whitespace
            // after "--" for it to count as one, so "a--b" stays an expression.
            if ($char === '#' || ($char === '-' && $next === '-' && trim($line[$i + 2] ?? ' ') === '')) {
                break;
            }

            if ($char === '/' && $next === '*') {
                // Conditional comments carry real statements (/*!40000 ALTER …*/)
                // so they are kept verbatim; a plain block comment is dropped.
                if (($line[$i + 2] ?? '') === '!') {
                    $ctx['buffer'] .= $char;
                    $i++;

                    continue;
                }

                $end = strpos($line, '*/', $i + 2);

                if ($end === false) {
                    $ctx['block'] = true;

                    break;
                }

                $i = $end + 2;

                continue;
            }

            $ctx['buffer'] .= $char;
            $i++;
        }

        $ctx['buffer'] .= "\n";
    }

    /* ------------------------------------------------------------------
       Inspection helpers
       ------------------------------------------------------------------ */

    private function looksLikeDump(?string $path, bool $compressed): bool
    {
        if (! $path || ! is_file($path)) {
            return false;
        }

        if ($compressed) {
            $head = (string) file_get_contents($path, false, null, 0, 2);

            if ($head !== "\x1f\x8b") {
                return false;
            }

            $handle = gzopen($path, 'rb');

            if (! $handle) {
                return false;
            }

            $sample = (string) gzread($handle, 4096);
            gzclose($handle);
        } else {
            $sample = (string) file_get_contents($path, false, null, 0, 4096);
        }

        if ($sample === '') {
            return false;
        }

        // PHP, shell and HTML markers mean the file is not a dump, whatever its
        // extension says — an uploaded backup is executed against the database.
        foreach (['<?php', '<?=', '<script', '#!/'] as $marker) {
            if (stripos($sample, $marker) !== false) {
                return false;
            }
        }

        return (bool) preg_match('/\b(CREATE\s+TABLE|INSERT\s+INTO|DROP\s+TABLE|SET\s+|CREATE\s+DATABASE|USE\s+|--\s)/i', $sample);
    }

    /** @return array<int, string> */
    public function tableNames(): array
    {
        $rows = $this->pdo()->query('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"')->fetchAll(PDO::FETCH_NUM);

        return array_map(static fn ($row) => $row[0], $rows);
    }

    /** @return array<int, string> */
    public function viewNames(): array
    {
        $rows = $this->pdo()->query('SHOW FULL TABLES WHERE Table_type = "VIEW"')->fetchAll(PDO::FETCH_NUM);

        return array_map(static fn ($row) => $row[0], $rows);
    }

    public function databaseName(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }

    public function databaseBytes(): ?int
    {
        try {
            $rows = $this->pdo()->query(
                'SELECT SUM(data_length + index_length) AS bytes FROM information_schema.TABLES WHERE table_schema = DATABASE()'
            )->fetchAll(PDO::FETCH_ASSOC);

            return isset($rows[0]['bytes']) ? (int) $rows[0]['bytes'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function humanBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (int) $bytes : number_format($bytes, $bytes >= 100 ? 0 : 1)).' '.$units[$i];
    }

    /* ------------------------------------------------------------------
       Connection + audit
       ------------------------------------------------------------------ */

    /**
     * A dedicated connection, so the unbuffered reads the dumper needs and the
     * FOREIGN_KEY_CHECKS a restore flips never touch the request's own session.
     */
    private function pdo(): PDO
    {
        static $pdo = null;

        if ($this->connection) {
            return $this->connection;
        }

        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $config = config('database.connections.'.config('database.default'));

        $dsn = ! empty($config['unix_socket'])
            ? sprintf('mysql:unix_socket=%s;dbname=%s', $config['unix_socket'], $config['database'])
            : sprintf('mysql:host=%s;port=%s;dbname=%s', $config['host'], $config['port'] ?? 3306, $config['database']);

        $dsn .= ';charset='.($config['charset'] ?? 'utf8mb4');

        return $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => false,
        ]);
    }

    /**
     * Backup, download, restore and delete all leave a line in the application
     * log with the admin, the file and the caller's IP. These are the four
     * actions on this screen that nothing else in the panel can undo.
     */
    public function audit(string $event, array $context = []): void
    {
        $admin = auth('admin')->user();

        Log::channel(config('logging.default'))->warning('[database] '.$event, $context + [
            'admin_id' => $admin->id ?? null,
            'admin' => $admin->email ?? null,
            'ip' => request()->ip(),
        ]);
    }
}
