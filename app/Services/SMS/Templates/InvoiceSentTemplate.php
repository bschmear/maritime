<?php

declare(strict_types=1);

namespace App\Services\SMS\Templates;

use App\Domain\Invoice\Models\Invoice;

class InvoiceSentTemplate
{
    public function render(Invoice $invoice, string $viewUrl): string
    {
        $number = $invoice->sequence ?? $invoice->id;

        return "Your invoice #{$number} is ready: {$viewUrl}";
    }
}
