<?php

namespace App\Support;

final class BlogPlaceholder
{
    public static function url(): ?string
    {
        $url = config('app.blog_placeholder_image');

        if (! is_string($url) || trim($url) === '') {
            return null;
        }

        return trim($url);
    }

    public static function coverImage(?string $coverImage): ?string
    {
        if ($coverImage !== null && trim($coverImage) !== '') {
            return $coverImage;
        }

        return self::url();
    }
}
