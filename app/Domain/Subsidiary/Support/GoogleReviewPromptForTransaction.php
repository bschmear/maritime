<?php

declare(strict_types=1);

namespace App\Domain\Subsidiary\Support;

use App\Domain\Subsidiary\Support\GoogleReviewRequestMessage;
use App\Domain\Transaction\Models\Transaction;
use App\Models\AccountSettings;

final class GoogleReviewPromptForTransaction
{
    /**
     * @return array{
     *     transaction_id: int,
     *     customer_email: string,
     *     customer_name: ?string,
     *     subsidiary_name: string,
     *     google_review_url: string,
     *     default_message: string,
     *     sandbox_mode: bool
     * }|null
     */
    public static function resolve(Transaction $transaction): ?array
    {
        $missing = [];
        if (! $transaction->relationLoaded('subsidiary')) {
            $missing['subsidiary'] = fn ($q) => $q->select([
                'id',
                'display_name',
                'google_review_url',
                'prompt_google_review_on_transaction_close',
            ]);
        }
        if (! $transaction->relationLoaded('customer')) {
            $missing['customer.contact'] = fn ($q) => $q->select(['id', 'email', 'first_name', 'last_name', 'display_name']);
        } elseif ($transaction->customer !== null && ! $transaction->customer->relationLoaded('contact')) {
            $missing['customer.contact'] = fn ($q) => $q->select(['id', 'email', 'first_name', 'last_name', 'display_name']);
        }

        if ($missing !== []) {
            $transaction->loadMissing($missing);
        }

        $subsidiary = $transaction->subsidiary;
        $reviewUrl = trim((string) ($subsidiary?->google_review_url ?? ''));

        if ($subsidiary === null
            || $reviewUrl === ''
            || ! $subsidiary->prompt_google_review_on_transaction_close) {
            return null;
        }

        $email = trim((string) (
            $transaction->customer_email
            ?? $transaction->customer?->contact?->email
            ?? $transaction->customer?->email
            ?? ''
        ));

        $customerName = trim((string) ($transaction->customer_name ?? ''));
        if ($customerName === '') {
            $customerName = trim((string) ($transaction->customer?->contact?->display_name ?? ''));
        }

        return [
            'transaction_id' => (int) ($transaction->getKey() ?? $transaction->id),
            'customer_email' => $email,
            'customer_name' => $customerName !== '' ? $customerName : null,
            'subsidiary_name' => (string) $subsidiary->display_name,
            'google_review_url' => $reviewUrl,
            'default_message' => GoogleReviewRequestMessage::default($subsidiary->display_name),
            'sandbox_mode' => AccountSettings::getCurrent()->smsSandboxMode(),
        ];
    }
}
