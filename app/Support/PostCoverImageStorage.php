<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class PostCoverImageStorage
{
    public const DIRECTORY = 'posts';

    public const PUBLIC_PREFIX = '/posts/';

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
        $file->move($directory, $filename);

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
}
