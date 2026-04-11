<?php

namespace App\Http\Controllers\Tenant;

use App\Domain\Contact\Models\Contact;
use App\Domain\Vendor\Actions\CreateVendor as CreateAction;
use App\Domain\Vendor\Actions\DeleteVendor as DeleteAction;
use App\Domain\Vendor\Actions\UpdateVendor as UpdateAction;
use App\Domain\Vendor\Models\Vendor;
use App\Domain\Vendor\Models\Vendor as RecordModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
}
