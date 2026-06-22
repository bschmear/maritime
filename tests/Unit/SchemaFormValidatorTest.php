<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Validation\SchemaFormValidator;
use Tests\TestCase;

class SchemaFormValidatorTest extends TestCase
{
    public function test_location_type_accepts_integer_option_id(): void
    {
        $fieldsSchema = [
            'location_type' => [
                'label' => 'Location Type',
                'type' => 'select',
                'enum' => 'App\\Enums\\Locations\\LocationType',
                'required' => true,
            ],
        ];

        $formSchema = [
            'form' => [
                'primary' => [
                    'fields' => [
                        ['key' => 'location_type', 'required' => true],
                    ],
                ],
            ],
        ];

        $result = SchemaFormValidator::validate(
            ['location_type' => 1],
            $formSchema,
            $fieldsSchema,
        );

        $this->assertNull($result);
    }

    public function test_location_type_rejects_invalid_integer(): void
    {
        $fieldsSchema = [
            'location_type' => [
                'label' => 'Location Type',
                'type' => 'select',
                'enum' => 'App\\Enums\\Locations\\LocationType',
                'required' => true,
            ],
        ];

        $formSchema = [
            'form' => [
                'primary' => [
                    'fields' => [
                        ['key' => 'location_type', 'required' => true],
                    ],
                ],
            ],
        ];

        $result = SchemaFormValidator::validate(
            ['location_type' => 999],
            $formSchema,
            $fieldsSchema,
        );

        $this->assertNotNull($result);
        $this->assertArrayHasKey('location_type', $result['errors']);
    }

    public function test_multi_enum_accepts_array_of_option_ids(): void
    {
        $fieldsSchema = [
            'asset_types' => [
                'label' => 'Applies to asset types',
                'type' => 'multi_enum',
                'enum' => 'App\\Enums\\Inventory\\AssetType',
                'required' => true,
            ],
        ];

        $formSchema = [
            'form' => [
                'primary' => [
                    'fields' => [
                        ['key' => 'asset_types', 'required' => true],
                    ],
                ],
            ],
        ];

        $result = SchemaFormValidator::validate(
            ['asset_types' => [1], 'vendor_id' => 5],
            $formSchema,
            $fieldsSchema,
        );

        $this->assertNull($result);
    }
}
