<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('account_user')) {
            return;
        }

        Schema::table('account_user', function (Blueprint $table) {
            $table->string('role_new', 32)->default('employee');
        });

        foreach (DB::table('account_user')->select('id', 'role')->cursor() as $row) {
            $new = match ((string) $row->role) {
                'owner' => 'owner',
                'admin' => 'admin',
                'member' => 'employee',
                default => 'employee',
            };
            DB::table('account_user')->where('id', $row->id)->update(['role_new' => $new]);
        }

        Schema::table('account_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('account_user', function (Blueprint $table) {
            $table->renameColumn('role_new', 'role');
        });

        if (Schema::hasTable('invitations')) {
            DB::table('invitations')->where('role', 'member')->update(['role' => 'employee']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('account_user')) {
            return;
        }

        Schema::table('account_user', function (Blueprint $table) {
            $table->string('role_old', 32)->default('member');
        });

        foreach (DB::table('account_user')->select('id', 'role')->cursor() as $row) {
            $old = match (true) {
                (string) $row->role === 'owner' => 'owner',
                (string) $row->role === 'admin' => 'admin',
                in_array((string) $row->role, ['manager', 'employee', 'guest'], true) => 'member',
                default => 'member',
            };
            DB::table('account_user')->where('id', $row->id)->update(['role_old' => $old]);
        }

        Schema::table('account_user', function (Blueprint $table) {
            $table->dropColumn('role');
        });

        Schema::table('account_user', function (Blueprint $table) {
            $table->renameColumn('role_old', 'role');
        });

        if (Schema::hasTable('invitations')) {
            DB::table('invitations')->whereIn('role', ['manager', 'employee', 'guest'])->update(['role' => 'member']);
        }
    }
};
