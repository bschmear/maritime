<?php

namespace App\Domain\Document\Models;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'documents';
    protected $guarded = ['id', 'created_at', 'updated_at'];
    protected $fillable = [
        'display_name',
        'description',
        'file',
        'user_id',
        'assigned_id',
        'user_name',
        'file_extension',
        'team_id',
        'file_size',
        'extracted_text',
        'ai_summary',
        'key_points',
        'ai_status',
        'ai_processed_at'
    ];

    protected $casts = [
        'key_points' => 'array',
        'ai_processed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
    }

    public function created_by()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'created_by_id');
    }

    public function updated_by()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'updated_by_id');
    }

    public function assigned()
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'assigned_id');
    }

    public function documentables()
    {
        return $this->morphTo();
    }

    public function contacts()
    {
        return $this->morphedByMany(\App\Domain\Contact\Models\Contact::class, 'documentable')->withTimestamps();
    }

    public function transactions()
    {
        return $this->morphedByMany(\App\Domain\Transaction\Models\Transaction::class, 'documentable')->withTimestamps();
    }

    public function vendors()
    {
        return $this->morphedByMany(\App\Domain\Vendor\Models\Vendor::class, 'documentable')->withTimestamps();
    }

    public function leads()
    {
        return $this->morphedByMany(\App\Domain\Lead\Models\Lead::class, 'documentable')->withTimestamps();
    }

    /**
     * Get parsed key points as array.
     */
    public function getKeyPointsArrayAttribute(): ?array
    {
        if (!$this->key_points) {
            return null;
        }

        $decoded = json_decode($this->key_points, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Check if document has been analyzed.
     */
    public function hasAnalysis(): bool
    {
        return $this->ai_status === 'completed' && !empty($this->ai_summary);
    }
}
