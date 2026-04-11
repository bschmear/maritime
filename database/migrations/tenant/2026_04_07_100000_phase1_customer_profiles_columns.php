<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->string('password')->nullable()->after('contact_id');
            $table->timestamp('email_verified_at')->nullable()->after('password');
            $table->rememberToken();

            $table->unsignedTinyInteger('status_id')->default(1)->after('tier');
            $table->unsignedTinyInteger('priority_id')->nullable()->after('status_id');

            $table->unsignedSmallInteger('lead_score')->nullable()->after('assigned_user_id');
            $table->string('campaign')->nullable()->after('lead_score');
            $table->string('medium')->nullable()->after('campaign');
            $table->string('source_details')->nullable()->after('medium');

            $table->date('last_contacted_at')->nullable()->after('source_details');
            $table->date('next_followup_at')->nullable()->after('last_contacted_at');

            $table->string('purchase_timeline')->nullable()->after('next_followup_at');
            $table->decimal('budget_min', 12, 2)->nullable()->after('purchase_timeline');
            $table->decimal('budget_max', 12, 2)->nullable()->after('budget_min');
            $table->string('interested_model')->nullable()->after('budget_max');
            $table->boolean('has_trade_in')->default(false)->after('interested_model');
            $table->decimal('trade_in_value', 12, 2)->nullable()->after('has_trade_in');
            $table->boolean('marketing_opt_in')->default(false)->after('trade_in_value');

            $table->string('utm_source')->nullable()->after('marketing_opt_in');
            $table->string('utm_medium')->nullable()->after('utm_source');
            $table->string('utm_campaign')->nullable()->after('utm_medium');
            $table->string('utm_term')->nullable()->after('utm_campaign');
            $table->string('utm_content')->nullable()->after('utm_term');

            $table->string('website')->nullable()->after('utm_content');
            $table->string('linkedin')->nullable()->after('website');
            $table->string('facebook')->nullable()->after('linkedin');

            $table->foreignId('created_by_user_id')->nullable()->after('facebook')->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();

            $table->foreignId('latest_score_id')->nullable()->after('last_updated_by_user_id')
                ->constrained('scores')->nullOnDelete();
            $table->decimal('latest_score', 12, 2)->nullable()->after('latest_score_id');
        });

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->foreign('converted_from_lead_id')
                ->references('id')
                ->on('lead_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropForeign(['converted_from_lead_id']);
        });

        Schema::table('customer_profiles', function (Blueprint $table) {
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['last_updated_by_user_id']);
            $table->dropForeign(['latest_score_id']);
            $table->dropColumn([
                'password',
                'email_verified_at',
                'remember_token',
                'status_id',
                'priority_id',
                'lead_score',
                'campaign',
                'medium',
                'source_details',
                'last_contacted_at',
                'next_followup_at',
                'purchase_timeline',
                'budget_min',
                'budget_max',
                'interested_model',
                'has_trade_in',
                'trade_in_value',
                'marketing_opt_in',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'utm_term',
                'utm_content',
                'website',
                'linkedin',
                'facebook',
                'created_by_user_id',
                'last_updated_by_user_id',
                'latest_score_id',
                'latest_score',
            ]);
        });
    }
};
