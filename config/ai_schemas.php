<?php

return [
    'survey_lead' => [
        'type' => 'object',
        'properties' => [
            'lead_score' => [
                'type' => 'number',
                'minimum' => 0,
                'maximum' => 100,
                'description' => 'Score from 1–100 assessing lead quality',
            ],
            'score_reasoning' => [
                'type' => 'string',
                'description' => 'Explanation for the lead score',
            ],
            'suggested_tasks' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'List of actionable tasks for this lead (may be empty)',
            ],
            'follow_up_message' => [
                'type' => 'string',
                'description' => 'Suggested follow-up message to the lead (may be empty)',
            ],
            'recommended_send_time' => [
                'type' => 'string',
                'format' => 'date-time',
                'description' => 'ISO 8601 timestamp for when to send follow-up (optional)',
            ],
        ],
        'required' => [
            'lead_score',
            'score_reasoning',
            'suggested_tasks',
            'follow_up_message',
            'recommended_send_time',
        ],
        'additionalProperties' => false,
    ],

    'survey_follow_up' => [
        'type' => 'object',
        'properties' => [
            'satisfaction_score' => [
                'type' => 'number',
                'minimum' => 1,
                'maximum' => 10,
                'description' => 'Customer satisfaction score (1–10)',
            ],
            'key_sentiment' => [
                'type' => 'string',
                'description' => 'Summary of customer sentiment',
            ],
            'suggested_response' => [
                'type' => 'string',
                'description' => 'Suggested message or response to the contact',
            ],
            'next_contact_timing' => [
                'type' => 'string',
                'description' => 'Recommended timing for next follow-up (e.g., "3 days")',
            ],
            'follow_up_tasks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'task_name' => ['type' => 'string'],
                        'due_date' => ['type' => 'string', 'format' => 'date-time'],
                        'reminder' => ['type' => 'string', 'format' => 'date-time'],
                        'priority' => [
                            'type' => 'string',
                            'enum' => ['low', 'medium', 'high'],
                        ],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['task_name', 'due_date'],
                    'additionalProperties' => false,
                ],
                'description' => 'Array of next steps or tasks with details (may be empty)',
            ],
        ],
        'required' => [
            'satisfaction_score',
            'key_sentiment',
            'suggested_response',
            'next_contact_timing',
            'follow_up_tasks',
        ],
        'additionalProperties' => false,
    ],
    'document_analysis' => [
        'type' => 'object',
        'properties' => [
            'document_id' => [
                'type' => 'string',
                'description' => 'ID of the document that was analyzed',
            ],
            'summary' => [
                'type' => 'string',
                'description' => 'Concise summary of the document content',
            ],
            'dates' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'date' => ['type' => 'string', 'format' => 'date'],
                        'context' => ['type' => 'string', 'description' => 'What this date is for (e.g., "Closing date", "Inspection deadline", "Contract expiration")'],
                    ],
                    'required' => ['date', 'context'],
                    'additionalProperties' => false,
                ],
                'description' => 'List of relevant dates mentioned in the document with context',
            ],
            'suggested_tasks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'task_name' => ['type' => 'string'],
                        'due_date' => ['type' => 'string', 'format' => 'date-time'],
                        'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                        'notes' => ['type' => 'string'],
                    ],
                    'required' => ['task_name', 'due_date', 'priority', 'notes'],
                    'additionalProperties' => false,
                ],
                'description' => 'List of actionable tasks suggested based on document content',
            ],
            'key_entities' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Important people, companies, or organizations mentioned',
            ],
            'key_sentiment' => [
                'type' => 'string',
                'enum' => ['positive', 'neutral', 'negative'],
                'description' => 'Overall sentiment of the document',
            ],
            'confidence_score' => [
                'type' => 'number',
                'minimum' => 0,
                'maximum' => 1,
                'description' => 'Confidence level of AI analysis (0–1)',
            ],
        ],
        'required' => [
            'document_id',
            'summary',
            'dates',
            'suggested_tasks',
            'key_entities',
            'key_sentiment',
            'confidence_score',
        ],
        'additionalProperties' => false,
    ]
];
