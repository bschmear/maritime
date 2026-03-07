<?php

namespace App\Domain\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function scores()
    {
        return $this->morphMany(\App\Domain\Score\Models\Score::class, 'scorable');
    }

    public function currentScores()
    {
        return $this->morphMany(\App\Domain\Score\Models\Score::class, 'scorable')->where('is_current', true);
    }
}
