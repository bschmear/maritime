<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Vendor\Actions\CreateVendor as CreateAction;
use App\Domain\Vendor\Actions\DeleteVendor as DeleteAction;
use App\Domain\Vendor\Actions\UpdateVendor as UpdateAction;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\Vendor\Models\Vendor as RecordModel;
use App\Mail\VendorPortalLink;
use App\Models\AccountSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redirect;

class VendorController extends RecordController
{
    protected $recordType = 'Vendor';

    protected $table = null;

    public function __construct(Request $request)
    {
        parent::__construct(
            $request,
            'vendors',
            'Vendor',
            new RecordModel,
            new CreateAction,
            new UpdateAction,
            new DeleteAction,
            $this->recordType // Domain name for schema lookup
        );
    }

    protected function inertiaUpdateSuccessRedirect(Request $request, int|string $id): RedirectResponse
    {
        return Redirect::route('vendors.show', $id)
            ->with('success', 'Vendor updated successfully');
    }

    public function setPrimaryContact(Request $request, int $vendor): RedirectResponse
    {
        $validated = $request->validate([
            'contact_id' => ['required', 'integer', 'exists:contacts,id'],
        ]);

        $contact = Contact::query()->findOrFail($validated['contact_id']);

        $vendorModel = Vendor::query()->findOrFail($vendor);

        if (! $contact->vendors()->whereKey($vendor)->exists()) {
            abort(422, 'Contact is not linked to this vendor. Attach the contact first.');
        }

        $vendorModel->update([
            'primary_contact_id' => $contact->id,
        ]);

        $vendorModel->refresh()->syncPrimaryContactPivot();

        return back();
    }

    public function updateContactPortalAccess(Request $request, int $vendor, int $contact): JsonResponse
    {
        $validated = $request->validate([
            'portal_access' => ['required', 'boolean'],
        ]);

        $vendorModel = Vendor::query()->findOrFail($vendor);

        if (! $vendorModel->linkedContacts()->whereKey($contact)->exists()) {
            abort(422, 'Contact is not linked to this vendor.');
        }

        $vendorModel->linkedContacts()->updateExistingPivot($contact, [
            'portal_access' => $validated['portal_access'],
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Email the vendor's primary contact links to the manufacturer (vendor) portal on this tenant.
     */
    public function sendVendorPortalLink(Request $request, int $vendor): RedirectResponse
    {
        $vendorModel = Vendor::query()->with('primaryContact')->findOrFail($vendor);

        $contact = $vendorModel->primaryContact;
        if ($contact === null) {
            return back()->with('error', 'This vendor has no primary contact.');
        }

        $email = trim((string) ($contact->email ?? ''));
        if ($email === '') {
            return back()->with('error', 'The primary contact does not have an email address.');
        }

        if (! $contact->vendors()->whereKey($vendorModel->getKey())->exists()) {
            return back()->with('error', 'The primary contact is not linked to this vendor. Re-link the contact or choose another primary.');
        }

        $this->sendVendorPortalLinkMailable($contact, $email);

        return back()->with('success', 'Manufacturer portal links sent to '.$email.'.');
    }

    /**
     * Email manufacturer portal links to a linked vendor contact.
     */
    public function sendVendorPortalLinkToContact(Request $request, int $vendor, int $contact): RedirectResponse
    {
        $vendorModel = Vendor::query()->findOrFail($vendor);
        $contactModel = Contact::query()->findOrFail($contact);

        if (! $contactModel->vendors()->whereKey($vendorModel->getKey())->exists()) {
            return back()->with('error', 'That contact is not linked to this vendor.');
        }

        $email = trim((string) ($contactModel->email ?? ''));
        if ($email === '') {
            return back()->with('error', 'That contact has no email address.');
        }

        $this->sendVendorPortalLinkMailable($contactModel, $email);

        return back()->with('success', 'Manufacturer portal links sent to '.$email.'.');
    }

    private function sendVendorPortalLinkMailable(Contact $contact, string $email): void
    {
        [$loginUrl, $registerUrl] = $this->tenantVendorPortalLoginAndRegisterUrls();

        Mail::to($email)->send(new VendorPortalLink(
            $contact,
            AccountSettings::getCurrent(),
            $loginUrl,
            $registerUrl,
        ));
    }

    /**
     * @return array{0: string, 1: string} Login URL, register URL on the tenant host.
     */
    private function tenantVendorPortalLoginAndRegisterUrls(): array
    {
        $tenant = tenant();
        $domain = $tenant?->domains->first()?->domain;
        $root = $domain ? 'https://'.$domain : rtrim((string) config('app.url'), '/');

        return [
            $root.'/vendor/portal/login',
            $root.'/vendor/portal/register',
        ];
    }
}
