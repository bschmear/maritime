<?php

namespace Tests\Unit;

use App\Support\MarketingSitemapGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSitemapGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private ?string $generatedPath = null;

    protected function tearDown(): void
    {
        if ($this->generatedPath !== null && is_file($this->generatedPath)) {
            @unlink($this->generatedPath);
        }

        parent::tearDown();
    }

    public function test_generate_writes_sitemap_with_marketing_home_url(): void
    {
        $this->generatedPath = app(MarketingSitemapGenerator::class)->generate();

        $this->assertFileExists($this->generatedPath);

        $xml = file_get_contents($this->generatedPath);

        $this->assertIsString($xml);
        $this->assertStringContainsString(route('home', [], true), $xml);
        $this->assertStringContainsString(route('blog', [], true), $xml);
    }

    public function test_generate_overwrites_existing_sitemap_file(): void
    {
        $path = public_path(MarketingSitemapGenerator::OUTPUT_PATH);
        $this->generatedPath = $path;

        file_put_contents($path, '<?xml version="1.0"?><urlset><url><loc>https://stale.example.test</loc></url></urlset>');

        app(MarketingSitemapGenerator::class)->generate();

        $xml = (string) file_get_contents($path);

        $this->assertStringNotContainsString('stale.example.test', $xml);
        $this->assertStringContainsString(route('home', [], true), $xml);
    }
}
