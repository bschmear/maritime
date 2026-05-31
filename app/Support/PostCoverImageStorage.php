<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class PostCoverImageStorage
{
    public const DIRECTORY = 'posts';

    public const PUBLIC_PREFIX = '/posts/';

    public const MAX_WIDTH = 800;

    /**
     * Store an uploaded cover image under public/posts and return the public URL path.
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
        return is_string($path)
            && $path !== ''
            && str_starts_with($path, self::PUBLIC_PREFIX);
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

        if (str_starts_with($path, self::PUBLIC_PREFIX)) {
            return ltrim(substr($path, 1), '/');
        }

        if (str_starts_with($path, self::DIRECTORY.'/')) {
            return $path;
        }

        $parsed = parse_url($path, PHP_URL_PATH);
        if (! is_string($parsed) || $parsed === '') {
            return null;
        }

        if (str_starts_with($parsed, self::PUBLIC_PREFIX)) {
            return ltrim(substr($parsed, 1), '/');
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
