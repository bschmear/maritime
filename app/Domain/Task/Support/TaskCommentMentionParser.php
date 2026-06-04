<?php

declare(strict_types=1);

namespace App\Domain\Task\Support;

/**
 * Parses and inserts task comment @mentions using the token format @[Label](user:ID).
 */
final class TaskCommentMentionParser
{
    /** @var string */
    public const TOKEN_PATTERN = '/@\[([^\]]+)\]\(user:(\d+)\)/';

    /**
     * @return list<int>
     */
    public static function extractUserIds(string $body): array
    {
        if (! preg_match_all(self::TOKEN_PATTERN, $body, $matches)) {
            return [];
        }

        $ids = array_map('intval', $matches[2]);

        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    public static function insertToken(int $userId, string $label): string
    {
        $safeLabel = str_replace(['[', ']', '(', ')'], '', trim($label));

        return '@['.($safeLabel !== '' ? $safeLabel : 'User')."](user:{$userId})";
    }

    public static function displayBody(string $body): string
    {
        return (string) preg_replace(self::TOKEN_PATTERN, '@$1', $body);
    }

    /**
     * @param  iterable<int, string>  $userLabels  map of user id => display label
     */
    public static function bodyToHtml(string $body, iterable $userLabels = []): string
    {
        $labels = [];
        foreach ($userLabels as $id => $label) {
            $labels[(int) $id] = (string) $label;
        }

        $html = '';
        $offset = 0;

        if (preg_match_all(self::TOKEN_PATTERN, $body, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $i => $fullMatch) {
                $start = $fullMatch[1];
                $html .= nl2br(e(substr($body, $offset, $start - $offset)), false);

                $id = (int) $matches[2][$i][0];
                $label = $labels[$id] ?? $matches[1][$i][0];
                $html .= '<span class="font-medium text-primary-700 dark:text-primary-400">@'
                    .e($label)
                    .'</span>';

                $offset = $start + strlen($fullMatch[0]);
            }
        }

        $html .= nl2br(e(substr($body, $offset)), false);

        return $html;
    }
}
