<?php

declare(strict_types=1);

use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOption\Models\AssetOptionAssignment;
use App\Domain\AssetOption\Models\AssetOptionMakeAssignment;
use App\Domain\BoatMake\Models\BoatMake;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_options', function (Blueprint $table) {
            if (! Schema::hasColumn('asset_options', 'is_global')) {
                $table->boolean('is_global')->default(false)->after('active');
            }
        });

        Schema::table('transaction_line_items', function (Blueprint $table) {
            if (! Schema::hasColumn('transaction_line_items', 'customer_offered_option_ids')) {
                $table->json('customer_offered_option_ids')->nullable()->after('asset_options_fill_mode');
            }
        });

        if (Schema::hasTable('asset_opportunity')) {
            Schema::table('asset_opportunity', function (Blueprint $table) {
                if (! Schema::hasColumn('asset_opportunity', 'customer_offered_option_ids')) {
                    $table->json('customer_offered_option_ids')->nullable();
                }
            });
        }

        $this->migrateAllBrandOptionsToGlobal();
    }

    public function down(): void
    {
        Schema::table('asset_options', function (Blueprint $table) {
            if (Schema::hasColumn('asset_options', 'is_global')) {
                $table->dropColumn('is_global');
            }
        });

        Schema::table('transaction_line_items', function (Blueprint $table) {
            if (Schema::hasColumn('transaction_line_items', 'customer_offered_option_ids')) {
                $table->dropColumn('customer_offered_option_ids');
            }
        });

        if (Schema::hasTable('asset_opportunity')) {
            Schema::table('asset_opportunity', function (Blueprint $table) {
                if (Schema::hasColumn('asset_opportunity', 'customer_offered_option_ids')) {
                    $table->dropColumn('customer_offered_option_ids');
                }
            });
        }
    }

    private function migrateAllBrandOptionsToGlobal(): void
    {
        if (! Schema::hasTable('asset_options') || ! Schema::hasTable('asset_option_make_assignments')) {
            return;
        }

        $activeMakeIds = BoatMake::query()
            ->where('active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($activeMakeIds === []) {
            return;
        }

        $activeMakeCount = count($activeMakeIds);

        AssetOption::query()
            ->where('is_global', false)
            ->each(function (AssetOption $option) use ($activeMakeIds, $activeMakeCount): void {
                $hasAssetAssignments = AssetOptionAssignment::query()
                    ->where('option_id', $option->id)
                    ->exists();

                if ($hasAssetAssignments) {
                    return;
                }

                $assignedMakeIds = AssetOptionMakeAssignment::query()
                    ->where('option_id', $option->id)
                    ->where('active', true)
                    ->pluck('make_id')
                    ->map(fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                if (count($assignedMakeIds) !== $activeMakeCount) {
                    return;
                }

                $assignedSet = array_flip($assignedMakeIds);
                foreach ($activeMakeIds as $makeId) {
                    if (! isset($assignedSet[$makeId])) {
                        return;
                    }
                }

                $option->update(['is_global' => true]);

                AssetOptionMakeAssignment::query()
                    ->where('option_id', $option->id)
                    ->delete();
            });
    }
};
