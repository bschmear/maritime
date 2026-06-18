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
}
