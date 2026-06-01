<?php

namespace App\Services\SMS;

use App\Domain\Contact\Models\Contact;
use App\Domain\Customer\Models\Customer;
use App\Domain\Delivery\Models\Delivery;
use App\Domain\Estimate\Models\Estimate;
use App\Domain\Invoice\Models\Invoice;
use App\Domain\ServiceTicket\Models\ServiceTicket;
use App\Domain\User\Models\User as TenantUser;
use App\Enums\SMS;
use App\Models\AccountSettings;
use App\Models\User as WebUser;
use App\Services\SMS\Data\SmsResult;
use App\Services\TenantStaffResolver;

class SmsService
{
    /**
     * Whether the current tenant has SMS enabled at all.
     */
    public function smsGloballyEnabled(): bool
    {
        return AccountSettings::getCurrent()->smsGloballyEnabled();
    }

    public function smsSandboxMode(): bool
    {
        return AccountSettings::getCurrent()->smsSandboxMode();
    }

    /**
     * Whether the current tenant wants SMS for a notification category.
     */
    public function tenantWantsSms(SMS|string $type): bool
    {
        $enum = $type instanceof SMS ? $type : SMS::tryFrom($type);
        if ($enum === null) {
            return false;
        }

        return AccountSettings::getCurrent()->wantsSms($enum);
    }

    /**
     * Whether the UI may offer “email + SMS” for estimate approval sends.
     *
     * @param  WebUser|TenantUser|null  $authUser  Session user is usually {@see WebUser} (central); phones live on the tenant {@see TenantUser} row matched by email.
     * @return array{offered: bool, hint: ?string} hint explains why SMS is unavailable when estimate SMS is enabled in settings but a destination number is missing.
     */
    public function estimateApprovalSmsCanBeOffered(?Customer $customer, WebUser|TenantUser|null $authUser): array
    {
        if (! $this->tenantWantsSms(SMS::Estimate)) {
            return ['offered' => false, 'hint' => null];
        }

        if ($authUser === null) {
            return ['offered' => false, 'hint' => 'Sign in to send SMS.'];
        }

        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            if ($tenantStaff === null) {
                return [
                    'offered' => false,
                    'hint' => 'No staff user in this tenant matches your login email; create or link your staff profile to send sandbox SMS.',
                ];
            }

            $to = $this->normalizePhoneForSms($tenantStaff->mobile_phone ?? $tenantStaff->office_phone ?? null);
            if ($to === null) {
                return [
                    'offered' => false,
                    'hint' => 'Sandbox mode sends texts to you: add a mobile or office phone on your staff user profile.',
                ];
            }

            return ['offered' => true, 'hint' => null];
        }

        if ($customer === null) {
            return ['offered' => false, 'hint' => 'This estimate has no customer to text.'];
        }

        $raw = $customer->mobile ?? $customer->phone ?? null;
        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return [
                'offered' => false,
                'hint' => 'Add a mobile or phone number on the customer record to send SMS.',
            ];
        }

        return ['offered' => true, 'hint' => null];
    }

    /**
     * Whether the UI may offer “email + SMS” for service ticket approval sends.
     *
     * @return array{offered: bool, hint: ?string}
     */
    public function serviceTicketApprovalSmsCanBeOffered(?Customer $customer, WebUser|TenantUser|null $authUser): array
    {
        if (! $this->tenantWantsSms(SMS::ServiceTicket)) {
            return ['offered' => false, 'hint' => null];
        }

        if ($authUser === null) {
            return ['offered' => false, 'hint' => 'Sign in to send SMS.'];
        }

        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            if ($tenantStaff === null) {
                return [
                    'offered' => false,
                    'hint' => 'No staff user in this tenant matches your login email; create or link your staff profile to send sandbox SMS.',
                ];
            }

            $to = $this->normalizePhoneForSms($tenantStaff->mobile_phone ?? $tenantStaff->office_phone ?? null);
            if ($to === null) {
                return [
                    'offered' => false,
                    'hint' => 'Sandbox mode sends texts to you: add a mobile or office phone on your staff user profile.',
                ];
            }

            return ['offered' => true, 'hint' => null];
        }

        if ($customer === null) {
            return ['offered' => false, 'hint' => 'This service ticket has no customer to text.'];
        }

        $raw = $customer->mobile ?? $customer->phone ?? null;
        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return [
                'offered' => false,
                'hint' => 'Add a mobile or phone number on the customer record to send SMS.',
            ];
        }

        return ['offered' => true, 'hint' => null];
    }

    /**
     * Whether the UI may offer “email + SMS” when sending an invoice to the customer.
     *
     * @return array{offered: bool, hint: ?string}
     */
    public function invoiceViewSmsCanBeOffered(?Contact $contact, WebUser|TenantUser|null $authUser): array
    {
        if (! $this->tenantWantsSms(SMS::Invoice)) {
            return ['offered' => false, 'hint' => null];
        }

        if ($authUser === null) {
            return ['offered' => false, 'hint' => 'Sign in to send SMS.'];
        }

        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            if ($tenantStaff === null) {
                return [
                    'offered' => false,
                    'hint' => 'No staff user in this tenant matches your login email; create or link your staff profile to send sandbox SMS.',
                ];
            }

            $to = $this->normalizePhoneForSms($tenantStaff->mobile_phone ?? $tenantStaff->office_phone ?? null);
            if ($to === null) {
                return [
                    'offered' => false,
                    'hint' => 'Sandbox mode sends texts to you: add a mobile or office phone on your staff user profile.',
                ];
            }

            return ['offered' => true, 'hint' => null];
        }

        if ($contact === null) {
            return ['offered' => false, 'hint' => 'This invoice has no contact to text.'];
        }

        $raw = $contact->mobile ?? $contact->phone ?? null;
        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return [
                'offered' => false,
                'hint' => 'Add a mobile or phone number on the contact record to send SMS.',
            ];
        }

        return ['offered' => true, 'hint' => null];
    }

    /**
     * Send a short SMS with the public invoice view link.
     */
    public function sendInvoiceViewSms(WebUser|TenantUser $authUser, ?Contact $contact, Invoice $invoice, string $viewUrl): SmsResult
    {
        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            $raw = $tenantStaff?->mobile_phone ?? $tenantStaff?->office_phone ?? null;
        } else {
            $raw = $contact?->mobile ?? $contact?->phone ?? null;
        }

        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return new SmsResult(success: false, status: 'invalid', error: 'No valid phone number for SMS.');
        }

        $label = $invoice->display_name ?? 'Invoice';
        $message = "Your invoice {$label} is ready to view: {$viewUrl}";
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477).'…';
        }

        $from = config('sms.providers.twilio.phone_number');

        return SmsProviderFactory::make()->send($to, $message, $from ?: null);
    }

    /**
     * Whether the UI may offer an SMS when marking a delivery en route.
     *
     * @return array{offered: bool, hint: ?string}
     */
    public function deliveryEnRouteSmsCanBeOffered(?Customer $customer, WebUser|TenantUser|null $authUser): array
    {
        if (! $this->tenantWantsSms(SMS::Delivery)) {
            return ['offered' => false, 'hint' => null];
        }

        if ($authUser === null) {
            return ['offered' => false, 'hint' => 'Sign in to send SMS.'];
        }

        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            if ($tenantStaff === null) {
                return [
                    'offered' => false,
                    'hint' => 'No staff user in this tenant matches your login email; create or link your staff profile to send sandbox SMS.',
                ];
            }

            $to = $this->normalizePhoneForSms($tenantStaff->mobile_phone ?? $tenantStaff->office_phone ?? null);
            if ($to === null) {
                return [
                    'offered' => false,
                    'hint' => 'Sandbox mode sends texts to you: add a mobile or office phone on your staff user profile.',
                ];
            }

            return ['offered' => true, 'hint' => null];
        }

        if ($customer === null) {
            return ['offered' => false, 'hint' => 'This delivery has no customer to text.'];
        }

        $raw = $customer->mobile ?? $customer->phone ?? null;
        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return [
                'offered' => false,
                'hint' => 'Add a mobile or phone number on the customer record to send SMS.',
            ];
        }

        return ['offered' => true, 'hint' => null];
    }

    /**
     * Send a short SMS that the driver is en route (public delivery review / tracking link).
     */
    public function sendDeliveryEnRouteSms(WebUser|TenantUser $authUser, ?Customer $customer, Delivery $delivery, string $trackUrl): SmsResult
    {
        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            $raw = $tenantStaff?->mobile_phone ?? $tenantStaff?->office_phone ?? null;
        } else {
            $raw = $customer?->mobile ?? $customer?->phone ?? null;
        }

        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return new SmsResult(success: false, status: 'invalid', error: 'No valid phone number for SMS.');
        }

        $label = $delivery->display_name ?? 'Delivery';
        $message = "Your delivery {$label} is on the way. Track: {$trackUrl}";
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477).'…';
        }

        $from = config('sms.providers.twilio.phone_number');

        return SmsProviderFactory::make()->send($to, $message, $from ?: null);
    }

    /**
     * Send a short SMS that the assigned technician has arrived at the delivery.
     *
     * @param  string  $technicianDisplayName  From {@see Delivery::technician} (not necessarily the logged-in user).
     */
    public function sendDeliveryArrivedSms(
        WebUser|TenantUser $authUser,
        ?Customer $customer,
        Delivery $delivery,
        string $technicianDisplayName,
    ): SmsResult {
        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            $raw = $tenantStaff?->mobile_phone ?? $tenantStaff?->office_phone ?? null;
        } else {
            $raw = $customer?->mobile ?? $customer?->phone ?? null;
        }

        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return new SmsResult(success: false, status: 'invalid', error: 'No valid phone number for SMS.');
        }

        $label = $delivery->display_name ?? 'Delivery';
        $message = "{$technicianDisplayName} has arrived for your delivery {$label}.";
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477).'…';
        }

        $from = config('sms.providers.twilio.phone_number');

        return SmsProviderFactory::make()->send($to, $message, $from ?: null);
    }

    /**
     * Whether the UI may offer an SMS when notifying the customer the technician has arrived.
     *
     * @return array{offered: bool, hint: ?string}
     */
    public function deliveryArrivedSmsCanBeOffered(?Customer $customer, WebUser|TenantUser|null $authUser): array
    {
        return $this->deliveryEnRouteSmsCanBeOffered($customer, $authUser);
    }

    /**
     * Whether the UI may offer SMS when sending a delivery signature / review link.
     *
     * @return array{offered: bool, hint: ?string}
     */
    public function deliverySignatureRequestSmsCanBeOffered(?Customer $customer, WebUser|TenantUser|null $authUser): array
    {
        return $this->deliveryEnRouteSmsCanBeOffered($customer, $authUser);
    }

    /**
     * Send a short SMS with the public delivery review / signature link.
     */
    public function sendDeliverySignatureRequestSms(
        WebUser|TenantUser $authUser,
        ?Customer $customer,
        Delivery $delivery,
        string $reviewUrl,
    ): SmsResult {
        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            $raw = $tenantStaff?->mobile_phone ?? $tenantStaff?->office_phone ?? null;
        } else {
            $raw = $customer?->mobile ?? $customer?->phone ?? null;
        }

        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return new SmsResult(success: false, status: 'invalid', error: 'No valid phone number for SMS.');
        }

        $label = $delivery->display_name ?? 'Delivery';
        $message = "Your delivery {$label} is ready to review and sign: {$reviewUrl}";
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477).'…';
        }

        $from = config('sms.providers.twilio.phone_number');

        return SmsProviderFactory::make()->send($to, $message, $from ?: null);
    }

    /**
     * Send a short SMS with the public estimate review link.
     */
    public function sendEstimateApprovalSms(WebUser|TenantUser $authUser, ?Customer $customer, Estimate $estimate, string $reviewUrl): SmsResult
    {
        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            $raw = $tenantStaff?->mobile_phone ?? $tenantStaff?->office_phone ?? null;
        } else {
            $raw = $customer?->mobile ?? $customer?->phone ?? null;
        }

        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return new SmsResult(success: false, status: 'invalid', error: 'No valid phone number for SMS.');
        }

        $label = $estimate->display_name ?? 'Estimate';
        $message = "Your estimate {$label} is ready to review: {$reviewUrl}";
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477).'…';
        }

        $from = config('sms.providers.twilio.phone_number');

        return SmsProviderFactory::make()->send($to, $message, $from ?: null);
    }

    /**
     * Send a short SMS with the public service ticket review link.
     */
    public function sendServiceTicketApprovalSms(WebUser|TenantUser $authUser, ?Customer $customer, ServiceTicket $serviceTicket, string $reviewUrl): SmsResult
    {
        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            $raw = $tenantStaff?->mobile_phone ?? $tenantStaff?->office_phone ?? null;
        } else {
            $raw = $customer?->mobile ?? $customer?->phone ?? null;
        }

        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return new SmsResult(success: false, status: 'invalid', error: 'No valid phone number for SMS.');
        }

        $number = $serviceTicket->service_ticket_number ?? $serviceTicket->id;
        $message = "Service ticket #{$number} is ready to review: {$reviewUrl}";
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477).'…';
        }

        $from = config('sms.providers.twilio.phone_number');

        return SmsProviderFactory::make()->send($to, $message, $from ?: null);
    }

    /**
     * Whether the UI may offer “email + SMS” when sending a contract for review/signature.
     *
     * @param  WebUser|TenantUser|null  $authUser  Session user is usually {@see WebUser}; phones live on the tenant {@see TenantUser} row matched by email.
     * @return array{offered: bool, hint: ?string}
     */
    public function contractReviewSmsCanBeOffered(?Customer $customer, WebUser|TenantUser|null $authUser): array
    {
        if (! $this->tenantWantsSms(SMS::Contract)) {
            return ['offered' => false, 'hint' => null];
        }

        if ($authUser === null) {
            return ['offered' => false, 'hint' => 'Sign in to send SMS.'];
        }

        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            if ($tenantStaff === null) {
                return [
                    'offered' => false,
                    'hint' => 'No staff user in this tenant matches your login email; create or link your staff profile to send sandbox SMS.',
                ];
            }

            $to = $this->normalizePhoneForSms($tenantStaff->mobile_phone ?? $tenantStaff->office_phone ?? null);
            if ($to === null) {
                return [
                    'offered' => false,
                    'hint' => 'Sandbox mode sends texts to you: add a mobile or office phone on your staff user profile.',
                ];
            }

            return ['offered' => true, 'hint' => null];
        }

        if ($customer === null) {
            return ['offered' => false, 'hint' => 'This contract has no customer to text.'];
        }

        $raw = $customer->mobile ?? $customer->phone ?? null;
        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return [
                'offered' => false,
                'hint' => 'Add a mobile or phone number on the customer record to send SMS.',
            ];
        }

        return ['offered' => true, 'hint' => null];
    }

    /**
     * Send a short SMS with the public contract review / sign link.
     */
    public function sendContractReviewSms(WebUser|TenantUser $authUser, ?Customer $customer, string $contractLabel, string $reviewUrl): SmsResult
    {
        $tenantStaff = $this->resolveTenantStaffForSms($authUser);

        if ($this->smsSandboxMode()) {
            $raw = $tenantStaff?->mobile_phone ?? $tenantStaff?->office_phone ?? null;
        } else {
            $raw = $customer?->mobile ?? $customer?->phone ?? null;
        }

        $to = $this->normalizePhoneForSms($raw);
        if ($to === null) {
            return new SmsResult(success: false, status: 'invalid', error: 'No valid phone number for SMS.');
        }

        $label = trim($contractLabel) !== '' ? $contractLabel : 'Contract';
        $message = "Your contract {$label} is ready to review and sign: {$reviewUrl}";
        if (strlen($message) > 480) {
            $message = substr($message, 0, 477).'…';
        }

        $from = config('sms.providers.twilio.phone_number');

        return SmsProviderFactory::make()->send($to, $message, $from ?: null);
    }

    private function normalizePhoneForSms(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $trim = trim($raw);
        $digits = preg_replace('/\D+/', '', $trim) ?? '';

        if ($digits === '' || strlen($digits) < 10) {
            return null;
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '1')) {
            return '+'.$digits;
        }

        if (strlen($digits) === 10) {
            return '+1'.$digits;
        }

        if (str_starts_with($trim, '+') && strlen($digits) >= 10) {
            return '+'.$digits;
        }

        return null;
    }

    /**
     * Phones for sandbox SMS live on the tenant `users` row ({@see TenantUser}), not the central web account.
     */
    private function resolveTenantStaffForSms(WebUser|TenantUser $auth): ?TenantUser
    {
        if ($auth instanceof TenantUser) {
            return $auth;
        }

        return TenantStaffResolver::tenantStaffForWebUser($auth);
    }
}
