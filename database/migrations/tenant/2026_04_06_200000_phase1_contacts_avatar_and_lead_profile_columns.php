<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('avatar')->nullable()->after('facebook');
        });

        Schema::table('lead_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('status_id')->default(1)->after('contact_id');
            $table->unsignedTinyInteger('budget_range')->default(1)->after('purchase_timeline');

            $table->boolean('converted')->default(false)->after('budget_range');
            $table->dateTime('converted_at')->nullable()->after('converted');
            $table->foreignId('converted_customer_id')->nullable()->after('converted_at')
                ->constrained('customers')->nullOnDelete();

            $table->foreignId('created_by_user_id')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            $table->foreignId('last_updated_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();

            $table->foreignId('latest_score_id')->nullable()->after('last_updated_by_user_id')
                ->constrained('scores')->nullOnDelete();
            $table->decimal('latest_score', 12, 2)->nullable()->after('latest_score_id');
        });
    }

    public function down(): void
    {
        Schema::table('lead_profiles', function (Blueprint $table) {
            $table->dropForeign(['converted_customer_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropForeign(['last_updated_by_user_id']);
            $table->dropForeign(['latest_score_id']);
            $table->dropColumn([
                'status_id',
                'budget_range',
                'converted',
                'converted_at',
                'converted_customer_id',
                'created_by_user_id',
                'last_updated_by_user_id',
                'latest_score_id',
                'latest_score',
            ]);
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('avatar');
        });
    }
};
