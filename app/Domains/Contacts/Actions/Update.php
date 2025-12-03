<?php

namespace App\Domains\Contacts\Actions;

use App\Models\Tenant\Contact;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Update
{
    /**
     * Handle the action.
     *
     * @param  \App\Models\Tenant\Contact  $contact
     * @param  array  $data
     * @return \App\Models\Tenant\Contact
     *
     * @throws ValidationException
     */
    public function __invoke(Contact $contact, array $data): Contact
    {
        // Validate incoming data
        $validated = Validator::make($data, [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string'],
        ])->validate();

        // Update the contact in the tenant database
        $contact->update($validated);

        return $contact;
    }
}
