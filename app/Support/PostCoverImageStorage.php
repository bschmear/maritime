<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class PostCoverImageStorage
{
    /** @var string Must not match kiosk/blog URL segments (e.g. /posts). */
    public const DIRECTORY = 'post-covers';

    public const PUBLIC_PREFIX = '/post-covers/';

    /** @var string Legacy path when covers lived under public/posts (conflicted with /posts routes). */
    private const LEGACY_PUBLIC_PREFIX = '/posts/';

    public const MAX_WIDTH = 800;

    /**
     * Store an uploaded cover image under public/post-covers and return the public URL path.
     */
    public static function store(UploadedFile $file, ?string $previousPath = null): string
    {
        self::deleteIfStoredLocally($previousPath);

        $directory = public_path(self::DIRECTORY);
        File::ensureDirectoryExists($directory);

        $extension = $file->guessExtension() ?: 'jpg';
        $extension = strtolower($extension);
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
            $extension = 'jpg';
        }

        $filename = Str::uuid().'.'.$extension;
        $fullPath = $directory.DIRECTORY_SEPARATOR.$filename;

        $mime = $file->getMimeType() ?? '';
        if (str_starts_with($mime, 'image/')) {
            self::storeImage($file, $fullPath);
        } else {
            $file->move($directory, $filename);
        }

        return self::PUBLIC_PREFIX.$filename;
    }

    public static function deleteIfStoredLocally(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $relative = self::relativePath($path);
        if ($relative === null) {
            return;
        }

        $fullPath = public_path($relative);
        if (File::isFile($fullPath)) {
            File::delete($fullPath);
        }
    }

    /**
     * @return string|null Path relative to public/ (e.g. posts/uuid.jpg)
     */
    public static function isStoredPublicPath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        return str_starts_with($path, self::PUBLIC_PREFIX)
            || str_starts_with($path, self::LEGACY_PUBLIC_PREFIX);
    }

    /**
     * @return array<int, \Closure|string>
     */
    public static function storedPublicPathRules(): array
    {
        return [
            'nullable',
            'string',
            'max:255',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value !== null && $value !== '' && ! self::isStoredPublicPath((string) $value)) {
                    $fail('The '.$attribute.' must be a valid cover image path.');
                }
            },
        ];
    }

    /**
     * @return string|null Path relative to public/ (e.g. posts/uuid.jpg)
     */
    public static function relativePath(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        foreach ([self::PUBLIC_PREFIX, self::LEGACY_PUBLIC_PREFIX] as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return ltrim(substr($path, 1), '/');
            }
        }

        if (str_starts_with($path, self::DIRECTORY.'/') || str_starts_with($path, 'posts/')) {
            return $path;
        }

        $parsed = parse_url($path, PHP_URL_PATH);
        if (! is_string($parsed) || $parsed === '') {
            return null;
        }

        foreach ([self::PUBLIC_PREFIX, self::LEGACY_PUBLIC_PREFIX] as $prefix) {
            if (str_starts_with($parsed, $prefix)) {
                return ltrim(substr($parsed, 1), '/');
            }
        }

        return null;
    }

    private static function storeImage(UploadedFile $file, string $fullPath): void
    {
        $source = $file->getRealPath();
        $width = self::imageWidth($source);

        if ($width !== null && $width <= self::MAX_WIDTH) {
            File::copy($source, $fullPath);

            return;
        }

        Image::read($source)
            ->scaleDown(width: self::MAX_WIDTH)
            ->save($fullPath);
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
