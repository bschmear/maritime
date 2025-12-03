<?php

namespace App\Domains\Contacts\Actions;

use App\Models\Tenant\Contact;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Create
{
    /**
     * Handle the action.
     *
     * @param  array  $data
     * @return \App\Models\Tenant\Contact
     *
     * @throws ValidationException
     */
    public function __invoke(array $data): Contact
    {
        // Validate incoming data
        $validated = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name'  => ['required', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string'],
        ])->validate();

        // Create the contact in the tenant database
        return Contact::create($validated);
    }
}
