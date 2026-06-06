<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('signature_method')->nullable()->after('avatar');
            $table->string('signature_file')->nullable()->after('signature_method');
            $table->string('typed_signature', 255)->nullable()->after('signature_file');
            $table->timestamp('signature_saved_at')->nullable()->after('typed_signature');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'signature_method',
                'signature_file',
                'typed_signature',
                'signature_saved_at',
            ]);
        });
    }
};
