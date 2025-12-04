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
        // Validate only fields that have validation rules
        $validated = Validator::make($data, [
            'first_name' => ['sometimes', 'required', 'string', 'max:255'],
            'last_name'  => ['sometimes', 'required', 'string', 'max:255'],
            'email'      => ['nullable', 'email', 'max:255'],
            'phone'      => ['nullable', 'string', 'max:50'],
            'notes'      => ['nullable', 'string'],
        ])->validate();

        try {
            $contact = Contact::findOrFail($id);
            
            // Merge all data with validated fields (validated fields take precedence)
            // This ensures validated fields use their validated values, while other fields are preserved
            $fieldsToSave = array_merge($data, $validated);
            // Remove fields that shouldn't be mass-assigned
            unset($fieldsToSave['id'], $fieldsToSave['created_at'], $fieldsToSave['updated_at']);
            $fieldsToSave['display_name'] = $fieldsToSave['first_name'] . ' ' . $fieldsToSave['last_name'];
            $contact->update($fieldsToSave);

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
