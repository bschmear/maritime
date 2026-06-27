<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lead_profiles')) {
            Schema::table('lead_profiles', function (Blueprint $table) {
                $table->index(['converted', 'next_followup_at'], 'lead_profiles_converted_followup_idx');
                $table->index(['converted', 'status_id'], 'lead_profiles_converted_status_idx');
            });
        }

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->index(['completed', 'due_date'], 'tasks_completed_due_date_idx');
            });
        }

        if (Schema::hasTable('opportunities')) {
            Schema::table('opportunities', function (Blueprint $table) {
                $table->index(['won_at', 'lost_at', 'updated_at'], 'opportunities_pipeline_updated_idx');
                $table->index(['stage', 'status'], 'opportunities_stage_status_idx');
            });
        }

        if (Schema::hasTable('service_tickets')) {
            Schema::table('service_tickets', function (Blueprint $table) {
                $table->index(['status', 'updated_at'], 'service_tickets_status_updated_idx');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->index(['status', 'due_at'], 'invoices_status_due_at_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lead_profiles')) {
            Schema::table('lead_profiles', function (Blueprint $table) {
                $table->dropIndex('lead_profiles_converted_followup_idx');
                $table->dropIndex('lead_profiles_converted_status_idx');
            });
        }

        if (Schema::hasTable('tasks')) {
            Schema::table('tasks', function (Blueprint $table) {
                $table->dropIndex('tasks_completed_due_date_idx');
            });
        }

        if (Schema::hasTable('opportunities')) {
            Schema::table('opportunities', function (Blueprint $table) {
                $table->dropIndex('opportunities_pipeline_updated_idx');
                $table->dropIndex('opportunities_stage_status_idx');
            });
        }

        if (Schema::hasTable('service_tickets')) {
            Schema::table('service_tickets', function (Blueprint $table) {
                $table->dropIndex('service_tickets_status_updated_idx');
            });
        }

        if (Schema::hasTable('invoices')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropIndex('invoices_status_due_at_idx');
            });
        }
    }
};
