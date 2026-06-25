<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Subsidiary\Models\Subsidiary;
use App\Domain\Subsidiary\Support\GoogleReviewPromptForTransaction;
use App\Domain\Transaction\Models\Transaction;
use App\Models\AccountSettings;
use Tests\TestCase;

class GoogleReviewPromptForTransactionTest extends TestCase
{
    public function test_returns_null_without_subsidiary_review_url_or_prompt_flag(): void
    {
        $transaction = new Transaction([
            'id' => 10,
            'subsidiary_id' => 1,
            'customer_email' => 'buyer@example.com',
        ]);

        $transaction->setRelation('subsidiary', new Subsidiary([
            'display_name' => 'Marina',
            'google_review_url' => null,
            'prompt_google_review_on_transaction_close' => false,
        ]));

        $this->assertNull(GoogleReviewPromptForTransaction::resolve($transaction));
    }

    public function test_returns_prompt_payload_when_subsidiary_is_configured(): void
    {
        AccountSettings::clearCache();
        $settings = new AccountSettings;
        $settings->forceFill(['id' => 1, 'sandbox_mode' => false]);
        $settings->exists = true;

        $property = new \ReflectionProperty(AccountSettings::class, 'resolved');
        $property->setAccessible(true);
        $property->setValue(null, $settings);

        try {
            $transaction = new Transaction;
            $transaction->forceFill([
                'id' => 10,
                'subsidiary_id' => 1,
                'customer_email' => 'buyer@example.com',
                'customer_name' => 'Taylor Buyer',
            ]);
            $transaction->exists = true;

            $transaction->setRelation('subsidiary', new Subsidiary([
                'id' => 1,
                'display_name' => 'Marina',
                'google_review_url' => 'https://g.page/r/CQu90eLhS4cAEAE/review',
                'prompt_google_review_on_transaction_close' => true,
            ]));

            $prompt = GoogleReviewPromptForTransaction::resolve($transaction);

            $this->assertIsArray($prompt);
            $this->assertSame(10, $prompt['transaction_id']);
            $this->assertSame('buyer@example.com', $prompt['customer_email']);
            $this->assertSame('Marina', $prompt['subsidiary_name']);
            $this->assertSame('https://g.page/r/CQu90eLhS4cAEAE/review', $prompt['google_review_url']);
            $this->assertSame(
                'We appreciate your business with Marina. We\'d appreciate it if you could leave us a Google review.',
                $prompt['default_message'],
            );
            $this->assertFalse($prompt['sandbox_mode']);
        } finally {
            AccountSettings::clearCache();
        }
    }
}
