<?php

namespace Domain\Contact\Actions;

use Domain\Contact\Models\Contact;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UpdateContact
{
    /**
     * Handle the action.
     *
     * @param  int  $id
     * @param  array  $data
     * @return array
     *
     * @throws ValidationException
     */
    public function __invoke(int $id, array $data): array
    {
        // Validate incoming data
        $validated = Validator::make($data, [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string'],
        ])->validate();

        try {
            $contact = Contact::findOrFail($id);
            $contact->update($validated);

            return [
                'success' => true,
                'record' => $contact,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'record' => null,
            ];
        }
    }
}
