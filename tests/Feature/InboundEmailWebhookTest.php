<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\SimulateInboundEmail;
use App\Domain\InboundEmail\Actions\CreateLeadFromInboundEmail;
use App\Domain\InboundEmail\InboundEmailActionFactory;
use App\Http\Controllers\Api\InboundEmailController;
use App\Http\Middleware\VerifyInboundEmailRequest;
use App\Jobs\ProcessInboundEmail;
use App\Services\Ai\LeadExtractionService;
use App\Services\InboundEmail\InboundEmailReceiver;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

class InboundEmailWebhookTest extends TestCase
{
    public function test_inbound_email_webhook_route_is_registered(): void
    {
        $this->assertTrue(Route::has('inbound-email.webhook'));
    }

    public function test_inbound_email_stack_classes_exist(): void
    {
        $this->assertTrue(class_exists(InboundEmailController::class));
        $this->assertTrue(class_exists(InboundEmailReceiver::class));
        $this->assertTrue(class_exists(ProcessInboundEmail::class));
        $this->assertTrue(class_exists(LeadExtractionService::class));
        $this->assertTrue(class_exists(CreateLeadFromInboundEmail::class));
        $this->assertTrue(class_exists(InboundEmailActionFactory::class));
        $this->assertTrue(class_exists(SimulateInboundEmail::class));
    }

    public function test_verify_inbound_email_middleware_allows_when_verification_disabled(): void
    {
        config([
            'inbound_email.verify_signature' => false,
            'inbound_email.webhook_secret' => 'secret',
        ]);

        $middleware = new VerifyInboundEmailRequest;
        $request = request()->create('/api/inbound-email', 'POST');

        $response = $middleware->handle($request, fn () => response('ok', 200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_verify_inbound_email_middleware_rejects_bad_secret(): void
    {
        config([
            'inbound_email.verify_signature' => true,
            'inbound_email.webhook_secret' => 'expected-secret',
        ]);

        $middleware = new VerifyInboundEmailRequest;
        $request = request()->create('/api/inbound-email', 'POST');

        $response = $middleware->handle($request, fn () => response('ok', 200));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_dealer_lead_fixture_is_callable(): void
    {
        $path = database_path('fixtures/inbound-email/sample-dealer-lead.php');
        $this->assertFileExists($path);

        $builder = require $path;
        $this->assertIsCallable($builder);

        $payload = $builder('lead-test@inbound.helmful.com');
        $this->assertSame('lead-test@inbound.helmful.com', $payload['to']);
        $this->assertNotSame('', trim((string) ($payload['text'] ?? '')));
    }

    public function test_process_inbound_email_job_has_ingestion_constructor(): void
    {
        $ref = new ReflectionClass(ProcessInboundEmail::class);
        $constructor = $ref->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertCount(1, $constructor->getParameters());
        $this->assertSame('ingestionId', $constructor->getParameters()[0]->getName());
    }
}
