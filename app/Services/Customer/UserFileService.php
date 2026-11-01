<?php

namespace App\Services\Customer;

use App\Models\UserFile;
use App\Services\BaseService;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Storage\FileStorage;

class UserFileService extends BaseService
{

    public const PRESCRIPTION_TYPE = 'prescription';

    public const MAX_PRESCRIPTION_FILES = 20;

    private const UPLOAD_DIR = 'order/saved_files/';

    private const LIST_COLUMNS = ['id', 'file_name', 'storage'];

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return UserFile::where('user_id', $filters['user_id'] ?? null)
            ->latest()
            ->select(self::LIST_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function prescriptionCount(mixed $userId): int
    {
        return UserFile::where('type', self::PRESCRIPTION_TYPE)->where('user_id', $userId)->count();
    }

    public function storePrescriptions(mixed $userId, array $files): array
    {
        $saved = [];

        foreach ($files as $file) {
            $saved[] = UserFile::create([
                'user_id' => $userId,
                'file_name' => FileStorage::upload(self::UPLOAD_DIR, $file),
                'storage' => FileStorage::getDisk(),
                'mime_type' => $file->getMimeType(),
                'type' => self::PRESCRIPTION_TYPE,
            ]);
        }

        return $saved;
    }

    public function deletePrescriptions(mixed $userId): bool
    {
        $files = UserFile::where('type', self::PRESCRIPTION_TYPE)
            ->where('user_id', $userId)
            ->get(['id', 'file_name']);

        foreach ($files as $file) {
            FileStorage::delete(self::UPLOAD_DIR, $file->file_name);
        }

        return (bool) UserFile::where('type', self::PRESCRIPTION_TYPE)->where('user_id', $userId)->delete();
    }

    public function findByName(mixed $userId, string $fileName): mixed
    {
        return UserFile::where('user_id', $userId)->where('file_name', $fileName)->first();
    }
}
