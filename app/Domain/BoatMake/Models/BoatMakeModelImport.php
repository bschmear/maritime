<?php

declare(strict_types=1);

namespace App\Domain\BoatMake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoatMakeModelImport extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_SKIPPED = 'skipped';

    public const STATUS_FAILED = 'failed';

    protected $table = 'boat_make_model_imports';

    protected $fillable = [
        'boat_make_id',
        'model_slug',
        'model_label',
        'status',
        'catalog_asset_key',
        'error_message',
    ];

    public function boatMake(): BelongsTo
    {
        return $this->belongsTo(BoatMake::class, 'boat_make_id');
    }
}
