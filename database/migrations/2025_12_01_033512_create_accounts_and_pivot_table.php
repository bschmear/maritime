<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Accounts Table
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        Schema::create('account_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('role', ['owner', 'admin', 'member'])->default('member');
            $table->timestamps();
            $table->unique(['account_id', 'user_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('account_user');
        Schema::dropIfExists('accounts');
    }
};
