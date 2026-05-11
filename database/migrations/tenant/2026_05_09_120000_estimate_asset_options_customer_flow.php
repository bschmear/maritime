<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('estimate_line_items')) {
            Schema::table('estimate_line_items', function (Blueprint $table) {
                if (! Schema::hasColumn('estimate_line_items', 'asset_options_fill_mode')) {
                    $table->string('asset_options_fill_mode', 32)->default('staff')->after('line_total');
                }
                if (! Schema::hasColumn('estimate_line_items', 'customer_asset_options_completed_at')) {
                    $table->timestamp('customer_asset_options_completed_at')->nullable()->after('asset_options_fill_mode');
                }
                if (! Schema::hasColumn('estimate_line_items', 'customer_asset_options_signer_name')) {
                    $table->string('customer_asset_options_signer_name')->nullable()->after('customer_asset_options_completed_at');
                }
                if (! Schema::hasColumn('estimate_line_items', 'customer_asset_options_signer_ip')) {
                    $table->string('customer_asset_options_signer_ip', 45)->nullable()->after('customer_asset_options_signer_name');
                }
            });
        }

        if (! Schema::hasTable('estimate_customer_option_signoffs')) {
            Schema::create('estimate_customer_option_signoffs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('estimate_id')->constrained('estimates')->cascadeOnDelete();
                $table->foreignId('estimate_line_item_id')->constrained('estimate_line_items')->cascadeOnDelete();
                $table->string('signer_name');
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('signed_at');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('estimate_customer_option_signoffs');

        if (Schema::hasTable('estimate_line_items')) {
            Schema::table('estimate_line_items', function (Blueprint $table) {
                foreach ([
                    'asset_options_fill_mode',
                    'customer_asset_options_completed_at',
                    'customer_asset_options_signer_name',
                    'customer_asset_options_signer_ip',
                ] as $col) {
                    if (Schema::hasColumn('estimate_line_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
