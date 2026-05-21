<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use Illuminate\Mail\Mailables\Address;

/**
 * Sets Reply-To to the estimate's salesperson so customer replies reach the right person
 * while the visible From address can remain the tenant/system sender.
 */
trait RepliesToEstimateSalesperson
{
    /**
     * @return array<int, Address>
     */
    protected function replyToSalespersonOnEstimate(): array
    {
        $user = $this->estimate->user ?? $this->estimate->salesperson;

        if ($user === null) {
            return [];
        }

        $email = is_string($user->email ?? null) ? trim($user->email) : '';
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        $name = trim((string) (
            $user->display_name
            ?: $user->full_name
            ?: trim(($user->first_name ?? '').' '.($user->last_name ?? ''))
        ));

        return [new Address($email, $name)];
    }
}
