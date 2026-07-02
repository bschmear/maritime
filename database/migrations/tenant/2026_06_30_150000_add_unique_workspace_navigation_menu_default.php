<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX navigation_menus_workspace_default_unique
             ON navigation_menus (is_default)
             WHERE is_default = true AND role_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS navigation_menus_workspace_default_unique');
    }
};
