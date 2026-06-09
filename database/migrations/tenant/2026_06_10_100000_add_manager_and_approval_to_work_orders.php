<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('manager_user_id')
                ->nullable()
                ->after('is_technician')
                ->constrained('users')
                ->nullOnDelete();
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->boolean('requires_manager_approval')->default(false)->after('warranty_closed');
            $table->foreignId('manager_user_id')->nullable()->after('requires_manager_approval')->constrained('users')->nullOnDelete();
            $table->timestamp('technician_submitted_at')->nullable()->after('manager_user_id');
            $table->foreignId('technician_submitted_by')->nullable()->after('technician_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('manager_signed_off_at')->nullable()->after('technician_submitted_by');
            $table->foreignId('manager_signed_off_by')->nullable()->after('manager_signed_off_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('checklist_items', function (Blueprint $table) {
            $table->string('response', 10)->nullable()->after('completed');
            $table->boolean('manager_approved')->default(false)->after('response');
            $table->timestamp('manager_approved_at')->nullable()->after('manager_approved');
            $table->unsignedBigInteger('manager_approved_by')->nullable()->after('manager_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_items', function (Blueprint $table) {
            $table->dropColumn(['response', 'manager_approved', 'manager_approved_at', 'manager_approved_by']);
        });

        Schema::table('work_orders', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
            $table->dropForeign(['technician_submitted_by']);
            $table->dropForeign(['manager_signed_off_by']);
            $table->dropColumn([
                'requires_manager_approval',
                'manager_user_id',
                'technician_submitted_at',
                'technician_submitted_by',
                'manager_signed_off_at',
                'manager_signed_off_by',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['manager_user_id']);
            $table->dropColumn('manager_user_id');
        });
    }
};
