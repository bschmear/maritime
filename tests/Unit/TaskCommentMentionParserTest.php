<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Task\Support\TaskCommentMentionParser;
use Tests\TestCase;

class TaskCommentMentionParserTest extends TestCase
{
    public function test_extracts_user_ids_from_tokens(): void
    {
        $body = 'Hi @[Jane Doe](user:5) and @[Bob](user:12) — please review.';

        $this->assertSame([5, 12], TaskCommentMentionParser::extractUserIds($body));
    }

    public function test_display_body_renders_friendly_mentions(): void
    {
        $body = 'Hi @[Ryder Storm](user:2)!';
        $this->assertSame('Hi @Ryder Storm!', TaskCommentMentionParser::displayBody($body));
    }

    public function test_body_to_html_highlights_mentions(): void
    {
        $body = 'Hi @[Jane](user:3)!';
        $html = TaskCommentMentionParser::bodyToHtml($body, [3 => 'Jane Smith']);

        $this->assertStringContainsString('@Jane Smith', $html);
        $this->assertStringContainsString('text-primary-700', $html);
        $this->assertStringNotContainsString('user:3', $html);
    }
}
