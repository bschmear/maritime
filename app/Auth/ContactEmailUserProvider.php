<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Hashing\Hasher as HasherContract;

/**
 * Resolves portal customers by contacts.email (canonical login) while the model persists on customer_profiles.
 */
class ContactEmailUserProvider extends EloquentUserProvider
{
    public function __construct(HasherContract $hasher, $model)
    {
        parent::__construct($hasher, $model);
    }

    public function retrieveByCredentials(array $credentials)
    {
        if ($credentials === []) {
            return null;
        }

        $query = $this->createModel()->newQuery();

        foreach ($credentials as $key => $value) {
            if (str_contains($key, 'password')) {
                continue;
            }

            if ($key === 'email') {
                $query->whereHas('contact', function ($q) use ($value) {
                    $q->where('email', $value);
                });

                continue;
            }

            if (is_array($value) || $value instanceof \Illuminate\Contracts\Support\Arrayable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }
}
