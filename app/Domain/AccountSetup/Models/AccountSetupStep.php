<?php

declare(strict_types=1);

namespace App\Domain\AccountSetup\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountSetupStep extends Model
{
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'route_params' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasOne<AccountSetupStepProgress, $this>
     */
    public function progress(): HasOne
    {
        return $this->hasOne(AccountSetupStepProgress::class);
    }
}
