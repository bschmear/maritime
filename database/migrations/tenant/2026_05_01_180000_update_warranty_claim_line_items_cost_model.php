<?php

use App\Enums\WarrantyClaim\LineItemCostType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warranty_claim_line_items', function (Blueprint $table) {
            if (! Schema::hasColumn('warranty_claim_line_items', 'cost_type')) {
                $table->string('cost_type', 32)
                    ->default(LineItemCostType::Quantity->value)
                    ->after('description');
            }
            if (! Schema::hasColumn('warranty_claim_line_items', 'notes')) {
                $table->text('notes')->nullable()->after('cost');
            }
        });

        if (Schema::hasColumn('warranty_claim_line_items', 'price')) {
            DB::statement('UPDATE warranty_claim_line_items SET cost = COALESCE(cost, price, 0)');
            DB::table('warranty_claim_line_items')->whereNull('cost')->update(['cost' => 0]);
            DB::table('warranty_claim_line_items')->whereNull('cost_type')->update([
                'cost_type' => LineItemCostType::Quantity->value,
            ]);

            Schema::table('warranty_claim_line_items', function (Blueprint $table) {
                $table->dropColumn('price');
            });
        }
    }

    public function down(): void
    {
        Schema::table('warranty_claim_line_items', function (Blueprint $table) {
            if (! Schema::hasColumn('warranty_claim_line_items', 'price')) {
                $table->decimal('price', 10, 2)->default(0)->after('quantity');
            }
        });

        DB::table('warranty_claim_line_items')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $type = LineItemCostType::tryFrom((string) ($row->cost_type ?? '')) ?? LineItemCostType::Quantity;
                $qty = max(1, (int) ($row->quantity ?? 1));
                $cost = (float) ($row->cost ?? 0);
                $lineTotal = $type->lineTotal($qty, $cost);
                $unitPrice = $qty > 0 ? round($lineTotal / $qty, 2) : $lineTotal;
                DB::table('warranty_claim_line_items')->where('id', $row->id)->update([
                    'price' => $unitPrice,
                ]);
            }
        });

        Schema::table('warranty_claim_line_items', function (Blueprint $table) {
            if (Schema::hasColumn('warranty_claim_line_items', 'cost_type')) {
                $table->dropColumn('cost_type');
            }
            if (Schema::hasColumn('warranty_claim_line_items', 'notes')) {
                $table->dropColumn('notes');
            }
        });
    }
};
