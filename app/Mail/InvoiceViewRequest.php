<?php

namespace App\Mail;

use App\Domain\Invoice\Models\Invoice;
use App\Enums\Payments\Terms;
use App\Models\AccountSettings;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent synchronously when staff emails a customer the public invoice link.
 */
class InvoiceViewRequest extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public AccountSettings $account,
        public string $viewUrl,
    ) {}

    public function envelope(): Envelope
    {
        $settings = is_array($this->account->settings) ? $this->account->settings : [];
        $company = $settings['business_name'] ?? config('app.name', 'Your service provider');

        return new Envelope(
            subject: 'Invoice '.$this->invoice->display_name.' — '.$company,
        );
    }

    public function content(): Content
    {
        $settings = is_array($this->account->settings) ? $this->account->settings : [];
        $companyName = $settings['business_name'] ?? config('app.name', 'Your service provider');

        $tz = $this->account->timezone ?? config('app.timezone');
        $invoiceDate = $this->invoice->created_at?->timezone($tz)?->format('F j, Y');
        $dueDate = $this->invoice->due_at?->timezone($tz)?->format('F j, Y');

        $paymentTermEnum = Terms::tryFrom((string) ($this->invoice->payment_term ?? ''));
        $paymentTermsLabel = $paymentTermEnum?->label()
            ?? (trim((string) $this->invoice->payment_term) !== ''
                ? ucfirst(str_replace('_', ' ', (string) $this->invoice->payment_term))
                : null);

        $subsidiaryName = $this->invoice->transaction?->subsidiary?->display_name;
        if ($subsidiaryName !== null && strcasecmp((string) $subsidiaryName, (string) $companyName) === 0) {
            $subsidiaryName = null;
        }
        $location = $this->invoice->transaction?->location;
        $locationLines = [];
        if ($location) {
            if ($location->display_name) {
                $locationLines[] = $location->display_name;
            }
            $addr = array_filter([
                $location->address_line_1,
                trim(implode(', ', array_filter([$location->city, $location->state, $location->postal_code]))),
            ]);
            if ($addr !== []) {
                $locationLines[] = implode(', ', $addr);
            }
            if ($location->phone) {
                $locationLines[] = 'Phone: '.$location->phone;
            }
            if ($location->email) {
                $locationLines[] = 'Email: '.$location->email;
            }
        }

        $customerName = trim((string) ($this->invoice->customer_name ?? ''));
        if ($customerName === '') {
            $customerName = trim((string) ($this->invoice->contact?->display_name ?? ''));
        }

        $total = (float) $this->invoice->total;
        $amountPaid = (float) $this->invoice->amount_paid;
        $amountDue = (float) $this->invoice->amount_due;

        return new Content(
            view: 'emails.invoice-view-request',
            with: [
                'invoice' => $this->invoice,
                'account' => $this->account,
                'viewUrl' => $this->viewUrl,
                'companyName' => $companyName,
                'subsidiaryName' => $subsidiaryName,
                'locationLines' => $locationLines,
                'customerName' => $customerName !== '' ? $customerName : null,
                'invoiceDate' => $invoiceDate,
                'dueDate' => $dueDate,
                'paymentTermsLabel' => $paymentTermsLabel,
                'invoiceTotalFormatted' => number_format($total, 2),
                'amountPaidFormatted' => number_format($amountPaid, 2),
                'amountDueFormatted' => number_format($amountDue, 2),
                'currency' => $this->invoice->currency ?? 'USD',
                'logoUrl' => $this->account->logo_url,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
