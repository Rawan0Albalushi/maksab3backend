<?php

namespace App\Support\Storage;

use App\Exceptions\InvalidUploadException;
use App\Services\System\BusinessSettingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;

class FileStorage
{
    private const BROWSABLE_IMAGE_EXTENSIONS = ['jpg', 'png', 'jpeg', 'gif', 'bmp', 'tif', 'tiff', 'webp'];


    public static function upload(string $dir, $image = null, ?int $maxSizeMb = null, ?string $allowedExtensions = null)
    {
        $validExtForWebp = ['jpg', 'jpeg', 'png'];
        try {
            if ($image != null) {
                self::validateFile($image, $maxSizeMb, $allowedExtensions);

                $format = $image->getClientOriginalExtension();
                if (in_array($format, $validExtForWebp)) {
                    $manager = new ImageManager(Driver::class);
                    $image = $manager->read($image);
                    $image = $image->encode(new WebpEncoder(quality: 80));
                    $format = 'webp';
                }
                $imageName = \Carbon\Carbon::now()->toDateString().'-'.uniqid().'.'.$format;

                if (! Storage::disk(self::getDisk())->exists($dir)) {
                    Storage::disk(self::getDisk())->makeDirectory($dir);
                }

                if ($image instanceof UploadedFile) {
                    Storage::disk(self::getDisk())->putFileAs($dir, $image, $imageName);
                } else {
                    Storage::disk(self::getDisk())->put($dir.'/'.$imageName, $image->toString());
                }

            } else {
                $imageName = 'def.png';
            }
        } catch (InvalidUploadException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('File upload failed', [
                'disk' => self::getDisk(),
                'dir' => $dir,
                'error' => $e->getMessage(),
            ]);

            throw new InvalidUploadException(
                'Image upload failed. Please try again.'
            );
        }

        return $imageName;
    }

    public static function update(string $dir, $old_image, $image = null, ?int $maxSizeMb = null, ?string $allowedExtensions = null)
    {
        if ($image == null) {
            return $old_image;
        }
        try {
            if ($old_image && Storage::disk(self::getDisk())->exists($dir.$old_image)) {
                Storage::disk(self::getDisk())->delete($dir.$old_image);
            }
        } catch (\Exception $e) {
        }
        $imageName = self::upload($dir, $image, $maxSizeMb, $allowedExtensions);

        return $imageName;
    }

    public static function delete(string $dir, $old_image)
    {

        try {
            if (Storage::disk('public')->exists($dir.$old_image)) {
                Storage::disk('public')->delete($dir.$old_image);
            }
            if (Storage::disk('s3')->exists($dir.$old_image)) {
                Storage::disk('s3')->delete($dir.$old_image);
            }
        } catch (\Exception $e) {
        }

        return true;
    }

    public static function uploadAttachments(string $dir, array $files): array
    {
        $stored = [];

        foreach ($files as $file) {
            if (! $file) {
                continue;
            }

            $stored[] = self::upload($dir, $file);
        }

        return $stored;
    }

    public static function getDisk()
    {
        $config = app(BusinessSettingService::class)->value('local_storage');

        return isset($config) ? ($config == 0 ? 's3' : 'public') : 'public';
    }

    public static function getStorageDiskByKey($model, string $key, string $default = 'public'): string
    {
        if (! $model || ! isset($model->storage) || count($model->storage) < 1) {
            return $default;
        }

        foreach ($model->storage as $storage) {
            if (($storage['key'] ?? null) === $key) {
                return $storage['value'] ?? $default;
            }
        }

        return $default;
    }

    public static function copyStorageFile(string $dir, ?string $fileName, string $sourceDisk = 'public'): ?string
    {
        if (! $fileName) {
            return null;
        }

        $extension = pathinfo($fileName, PATHINFO_EXTENSION) ?: 'tmp';
        $newFileName = \Carbon\Carbon::now()->toDateString().'-'.uniqid().'.'.$extension;
        $sourcePath = $dir.$fileName;
        $targetDisk = self::getDisk();

        try {
            if (! Storage::disk($sourceDisk)->exists($sourcePath)) {
                return null;
            }

            if (! Storage::disk($targetDisk)->exists($dir)) {
                Storage::disk($targetDisk)->makeDirectory($dir);
            }

            Storage::disk($targetDisk)->put($dir.$newFileName, Storage::disk($sourceDisk)->get($sourcePath));

            return $newFileName;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getStoredFileSize(string $dir, ?string $fileName, string $disk = 'public'): int
    {
        if (! $fileName) {
            return 0;
        }

        try {
            $path = $dir.$fileName;
            if (Storage::disk($disk)->exists($path)) {
                return (int) Storage::disk($disk)->size($path);
            }
        } catch (\Throwable $e) {
        }

        return 0;
    }

    public static function validateFile($image, ?int $maxSizeMb = null, ?string $allowedExtensionsString = null)
    {
        if (! $image instanceof UploadedFile) {
            throw new InvalidUploadException('Invalid file upload.');
        }

        $maxSizeMb = $maxSizeMb ?? MAX_FILE_SIZE;

        if ($image->getSize() > $maxSizeMb * 1024 * 1024) {
            throw new InvalidUploadException('File size exceeds the limit of '.$maxSizeMb.'MB');
        }

        $allowedExtensions = explode(',', $allowedExtensionsString ?? (IMAGE_EXTENSION.','.VIDEO_EXTENSION.','.DOCUMENT_EXTENSION.','.AUDIO_EXTENSION.','.FILE_EXTENSION));
        $allowedExtensions = array_map(function ($ext) {
            return str_replace('.', '', trim($ext));
        }, $allowedExtensions);

        $extension = strtolower($image->getClientOriginalExtension());

        if (! $extension || $extension == '') {
            $extension = self::extensionFromMimeType($image->getMimeType());
        }

        if (! in_array($extension, $allowedExtensions)) {
            throw new InvalidUploadException('File type not allowed.');
        }
    }

    public static function extensionFromMimeType(string $mimeType): string
    {
        $mimeType = strtolower($mimeType);

        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',

            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'video/ogg' => 'ogg',

            'audio/mpeg' => 'mp3',
            'audio/wav' => 'wav',
            'audio/ogg' => 'ogg',

            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'excel',

            'application/zip' => 'zip',
            'application/octet-stream' => 'p8',
        ];

        if (isset($map[$mimeType])) {
            return $map[$mimeType];
        }

        return explode('/', $mimeType)[1] ?? '';
    }

    public static function formatFilesAndFolders(iterable $paths, string $type): array
    {
        $rows = [];

        foreach ($paths as $path) {
            $name = self::extractFileName($path);

            if (! in_array(self::extractFileExtension($name), self::BROWSABLE_IMAGE_EXTENSIONS) && $type !== 'folder') {
                continue;
            }

            $rows[] = [
                'name' => $name,
                'path' => match ($type) {
                    'file', 'folder' => $path,
                    default => '',
                },
                'db_path' => self::toDatabasePath($path),
                'type' => $type,
            ];
        }

        return $rows;
    }

    private static function extractFileName(string $path): string
    {
        $segments = explode('/', $path);

        return end($segments);
    }

    private static function extractFileExtension(string $name): string
    {
        $segments = explode('.', $name);

        return strtolower(end($segments));
    }

    private static function toDatabasePath(string $path): string
    {
        $segments = explode('/', $path, 3);

        return end($segments);
    }

    public static function updateStorageTable($dataType, $dataId, $image): void
    {
        DB::table('storages')->updateOrInsert([
            'data_type' => $dataType,
            'data_id' => $dataId,
            'key' => 'image',
        ], [
            'value' => self::getDisk(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
