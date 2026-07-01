<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\User\Models\User;
use App\Http\Controllers\Concerns\HasSchemaSupport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class HasSchemaSupportRecordOptionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->timestamps();
        });

        User::query()->insert([
            ['id' => 1, 'display_name' => 'Alice', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'display_name' => 'Bob', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function test_enum_options_reuse_record_select_query_for_same_model(): void
    {
        $fieldsSchema = [
            'manager_user_id' => ['type' => 'record', 'typeDomain' => 'User'],
            'delivery_approver_user_id' => ['type' => 'record', 'typeDomain' => 'User'],
            'created_by_id' => ['type' => 'record', 'typeDomain' => 'User'],
            'updated_by_id' => ['type' => 'record', 'typeDomain' => 'User'],
        ];

        DB::connection('tenant')->enableQueryLog();
        DB::connection('tenant')->flushQueryLog();

        $options = HasSchemaSupport::enumOptionsFromUnwrappedFields($fieldsSchema);

        $userQueries = collect(DB::connection('tenant')->getQueryLog())
            ->filter(fn (array $query) => str_contains(strtolower($query['query']), 'from "users"'))
            ->count();

        $this->assertCount(2, $options['manager_user_id']);
        $this->assertSame($options['manager_user_id'], $options['created_by_id']);
        $this->assertSame(1, $userQueries);
    }
}
