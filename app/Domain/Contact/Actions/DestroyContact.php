<?php

namespace Domain\Contact\Actions;

use Domain\Contact\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;
use Throwable;

class DestroyContact
{
    /**
     * Handle the action.
     *
     * @param  int  $id
     * @return array
     */
    public function __invoke(int $id): array
    {
        try {
            $contact = Contact::findOrFail($id);
            $contact->delete();

            return [
                'success' => true,
                'message' => 'Record deleted successfully.',
            ];
        } catch (QueryException $e) {
            Log::error('Database query error in DestroyContact', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        } catch (Throwable $e) {
            Log::error('Unexpected error in DestroyContact', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}
