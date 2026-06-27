<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\Document\Models\Document;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BoatMakeLogoTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'tenant',
            'database.connections.tenant' => array_merge(
                config('database.connections.sqlite'),
                ['database' => ':memory:']
            ),
            'filesystems.disks.s3.cdn_url' => 'https://cdn.example.com',
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('display_name')->nullable();
            $table->string('file');
            $table->string('file_extension')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();
        });

        Schema::connection('tenant')->create('boat_make', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('slug')->nullable();
            $table->boolean('is_custom')->default(false);
            $table->boolean('use_default_logo')->default(true);
            $table->string('default_brand_image', 512)->nullable();
            $table->unsignedBigInteger('custom_logo_id')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function test_logo_url_uses_default_catalog_image_when_enabled(): void
    {
        $make = BoatMake::query()->create([
            'display_name' => 'Achilles',
            'use_default_logo' => true,
            'default_brand_image' => 'https://cdn.example.com/public/inventory/boat_makes/achilles.webp',
        ]);

        $this->assertSame(
            'https://cdn.example.com/public/inventory/boat_makes/achilles.webp',
            $make->logo_url
        );
    }

    public function test_logo_url_uses_custom_document_when_default_disabled(): void
    {
        $document = Document::query()->create([
            'display_name' => 'Custom logo',
            'file' => 'public/tenant-1/boat_makes/custom.webp',
            'file_extension' => 'webp',
            'file_size' => 1000,
        ]);

        $make = BoatMake::query()->create([
            'display_name' => 'Custom Brand',
            'use_default_logo' => false,
            'default_brand_image' => 'https://cdn.example.com/public/inventory/boat_makes/default.webp',
            'custom_logo_id' => $document->id,
        ]);

        $this->assertSame(
            'https://cdn.example.com/public/tenant-1/boat_makes/custom.webp',
            $make->logo_url
        );
    }

    public function test_logo_url_is_null_when_no_logo_configured(): void
    {
        $make = BoatMake::query()->create([
            'display_name' => 'No Logo Brand',
            'use_default_logo' => false,
        ]);

        $this->assertNull($make->logo_url);
    }
}
