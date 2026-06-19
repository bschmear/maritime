<?php

declare(strict_types=1);

namespace App\Domain\ChartOfAccount\Models;

use App\Domain\Bill\Models\Bill;
use App\Domain\BillItem\Models\BillItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChartOfAccount extends Model
{
    protected $table = 'chart_of_accounts';

    protected $appends = ['display_name'];

    protected $fillable = [
        'name',
        'quickbooks_account_id',
        'account_type',
        'detail_type',
        'fully_qualified_name',
        'active',
        'parent_id',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'parent_id' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getDisplayNameAttribute(): string
    {
        if (filled($this->name)) {
            return (string) $this->name;
        }

        if (filled($this->fully_qualified_name)) {
            return (string) $this->fully_qualified_name;
        }

        if (filled($this->quickbooks_account_id)) {
            return 'COA-'.(string) $this->quickbooks_account_id;
        }

        return 'COA-'.($this->id ?: '???');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class, 'chart_of_account_id');
    }

    public function billItems(): HasMany
    {
        return $this->hasMany(BillItem::class, 'chart_of_account_id');
    }
}
