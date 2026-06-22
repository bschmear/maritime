<?php

declare(strict_types=1);

namespace App\Domain\BoatMake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatMakeInvoiceImportProfile extends Model
{
    protected $table = 'boat_make_invoice_import_profiles';

    protected $fillable = [
        'boat_make_id',
        'ai_instructions',
    ];

    public function boatMake(): BelongsTo
    {
        return $this->belongsTo(BoatMake::class, 'boat_make_id');
    }
}
