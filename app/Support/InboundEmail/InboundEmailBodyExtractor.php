<?php

namespace App\Support\InboundEmail;

class InboundEmailBodyExtractor
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public static function extract(array $payload): string
    {
        $text = trim((string) ($payload['text'] ?? ''));
        if ($text !== '') {
            return $text;
        }

        $html = trim((string) ($payload['html'] ?? ''));
        if ($html === '') {
            return '';
        }

        $stripped = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return preg_replace("/\n{3,}/", "\n\n", $stripped) ?? $stripped;
    }
}
