<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Domain\Contact\Models\Contact;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class VerifyVendorEmailController extends Controller
{
    public function __invoke(Request $request, int $id, string $hash): RedirectResponse
    {
        $contact = Contact::query()->findOrFail($id);

        if (! hash_equals((string) $hash, sha1($contact->getEmailForVerification()))) {
            abort(403);
        }

        if (! $contact->vendors()->exists()) {
            abort(403);
        }

        if (! $request->hasValidSignature()) {
            abort(403);
        }

        if ($contact->email_verified_at === null) {
            $contact->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::guard('vendor')->login($contact);
        $request->session()->regenerate();

        return redirect()
            ->route('vendor.portal.index')
            ->with('success', 'Your email has been verified.');
    }
}
