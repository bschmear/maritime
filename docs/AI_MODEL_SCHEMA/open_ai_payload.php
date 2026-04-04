$response = OpenAI::chat()->create([
    'model' => 'gpt-5',
    'temperature' => 0.2,

    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'boat_model_meta',
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [
                    'series',
                    'type_display',
                    'boat_type_key',
                    'description',
                    'features'
                ],
                'properties' => [

                    'series' => [
                        'type' => 'string'
                    ],

                    'type_display' => [
                        'type' => 'string'
                    ],

                    'boat_type_key' => [
                        'type' => 'string',
                        'enum' => array_map(fn($case) => $case->value, \App\Enums\Inventory\BoatType::cases())
                    ],

                    'description' => [
                        'type' => 'string'
                    ],

                    'features' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string'
                        ]
                    ],

                    // ONE OF THESE WILL EXIST
                    'specifications' => [
                        'type' => ['object', 'null'],
                        'additionalProperties' => false,
                        'properties' => [
                            'length' => ['type' => ['string', 'null']],
                            'width' => ['type' => ['string', 'null']],
                            'height' => ['type' => ['string', 'null']],
                            'weight' => ['type' => ['string', 'null']],
                            'capacity_persons' => ['type' => ['integer', 'null']],
                            'max_hp' => ['type' => ['string', 'null']],
                            'fuel_capacity' => ['type' => ['string', 'null']]
                        ]
                    ],

                    'variants' => [
                        'type' => ['array', 'null'],
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['id', 'name', 'specifications'],
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'name' => ['type' => 'string'],
                                'specifications' => [
                                    'type' => 'object',
                                    'additionalProperties' => false,
                                    'properties' => [
                                        'length' => ['type' => ['string', 'null']],
                                        'width' => ['type' => ['string', 'null']],
                                        'height' => ['type' => ['string', 'null']],
                                        'weight' => ['type' => ['string', 'null']],
                                        'capacity_persons' => ['type' => ['integer', 'null']],
                                        'max_hp' => ['type' => ['string', 'null']],
                                        'fuel_capacity' => ['type' => ['string', 'null']]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],

                // enforce one or the other
                'oneOf' => [
                    ['required' => ['specifications']],
                    ['required' => ['variants']]
                ]
            ]
        ]
    ],

    'messages' => [

        [
            'role' => 'system',
            'content' => <<<SYS
You are a marine product data normalization engine.

Return ONLY valid JSON that matches the provided schema.

STRICT RULES:
- Do not include any text outside JSON
- Do not include comments
- Do not include extra fields
- Use null when data is unknown
- Do not guess specifications
- Prefer accuracy over completeness

SPEC RULES:
- length/width/height must be in feet and inches format: 21' 8"
- weight must be in lbs (e.g., "2357 lbs")
- max_hp must be like "150hp"
- fuel_capacity must be like "40 gal"
- capacity_persons must be an integer

VARIANT RULES:
- Only include "variants" if the model line clearly has size or trim variations
- If variants exist:
  - DO NOT include root "specifications"
- If no variants:
  - DO include "specifications"

ID RULES (CRITICAL):
- Variant IDs must be slug-style (lowercase, hyphenated)
- Example: "11-dlx", "19-dlx-io"
- IDs must be stable and reusable

BOAT TYPE:
- Must match one of the provided enum values exactly

When unsure:
- Use null
- Still return valid structure
SYS
        ],

        [
            'role' => 'user',
            'content' => json_encode([
                'task' => 'generate_boat_model_metadata',
                'make' => $makeLabel,     // e.g. "Walker Bay"
                'model' => $modelLabel,   // e.g. "G15"
                'make_slug' => $makeSlug, // e.g. "walker-bay"
                'model_slug' => $modelSlug // e.g. "g15"
            ])
        ]
    ]
]);