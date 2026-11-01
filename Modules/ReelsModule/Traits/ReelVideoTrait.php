<?php

namespace Modules\ReelsModule\Traits;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Modules\ReelsModule\Entities\Reel;
use Modules\ReelsModule\Services\Reel\ReelService;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait ReelVideoTrait
{
    private const CHUNK_BYTES = 1048576;

    private const MIME_TYPES = [
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'mkv' => 'video/x-matroska',
        '3gp' => 'video/3gpp',
        'gif' => 'image/gif',
    ];

    protected function streamReelVideo(Reel $reel, Request $request): Response|StreamedResponse|RedirectResponse
    {
        $disk = app(ReelService::class)->videoDisk($reel);
        $relativePath = ReelService::IMAGE_DIR . $reel->video;

        $filePath = method_exists(Storage::disk($disk), 'path')
            ? Storage::disk($disk)->path($relativePath)
            : null;

        if (! $filePath || ! is_file($filePath)) {
            abort_unless(Storage::disk($disk)->exists($relativePath), 404);

            return response()->redirectTo($reel->video_full_url);
        }

        $fileSize = filesize($filePath);
        $modifiedAt = filemtime($filePath);
        $etag = '"' . md5($modifiedAt . $fileSize) . '"';
        $lastModified = gmdate('D, d M Y H:i:s', $modifiedAt) . ' GMT';

        if ($this->reelVideoIsFresh($request, $etag, $modifiedAt)) {
            return response('', 304, ['ETag' => $etag, 'Last-Modified' => $lastModified]);
        }

        $headers = [
            'Content-Type' => $this->reelVideoMimeType($filePath),
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=86400',
            'ETag' => $etag,
            'Last-Modified' => $lastModified,
        ];

        [$start, $end, $status] = $this->reelVideoRange($request, $fileSize);

        if ($status === 206) {
            if ($start > $end || $start >= $fileSize) {
                return response('', 416, ['Content-Range' => "bytes */{$fileSize}"]);
            }

            $headers['Content-Range'] = "bytes {$start}-{$end}/{$fileSize}";
        }

        $headers['Content-Length'] = (string) ($end - $start + 1);

        return response()->stream(fn () => $this->pushReelVideo($filePath, $start, $end), $status, $headers);
    }

    private function reelVideoIsFresh(Request $request, string $etag, int $modifiedAt): bool
    {
        return $request->header('If-None-Match') === $etag
            || ($request->header('If-Modified-Since') && strtotime($request->header('If-Modified-Since')) >= $modifiedAt);
    }

    private function reelVideoMimeType(string $filePath): string
    {
        return self::MIME_TYPES[strtolower(pathinfo($filePath, PATHINFO_EXTENSION))]
            ?? (mime_content_type($filePath) ?: 'video/mp4');
    }

    private function reelVideoRange(Request $request, int $fileSize): array
    {
        if (! preg_match('/bytes=(\d*)-(\d*)/i', (string) $request->header('Range'), $matches)) {
            return [0, $fileSize - 1, 200];
        }

        return [
            $matches[1] !== '' ? (int) $matches[1] : 0,
            min($matches[2] !== '' ? (int) $matches[2] : $fileSize - 1, $fileSize - 1),
            206,
        ];
    }

    private function pushReelVideo(string $filePath, int $start, int $end): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $handle = fopen($filePath, 'rb');

        if ($handle === false) {
            return;
        }

        try {
            fseek($handle, $start);
            $bytesRemaining = $end - $start + 1;

            while (! feof($handle) && $bytesRemaining > 0) {
                if (connection_aborted()) {
                    break;
                }

                $buffer = fread($handle, min(self::CHUNK_BYTES, $bytesRemaining));

                if ($buffer === false) {
                    break;
                }

                echo $buffer;
                flush();

                $bytesRemaining -= strlen($buffer);
            }
        } finally {
            fclose($handle);
        }
    }
}
