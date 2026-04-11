<?php

namespace App\Domain\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerProfile extends Model
{
    protected $table = 'customer_profiles';

    protected $guarded = ['id'];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
