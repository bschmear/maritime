<?php

namespace App\Domain\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $table = 'contacts';

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Contact $contact) {
            if (empty($contact->display_name)) {
                $name = trim(implode(' ', array_filter([$contact->first_name, $contact->last_name])));
                $contact->display_name = $name !== ''
                    ? $name
                    : ($contact->email ?: ($contact->company ?: 'Contact'));
            }
        });
    }

    public function scores()
    {
        return $this->morphMany(\App\Domain\Score\Models\Score::class, 'scorable');
    }

    public function currentScores()
    {
        return $this->morphMany(\App\Domain\Score\Models\Score::class, 'scorable')->where('is_current', true);
    }
}
