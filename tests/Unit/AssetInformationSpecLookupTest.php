<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Asset\AssetInformationSpecLookup;
use Tests\TestCase;

class AssetInformationSpecLookupTest extends TestCase
{
    public function test_it_resolves_walker_bay_generation_12_specs(): void
    {
        $context = [
            'tenant_id' => '762332',
            'model_name' => 'Walker Bay Generation 12',
            'make_label' => 'Walker Bay',
            'model_label' => 'Generation 12',
            'spec_fields' => [
                ['name' => 'length', 'type' => 'number', 'unit' => 'mm', 'required' => false],
                ['name' => 'width', 'type' => 'number', 'unit' => 'mm', 'required' => false],
                ['name' => 'hull_type', 'type' => 'string', 'unit' => null, 'required' => false],
                ['name' => 'hull_material', 'type' => 'string', 'unit' => null, 'required' => false],
                ['name' => 'boat_type', 'type' => 'string', 'unit' => null, 'required' => false],
                ['name' => 'max_people', 'type' => 'number', 'unit' => null, 'required' => false],
                ['name' => 'max_hp', 'type' => 'number', 'unit' => 'hp', 'required' => false],
                ['name' => 'boat_weight', 'type' => 'number', 'unit' => 'lb', 'required' => false],
            ],
        ];

        $result = AssetInformationSpecLookup::resolve($context);

        $this->assertIsArray($result);
        $this->assertSame('manufacturer_verified', $result['data_source_type']);
        $this->assertSame(3658, $result['specs']['length']);
        $this->assertSame(1930, $result['specs']['width']);
        $this->assertSame(6, $result['specs']['max_people']);
        $this->assertSame(50, $result['specs']['max_hp']);
        $this->assertNotNull($result['specs']['boat_weight']);
        $this->assertNotNull($result['specs']['hull_type']);
        $this->assertNotNull($result['specs']['boat_type']);
    }

    public function test_it_returns_null_for_unknown_brand(): void
    {
        $result = AssetInformationSpecLookup::resolve([
            'tenant_id' => '1',
            'model_name' => 'Mystery Boat 9000',
            'make_label' => 'Not A Real Brand XYZ',
            'model_label' => '9000',
            'spec_fields' => [
                ['name' => 'length', 'type' => 'number', 'unit' => 'mm', 'required' => false],
            ],
        ]);

        $this->assertNull($result);
    }
}
