<?php

namespace App\Domains\Contacts\Actions;

use App\Models\Tenant\Contact;

class Destroy
{
    /**
     * Handle the action.
     *
     * @param  \App\Models\Tenant\Contact  $contact
     * @return bool|null
     */
    public function __invoke(Contact $contact): ?bool
    {
        return $contact->delete();
    }
}
