<?php

namespace Tests\Unit;

use App\Domain\Integration\Models\Integration;
use App\Enums\Integration\IntegrationType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class IntegrationExternalIdEncryptionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:'],
            ),
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('integration_type');
            $table->text('external_id');
            $table->string('external_id_hash', 64)->nullable();
            $table->string('name')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    #[Test]
    public function external_id_is_encrypted_at_rest_and_hash_is_set(): void
    {
        $integration = Integration::query()->create([
            'integration_type' => IntegrationType::QuickBooks,
            'external_id' => 'realm-12345',
            'name' => 'QuickBooks',
            'active' => true,
        ]);

        $raw = DB::connection('tenant')
            ->table('integrations')
            ->where('id', $integration->id)
            ->value('external_id');

        $this->assertNotSame('realm-12345', $raw);
        $this->assertSame('realm-12345', $integration->fresh()->external_id);
        $this->assertSame(
            Integration::hashExternalId('realm-12345'),
            $integration->fresh()->external_id_hash,
        );
    }

    #[Test]
    public function attributes_for_external_id_matches_hash(): void
    {
        $this->assertSame(
            ['external_id_hash' => Integration::hashExternalId('abc')],
            Integration::attributesForExternalId('abc'),
        );
    }
}
