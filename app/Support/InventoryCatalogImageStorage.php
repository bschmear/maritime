<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\Mime\MimeTypes;

class InventoryCatalogImageStorage
{
    public const S3_DIRECTORY = 'public/inventory/boat_makes';

    public const MAX_WIDTH = 500;

    /**
     * @return array{url: string, storage_key: string}
     */
    public static function store(UploadedFile $file, ?string $previousPath = null, string $directory = self::S3_DIRECTORY): array
    {
        self::deleteIfStored($previousPath);

        $extension = self::extensionFor($file);
        $filename = Str::uuid().'.'.$extension;
        $key = rtrim($directory, '/').'/'.$filename;

        $tempPath = self::prepareUploadPath($file, $extension);
        $shouldDeleteTemp = $tempPath !== $file->getRealPath();

        try {
            $disk = Storage::disk('s3');
            $disk->getClient()->putObject([
                'Bucket' => $disk->getConfig()['bucket'],
                'Key' => $key,
                'SourceFile' => $tempPath,
                'ContentType' => self::mimeForExtension($extension),
                'CacheControl' => 'public, max-age=604800',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to store inventory catalog image.', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($shouldDeleteTemp && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        return [
            'url' => self::cdnUrl($key),
            'storage_key' => $key,
        ];
    }

    public static function deleteIfStored(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $key = self::s3KeyFromStoredPath($path);
        if ($key === null) {
            return;
        }

        try {
            $disk = Storage::disk('s3');
            if ($disk->exists($key)) {
                $disk->delete($key);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to delete inventory catalog image from S3.', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public static function cdnUrl(string $key): string
    {
        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if (! $cdnUrl) {
            throw new \RuntimeException('CLOUDFRONT_URL is not configured.');
        }

        return rtrim($cdnUrl, '/').'/'.ltrim($key, '/');
    }

    public static function s3KeyFromStoredPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, 'public/inventory/')) {
            return $path;
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if ($cdnUrl && str_starts_with($path, rtrim($cdnUrl, '/').'/')) {
            $key = substr($path, strlen(rtrim($cdnUrl, '/')));
            $key = ltrim($key, '/');

            return str_starts_with($key, 'public/inventory/') ? $key : null;
        }

        $parsed = parse_url($path, PHP_URL_PATH);
        if (is_string($parsed) && $parsed !== '') {
            $parsed = ltrim($parsed, '/');
            if (str_starts_with($parsed, 'public/inventory/')) {
                return $parsed;
            }
        }

        return null;
    }

    private static function extensionFor(UploadedFile $file): string
    {
        $mime = $file->getMimeType() ?? '';
        $extensions = MimeTypes::getDefault()->getExtensions($mime);
        $extension = strtolower($extensions[0] ?? $file->guessExtension() ?: 'jpg');

        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            return 'jpg';
        }

        return $extension === 'jpeg' ? 'jpg' : $extension;
    }

    private static function mimeForExtension(string $extension): string
    {
        return match ($extension) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/jpeg',
        };
    }

    private static function prepareUploadPath(UploadedFile $file, string $extension): string
    {
        $mime = $file->getMimeType() ?? '';
        if (! str_starts_with($mime, 'image/')) {
            return $file->getRealPath();
        }

        $source = $file->getRealPath();
        $width = self::imageWidth($source);

        if ($width !== null && $width <= self::MAX_WIDTH) {
            return $source;
        }

        $tempDir = storage_path('app/temp');
        File::ensureDirectoryExists($tempDir);

        $tempPath = $tempDir.'/'.Str::uuid().'.'.$extension;

        Image::read($source)
            ->scaleDown(width: self::MAX_WIDTH)
            ->save($tempPath);

        return $tempPath;
    }

    private static function imageWidth(string $path): ?int
    {
        $info = @getimagesize($path);

        if ($info === false) {
            return null;
        }

        return $info[0] ?? null;
    }
}
