<?php

class InvoiceSentTemplate
{
    public function render(Invoice $invoice): string
    {
        return "Your invoice #{$invoice->number} is ready: {$invoice->url}";
    }
}
