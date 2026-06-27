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

    public function test_string_backed_enum_select_accepts_string_option_id(): void
    {
        $fieldsSchema = [
            'input_type' => [
                'label' => 'Input type',
                'type' => 'select',
                'enum' => 'App\\Enums\\AssetOption\\AssetOptionInputType',
                'required' => true,
            ],
        ];

        $formSchema = [
            'form' => [
                'primary' => [
                    'fields' => [
                        ['key' => 'input_type', 'required' => true],
                    ],
                ],
            ],
        ];

        $result = SchemaFormValidator::validate(
            ['input_type' => 'toggle'],
            $formSchema,
            $fieldsSchema,
        );

        $this->assertNull($result);
    }

    public function test_string_backed_enum_select_rejects_invalid_value(): void
    {
        $fieldsSchema = [
            'input_type' => [
                'label' => 'Input type',
                'type' => 'select',
                'enum' => 'App\\Enums\\AssetOption\\AssetOptionInputType',
                'required' => true,
            ],
        ];

        $formSchema = [
            'form' => [
                'primary' => [
                    'fields' => [
                        ['key' => 'input_type', 'required' => true],
                    ],
                ],
            ],
        ];

        $result = SchemaFormValidator::validate(
            ['input_type' => 'not_a_type'],
            $formSchema,
            $fieldsSchema,
        );

        $this->assertNotNull($result);
        $this->assertArrayHasKey('input_type', $result['errors']);
    }

    public function test_merge_field_defaults_applies_enum_ids_for_contract_quick_create(): void
    {
        $fieldsSchema = [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'enum' => 'App\\Enums\\Contract\\ContractStatus',
                'default' => 'draft',
            ],
            'payment_status' => [
                'label' => 'Payment Status',
                'type' => 'select',
                'enum' => 'App\\Enums\\Contract\\ContractPaymentStatus',
                'default' => 'pending',
            ],
        ];

        $formSchema = [
            'form' => [
                'primary' => [
                    'fields' => [
                        ['key' => 'status', 'required' => true],
                        ['key' => 'payment_status', 'required' => true],
                    ],
                ],
            ],
        ];

        $result = SchemaFormValidator::validate(
            [
                'customer_id' => 1,
                'total_amount' => 1000,
            ],
            $formSchema,
            $fieldsSchema,
        );

        $this->assertNull($result);
    }

    public function test_string_backed_enum_with_numeric_option_ids_accepts_id_or_value(): void
    {
        $fieldsSchema = [
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'enum' => 'App\\Enums\\Invoice\\Status',
                'required' => true,
            ],
        ];

        $formSchema = [
            'form' => [
                'primary' => [
                    'fields' => [
                        ['key' => 'status', 'required' => true],
                    ],
                ],
            ],
        ];

        $this->assertNull(SchemaFormValidator::validate(['status' => 1], $formSchema, $fieldsSchema));
        $this->assertNull(SchemaFormValidator::validate(['status' => 'draft'], $formSchema, $fieldsSchema));

        $invalid = SchemaFormValidator::validate(['status' => 'not_a_status'], $formSchema, $fieldsSchema);
        $this->assertNotNull($invalid);
        $this->assertArrayHasKey('status', $invalid['errors']);
    }

    public function test_partial_update_only_validates_submitted_required_fields(): void
    {
        $fieldsSchema = [
            'display_name' => [
                'label' => 'Title',
                'type' => 'text',
                'required' => true,
            ],
            'status_id' => [
                'label' => 'Status',
                'type' => 'select',
                'enum' => 'App\\Enums\\Task\\TaskStatus',
                'required' => true,
            ],
            'priority_id' => [
                'label' => 'Priority',
                'type' => 'select',
                'enum' => 'App\\Enums\\Task\\TaskPriority',
                'required' => true,
            ],
        ];

        $formSchema = [
            'form' => [
                'task_details' => [
                    'fields' => [
                        ['key' => 'display_name', 'required' => true],
                    ],
                ],
                'management' => [
                    'fields' => [
                        ['key' => 'status_id', 'required' => true],
                        ['key' => 'priority_id', 'required' => true],
                    ],
                ],
            ],
        ];

        $this->assertNull(SchemaFormValidator::validate(
            ['status_id' => 1],
            $formSchema,
            $fieldsSchema,
            partial: true,
        ));

        $missingStatus = SchemaFormValidator::validate(
            ['display_name' => ''],
            $formSchema,
            $fieldsSchema,
            partial: true,
        );

        $this->assertNotNull($missingStatus);
        $this->assertArrayHasKey('display_name', $missingStatus['errors']);
        $this->assertArrayNotHasKey('priority_id', $missingStatus['errors']);
    }
}
