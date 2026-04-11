<?php

namespace App\Domain\Contact\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeadProfile extends Model
{
    protected $table = 'lead_profiles';

    protected $fillable = [
        'contact_id',
        'lead_score',
        'lead_status',
        'qualification_stage',
        'is_qualified',
        'qualified_at',
        'assigned_user_id',
        'priority_id',
        'last_contacted_at',
        'next_followup_at',
        'contact_attempts',
        'source_id',
        'source_details',
        'referrer',
        'campaign',
        'medium',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'purchase_timeline',
        'budget_min',
        'budget_max',
        'interested_model',
        'has_trade_in',
        'trade_in_value',
        'marketing_opt_in',
        'opted_in_at',
        'notes',
        'status_id',
        'budget_range',
        'converted',
        'converted_at',
        'converted_customer_id',
        'created_by_user_id',
        'last_updated_by_user_id',
        'latest_score_id',
        'latest_score',
    ];

    protected $casts = [
        'is_qualified' => 'boolean',
        'qualified_at' => 'date',
        'last_contacted_at' => 'date',
        'next_followup_at' => 'date',
        'has_trade_in' => 'boolean',
        'marketing_opt_in' => 'boolean',
        'opted_in_at' => 'datetime',
        'converted' => 'boolean',
        'converted_at' => 'datetime',
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'trade_in_value' => 'decimal:2',
        'latest_score' => 'decimal:2',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
