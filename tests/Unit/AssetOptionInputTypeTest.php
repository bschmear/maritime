<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\AssetOption\AssetOptionInputType;
use PHPUnit\Framework\TestCase;

class AssetOptionInputTypeTest extends TestCase
{
    public function test_options_returns_four_choices(): void
    {
        $options = AssetOptionInputType::options();

        $this->assertCount(4, $options);
        $this->assertSame('select', $options[0]['id']);
    }
}
