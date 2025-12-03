<?php

namespace Domain\Contact\Actions;

use Domain\Contact\Models\Contact;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CreateContact
{
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
