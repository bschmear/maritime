<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\MsoRecord\Models\MsoSourceLayout;
use App\Domain\MsoRecord\Support\MsoRecordDetails;
use App\Domain\MsoRecord\Support\SaveMsoBuilderState;
use PHPUnit\Framework\TestCase;

class SaveMsoBuilderStateTest extends TestCase
{
    public function test_fields_from_layout_template_generates_new_ids_and_prefill(): void
    {
        $record = new MsoRecord;
        $record->details = MsoRecordDetails::build([
            'transaction' => ['customer_name' => 'Sam Customer'],
            'subsidiary' => ['display_name' => 'Harbor Dealer'],
            'line_item' => ['name' => 'Unit A'],
        ], null, []);

        $layout = new MsoSourceLayout;
        $layout->layout = [
            [
                'type' => 'customer_name',
                'page' => 1,
                'x' => 0.1,
                'y' => 0.2,
                'width' => 0.3,
                'height' => 0.04,
                'font_size' => 10,
            ],
        ];

        $fields = SaveMsoBuilderState::fieldsFromLayoutTemplate($layout, $record, null);

        $this->assertCount(1, $fields);
        $this->assertSame('customer_name', $fields[0]['type']);
        $this->assertSame('Sam Customer', $fields[0]['value']);
        $this->assertNotEmpty($fields[0]['id']);
    }
}
