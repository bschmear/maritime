<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            if (! Schema::hasColumn('locations', 'delivery_approver_user_id')) {
                $table->foreignId('delivery_approver_user_id')
                    ->nullable()
                    ->after('manager_user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });

        Schema::table('deliveries', function (Blueprint $table) {
            $table->foreignId('requested_by_user_id')->nullable()->after('technician_id')->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable()->after('requested_by_user_id');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('requested_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->string('review_decision', 32)->nullable()->after('reviewed_at');
            $table->text('review_notes')->nullable()->after('review_decision');
            $table->timestamp('proposed_scheduled_at')->nullable()->after('review_notes');
        });

        DB::table('deliveries')->where('status', 'confirmed')->update(['status' => 'scheduled']);
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by_user_id');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn([
                'requested_at',
                'reviewed_at',
                'review_decision',
                'review_notes',
                'proposed_scheduled_at',
            ]);
        });

        Schema::table('locations', function (Blueprint $table) {
            if (Schema::hasColumn('locations', 'delivery_approver_user_id')) {
                $table->dropConstrainedForeignId('delivery_approver_user_id');
            }
        });
    }
};
