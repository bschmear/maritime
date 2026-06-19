<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Concerns\HasSchemaSupport;
use Illuminate\Routing\Controller;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase as LaravelTestCase;

class BillTableDefaultFiltersTest extends LaravelTestCase
{
    #[Test]
    public function bill_table_schema_defaults_to_open_and_overdue_status_strings(): void
    {
        $controller = new class extends Controller
        {
            use HasSchemaSupport;
        };

        $path = app_path('Domain/Bill/Schema/table.json');
        $schema = json_decode((string) file_get_contents($path), true);

        $method = new \ReflectionMethod($controller, 'defaultFiltersFromTableSchema');
        $method->setAccessible(true);
        $defaults = $method->invoke($controller, $schema);

        $this->assertCount(1, $defaults);
        $this->assertSame('status', $defaults[0]['field']);
        $this->assertSame('any_of', $defaults[0]['operator']);
        $this->assertSame(['open', 'overdue'], $defaults[0]['value']);
    }
}
