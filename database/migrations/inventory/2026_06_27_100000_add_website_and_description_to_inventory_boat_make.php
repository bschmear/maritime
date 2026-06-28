<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'inventory';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('boat_make')) {
            return;
        }

        $schema->table('boat_make', function (Blueprint $table) use ($schema) {
            if (! $schema->hasColumn('boat_make', 'website_url')) {
                $table->string('website_url', 512)->nullable()->after('logo_url');
            }
            if (! $schema->hasColumn('boat_make', 'description')) {
                $table->text('description')->nullable()->after('website_url');
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->connection);

        if (! $schema->hasTable('boat_make')) {
            return;
        }

        $schema->table('boat_make', function (Blueprint $table) use ($schema) {
            $columns = [];
            if ($schema->hasColumn('boat_make', 'description')) {
                $columns[] = 'description';
            }
            if ($schema->hasColumn('boat_make', 'website_url')) {
                $columns[] = 'website_url';
            }
            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
