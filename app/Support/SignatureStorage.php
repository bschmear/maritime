<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Drawn e-signatures are stored under {@see self::DIRECTORY} so CloudFront (public prefix) can serve them.
 */
final class SignatureStorage
{
    public const DIRECTORY = 'public/signatures';

    public static function storeDrawnImage(string $base64Data, string $uuid): ?string
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $base64Data, $matches)) {
            return null;
        }

        $extension = $matches[1];
        $decoded = base64_decode(substr($base64Data, strpos($base64Data, ',') + 1));
        if (! $decoded) {
            return null;
        }

        $filename = $uuid.'-signature.'.$extension;
        $key = self::DIRECTORY.'/'.$filename;

        try {
            $disk = Storage::disk('s3');
            $disk->getClient()->putObject([
                'Bucket' => $disk->getConfig()['bucket'],
                'Key' => $key,
                'Body' => $decoded,
                'ContentType' => "image/{$extension}",
                'CacheControl' => 'public, max-age=604800',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store signature image: '.$e->getMessage());

            return null;
        }

        return $key;
    }

    public static function url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if ($cdnUrl && str_starts_with($path, 'public/')) {
            return rtrim($cdnUrl, '/').'/'.$path;
        }

        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addHours(2));
        } catch (\Throwable) {
            try {
                return Storage::disk('s3')->url($path);
            } catch (\Throwable) {
                return null;
            }
        }
    }
}
