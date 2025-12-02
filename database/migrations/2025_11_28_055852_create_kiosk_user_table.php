<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('kiosk_role_id');
            $table->timestamps();

            $table->unique(['user_id', 'kiosk_role_id']);
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('kiosk_role_id')->references('id')->on('kiosk_roles')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_user');
    }
};
