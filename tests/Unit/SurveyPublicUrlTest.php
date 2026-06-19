<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Survey\SurveyPublicUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Stancl\Tenancy\Contracts\Tenant;
use Tests\TestCase;

class SurveyPublicUrlTest extends TestCase
{
    public function test_signed_show_url_validates_like_laravel_signed_route(): void
    {
        $domain = new class
        {
            public string $domain = '762332.maritime.test';
        };

        $tenant = new class($domain)
        {
            public function __construct(public object $domainModel) {}

            public function domains()
            {
                return new class($this->domainModel)
                {
                    public function __construct(public object $domainModel) {}

                    public function first()
                    {
                        return $this->domainModel;
                    }
                };
            }
        };

        $this->app->instance(Tenant::class, $tenant);

        $signed = SurveyPublicUrl::signedShowUrl([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'type' => 'contact',
            'rid' => 42,
            'aid' => 7,
        ]);

        $this->assertStringStartsWith('https://762332.maritime.test/survey/view?', $signed);
        $this->assertStringContainsString('signature=', $signed);

        $request = Request::create($signed);
        $this->assertTrue(URL::hasValidSignature($request));
    }
}
