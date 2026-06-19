<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\InboundEmail\InboundEmailBodyExtractor;
use App\Support\InboundEmail\LeadNameSplitter;
use Tests\TestCase;

class InboundEmailSupportTest extends TestCase
{
    public function test_body_extractor_prefers_plain_text(): void
    {
        $body = InboundEmailBodyExtractor::extract([
            'text' => 'Plain inquiry body',
            'html' => '<p>HTML body</p>',
        ]);

        $this->assertSame('Plain inquiry body', $body);
    }

    public function test_body_extractor_falls_back_to_stripped_html(): void
    {
        $body = InboundEmailBodyExtractor::extract([
            'html' => '<p>Hello <strong>buyer</strong></p>',
        ]);

        $this->assertSame('Hello buyer', $body);
    }

    public function test_lead_name_splitter_splits_first_and_last(): void
    {
        $this->assertSame(
            ['first_name' => 'John', 'last_name' => 'Martinez'],
            LeadNameSplitter::split('John Martinez')
        );
    }

    public function test_lead_name_splitter_handles_single_name(): void
    {
        $this->assertSame(
            ['first_name' => 'Madonna', 'last_name' => null],
            LeadNameSplitter::split('Madonna')
        );
    }
}
