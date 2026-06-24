<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\BoatShow\Support\BoatShowWordPressPayload;
use App\Domain\Integration\Support\WordPressIntegrationSettings;
use App\Services\Integrations\WordPressPluginZipBuilder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

class WordPressIntegrationTest extends TestCase
{
    #[Test]
    public function it_hashes_api_keys_consistently(): void
    {
        $key = 'test-api-key-123';
        $hash = WordPressIntegrationSettings::hashApiKey($key);

        $this->assertTrue(WordPressIntegrationSettings::verifyApiKey($key, $hash));
        $this->assertFalse(WordPressIntegrationSettings::verifyApiKey('wrong-key', $hash));
    }

    #[Test]
    public function boat_show_payload_includes_expected_keys(): void
    {
        $show = new \App\Domain\BoatShow\Models\BoatShow;
        $show->forceFill([
            'display_name' => 'Miami Boat Show',
            'slug' => 'miami-boat-show',
            'description' => 'Annual show',
            'website' => 'https://example.com',
        ]);
        $show->uuid = '11111111-1111-1111-1111-111111111111';
        $payload = BoatShowWordPressPayload::forShow($show);
        $this->assertSame('11111111-1111-1111-1111-111111111111', $payload['uuid']);
        $this->assertSame('Miami Boat Show', $payload['display_name']);
        $this->assertArrayHasKey('logo_url', $payload);
        $this->assertArrayHasKey('app_show_url', $payload);
    }

    #[Test]
    public function boat_show_event_payload_includes_link_fields(): void
    {
        $show = new \App\Domain\BoatShow\Models\BoatShow;
        $show->uuid = '22222222-2222-2222-2222-222222222222';
        $show->slug = 'miami-boat-show';

        $event = new \App\Domain\BoatShowEvent\Models\BoatShowEvent;
        $event->forceFill([
            'display_name' => 'Miami 2026',
            'year' => 2026,
            'active' => true,
        ]);
        $event->id = 42;
        $event->uuid = '33333333-3333-3333-3333-333333333333';
        $event->setRelation('show', $show);

        $payload = BoatShowWordPressPayload::forEvent($event);

        $this->assertSame('33333333-3333-3333-3333-333333333333', $payload['uuid']);
        $this->assertSame('22222222-2222-2222-2222-222222222222', $payload['boat_show_uuid']);
        $this->assertArrayHasKey('logo_url', $payload);
        $this->assertArrayHasKey('app_event_url', $payload);
        $this->assertArrayHasKey('public_event_url', $payload);
    }

    #[Test]
    public function event_post_type_respects_wordpress_twenty_character_limit(): void
    {
        $source = (string) file_get_contents(base_path('wordpress-plugin/helmful-sync/includes/class-cpt.php'));

        preg_match("/public const EVENT_POST_TYPE = '([^']+)'/", $source, $matches);

        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertLessThanOrEqual(20, strlen($matches[1]));
    }

    #[Test]
    public function it_builds_a_wordpress_installable_plugin_zip(): void
    {
        $builder = new WordPressPluginZipBuilder;
        $zipPath = $builder->build();

        $this->assertFileExists($zipPath);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath) === true);
        $this->assertNotFalse($zip->locateName('helmful-sync/helmful-sync.php'));
        $this->assertNotFalse($zip->locateName('helmful-sync/includes/class-rest-api.php'));
        $zip->close();

        @unlink($zipPath);
    }
}
