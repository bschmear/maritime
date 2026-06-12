<?php

namespace Tests\Unit\Support\Validation;

use App\Support\Validation\FriendlyDatabaseErrors;
use PHPUnit\Framework\TestCase;

class FriendlyDatabaseErrorsTest extends TestCase
{
    public function test_it_maps_not_null_violations_to_field_labels(): void
    {
        $message = 'SQLSTATE[23502]: Not null violation: 7 ERROR: null value in column "condition" of relation "asset_units" violates not-null constraint';

        $result = FriendlyDatabaseErrors::fromMessage($message, [
            'condition' => ['label' => 'Condition', 'type' => 'select'],
        ]);

        $this->assertSame('Please select Condition.', $result['message']);
        $this->assertSame(['condition' => 'Please select Condition.'], $result['errors']);
    }
}
