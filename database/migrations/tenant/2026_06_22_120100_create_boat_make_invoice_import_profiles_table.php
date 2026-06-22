<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boat_make_invoice_import_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('boat_make_id');
            $table->text('ai_instructions')->nullable();
            $table->timestamps();

            $table->foreign('boat_make_id')->references('id')->on('boat_make')->cascadeOnDelete();
            $table->unique('boat_make_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boat_make_invoice_import_profiles');
    }
};
