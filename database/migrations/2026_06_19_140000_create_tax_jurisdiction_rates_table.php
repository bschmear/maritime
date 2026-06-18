<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('fl_tax_zip_counties');
        Schema::dropIfExists('fl_tax_counties');
        Schema::dropIfExists('tax_zip_counties');
        Schema::dropIfExists('tax_county_rates');

        Schema::create('tax_jurisdiction_rates', function (Blueprint $table) {
            $table->id();
            $table->string('country_code', 2)->default('US');
            $table->string('state_code', 2);
            $table->string('postal_code', 10);
            $table->string('city', 128)->nullable();
            $table->string('county_name', 128)->nullable();
            $table->string('jurisdiction_code', 16)->nullable();
            $table->string('jurisdiction_label', 255)->nullable();
            $table->decimal('state_rate_percent', 6, 4)->default(0);
            $table->decimal('local_rate_percent', 6, 4)->default(0);
            $table->decimal('total_rate_percent', 6, 4);
            $table->string('source', 32)->default('ai');
            $table->timestamp('fetched_at');
            $table->timestamps();

            $table->unique(['country_code', 'state_code', 'postal_code']);
            $table->index(['state_code', 'county_name']);
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_jurisdiction_rates');
    }
};
