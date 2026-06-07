<?php

declare(strict_types=1);

namespace App\Domain\AccountSetup\Models;

use App\Domain\User\Models\User;
use App\Enums\AccountSetupStepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountSetupStepProgress extends Model
{
    protected $table = 'account_setup_step_progress';

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
            'status' => AccountSetupStepStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AccountSetupStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(AccountSetupStep::class, 'account_setup_step_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_id');
    }
}
