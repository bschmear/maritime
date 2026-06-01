<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Symfony\Component\Mime\MimeTypes;

class PostCoverImageStorage
{
    /** S3 object prefix (served via CloudFront); not a local public/posts path. */
    public const S3_DIRECTORY = 'public/posts';

    public const MAX_WIDTH = 800;

    /** @var string Legacy local paths from before S3 migration. */
    private const LEGACY_LOCAL_PREFIXES = ['/post-covers/', '/posts/'];

    /**
     * Upload a cover image to S3 and return its CloudFront URL.
     */
    public static function store(UploadedFile $file, ?string $previousPath = null): string
    {
        self::deleteIfStored($previousPath);

        $extension = self::extensionFor($file);
        $filename = Str::uuid().'.'.$extension;
        $key = self::S3_DIRECTORY.'/'.$filename;

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
            Log::error('Failed to store blog post cover image.', [
                'key' => $key,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            if ($shouldDeleteTemp && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        return self::cdnUrl($key);
    }

    public static function deleteIfStored(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $key = self::s3KeyFromStoredPath($path);
        if ($key !== null) {
            try {
                $disk = Storage::disk('s3');
                if ($disk->exists($key)) {
                    $disk->delete($key);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to delete blog post cover from S3.', [
                    'key' => $key,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        self::deleteLegacyLocalFile($path);
    }

    /** @deprecated Use {@see deleteIfStored()}. */
    public static function deleteIfStoredLocally(?string $path): void
    {
        self::deleteIfStored($path);
    }

    public static function isStoredPublicPath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        if (self::s3KeyFromStoredPath($path) !== null) {
            return true;
        }

        foreach (self::LEGACY_LOCAL_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, \Closure|string>
     */
    public static function storedPublicPathRules(): array
    {
        return [
            'nullable',
            'string',
            'max:512',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && $value !== '' && ! self::isStoredPublicPath((string) $value)) {
                    $fail('The '.$attribute.' must be a valid cover image path.');
                }
            },
        ];
    }

    public static function cdnUrl(string $key): string
    {
        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if (! $cdnUrl) {
            throw new \RuntimeException('CLOUDFRONT_URL is not configured.');
        }

        return rtrim($cdnUrl, '/').'/'.ltrim($key, '/');
    }

    /**
     * @return string|null S3 object key (e.g. public/posts/uuid.jpg)
     */
    public static function s3KeyFromStoredPath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, self::S3_DIRECTORY.'/')) {
            return $path;
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if ($cdnUrl && str_starts_with($path, rtrim($cdnUrl, '/').'/')) {
            $key = substr($path, strlen(rtrim($cdnUrl, '/')));
            $key = ltrim($key, '/');

            return str_starts_with($key, self::S3_DIRECTORY.'/') ? $key : null;
        }

        $parsed = parse_url($path, PHP_URL_PATH);
        if (is_string($parsed) && $parsed !== '') {
            $parsed = ltrim($parsed, '/');
            if (str_starts_with($parsed, self::S3_DIRECTORY.'/')) {
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

    private static function deleteLegacyLocalFile(string $path): void
    {
        $relative = self::legacyLocalRelativePath($path);
        if ($relative === null) {
            return;
        }

        $fullPath = public_path($relative);
        if (File::isFile($fullPath)) {
            File::delete($fullPath);
        }
    }

    /**
     * @return string|null Path relative to public/ for legacy local files
     */
    private static function legacyLocalRelativePath(string $path): ?string
    {
        $path = trim($path);

        foreach (self::LEGACY_LOCAL_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return ltrim(substr($path, 1), '/');
            }
        }

        $parsed = parse_url($path, PHP_URL_PATH);
        if (! is_string($parsed) || $parsed === '') {
            return null;
        }

        foreach (self::LEGACY_LOCAL_PREFIXES as $prefix) {
            if (str_starts_with($parsed, $prefix)) {
                return ltrim(substr($parsed, 1), '/');
            }
        }

        if (str_starts_with($path, 'post-covers/') || str_starts_with($path, 'posts/')) {
            return $path;
        }

        return null;
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
