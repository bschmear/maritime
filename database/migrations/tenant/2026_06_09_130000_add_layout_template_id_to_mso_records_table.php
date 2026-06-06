<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mso_records', function (Blueprint $table) {
            $table->foreignId('layout_template_id')
                ->nullable()
                ->after('output_document_id')
                ->constrained('mso_layout_templates')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mso_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_template_id');
        });
    }
};
