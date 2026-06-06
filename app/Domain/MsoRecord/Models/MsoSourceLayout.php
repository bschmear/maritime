<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Models;

use App\Domain\Document\Models\Document;
use App\Domain\User\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MsoSourceLayout extends Model
{
    protected $table = 'mso_source_layouts';

    protected $fillable = [
        'source_document_id',
        'layout',
        'created_by_id',
    ];

    protected $casts = [
        'layout' => 'array',
    ];

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
