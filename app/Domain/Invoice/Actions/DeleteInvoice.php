<?php

namespace App\Domain\Invoice\Actions;

/**
 * @deprecated Use {@see RemoveInvoice} directly. Kept for RecordController wiring.
 */
class DeleteInvoice
{
    public function __invoke(int $id, bool $deleteFromQuickbooks = false): array
    {
        return (new RemoveInvoice)(
            $id,
            'delete',
            $deleteFromQuickbooks,
            $deleteFromQuickbooks ? 'auto' : 'auto',
        );
    }
}
