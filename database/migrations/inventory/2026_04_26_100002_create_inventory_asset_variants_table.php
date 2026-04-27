<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('asset_variants')) {
            return;
        }

        Schema::connection($this->connection)->create('asset_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('display_name')->nullable();
            $table->string('key')->nullable();
            $table->boolean('inactive')->default(false);
            $table->decimal('default_cost', 12, 2)->nullable();
            $table->decimal('default_price', 12, 2)->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['asset_id', 'key']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('asset_variants');
    }
};
