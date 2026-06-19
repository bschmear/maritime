<?php

namespace App\Console\Commands;

use App\Enums\InboundEmail\IngestionStatus;
use App\Models\AiEmailIngestion;
use App\Services\InboundEmail\InboundEmailReceiver;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

class SimulateInboundEmail extends Command
{
    protected $signature = 'inbound-email:simulate
                            {address : Active email_routes.address to deliver to}
                            {--url= : Webhook URL (default APP_URL/api/inbound-email)}
                            {--fixture=dealer-lead : Fixture name under database/fixtures/inbound-email}
                            {--secret= : X-Inbound-Email-Secret header (default from config)}
                            {--direct : Bypass HTTP and call InboundEmailReceiver directly}
                            {--insecure : Skip SSL certificate verification (local .test HTTPS)}
                            {--sync : After request, run queue:work --once and show ingestion status}';

    protected $description = 'POST a sample SendGrid inbound payload to the inbound email webhook (for staging/production testing).';

    public function handle(InboundEmailReceiver $receiver): int
    {
        $address = strtolower(trim($this->argument('address')));
        $payload = $this->loadFixture($address);

        if ($this->option('direct')) {
            $ingestion = $receiver->receive($payload);
            if ($ingestion === null) {
                $this->error('No active email route found for address: '.$address);

                return self::FAILURE;
            }

            $this->info('Ingestion created directly (id: '.$ingestion->id.').');

            if ($this->option('sync')) {
                $this->runQueueOnce();
                $this->reportIngestionStatus($ingestion->id);
            }

            return self::SUCCESS;
        }

        $url = $this->resolveWebhookUrl();
        $headers = $this->buildHeaders();

        $this->info('POST '.$url);
        $this->line('To: '.$address);

        try {
            $response = $this->httpClient($url)
                ->withHeaders($headers)
                ->post($url, $this->payloadToMultipart($payload));
        } catch (ConnectionException $e) {
            $this->error($e->getMessage());
            $this->newLine();
            $this->line('Local dev options:');
            $this->line('  php artisan inbound-email:simulate '.$address.' --direct --sync');
            $this->line('  php artisan inbound-email:simulate '.$address.' --url=http://maritime.test/api/inbound-email --sync');
            $this->line('  php artisan inbound-email:simulate '.$address.' --url=https://maritime.test/api/inbound-email --insecure --sync');

            return self::FAILURE;
        }

        $this->line('HTTP '.$response->status().': '.$response->body());

        if (! $response->successful()) {
            return self::FAILURE;
        }

        if ($this->option('sync')) {
            $this->runQueueOnce();

            $ingestion = AiEmailIngestion::query()
                ->where('to_email', $address)
                ->latest('id')
                ->first();

            if ($ingestion !== null) {
                $this->reportIngestionStatus($ingestion->id);
            } else {
                $this->warn('No ingestion record found yet for '.$address);
            }
        }

        return self::SUCCESS;
    }

    protected function resolveWebhookUrl(): string
    {
        $url = $this->option('url') ?: rtrim((string) config('app.url'), '/').'/api/inbound-email';

        $host = parse_url($url, PHP_URL_HOST);
        if (
            ! $this->option('url')
            && is_string($host)
            && (str_ends_with($host, '.test') || str_ends_with($host, '.local'))
            && str_starts_with($url, 'https://')
        ) {
            $url = 'http://'.substr($url, strlen('https://'));
        }

        return $url;
    }

    protected function httpClient(string $url): PendingRequest
    {
        $client = Http::asMultipart();

        if ($this->shouldSkipSslVerification($url)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    protected function shouldSkipSslVerification(string $url): bool
    {
        if ($this->option('insecure')) {
            return true;
        }

        if (! app()->environment('local')) {
            return false;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host)
            && (str_ends_with($host, '.test') || str_ends_with($host, '.local'))
            && str_starts_with($url, 'https://');
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadFixture(string $address): array
    {
        $fixture = (string) $this->option('fixture');
        $path = database_path('fixtures/inbound-email/sample-'.$fixture.'.php');

        if (! is_file($path)) {
            throw new \RuntimeException('Fixture not found: '.$path);
        }

        $builder = require $path;
        if (! is_callable($builder)) {
            throw new \RuntimeException('Fixture must return a callable: '.$path);
        }

        /** @var array<string, mixed> $payload */
        $payload = $builder($address);

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    protected function buildHeaders(): array
    {
        $headers = [];

        $secret = $this->option('secret') ?? config('inbound_email.webhook_secret');
        if (is_string($secret) && $secret !== '') {
            $headers['X-Inbound-Email-Secret'] = $secret;
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{name: string, contents: string}>
     */
    protected function payloadToMultipart(array $payload): array
    {
        $multipart = [];
        foreach ($payload as $key => $value) {
            $multipart[] = [
                'name' => (string) $key,
                'contents' => is_string($value) ? $value : json_encode($value, JSON_THROW_ON_ERROR),
            ];
        }

        return $multipart;
    }

    protected function runQueueOnce(): void
    {
        $this->info('Running queue:work --once ...');
        Artisan::call('queue:work', ['--once' => true]);
        $this->line(trim(Artisan::output()));
    }

    protected function reportIngestionStatus(int $ingestionId): void
    {
        $ingestion = AiEmailIngestion::query()->find($ingestionId);
        if ($ingestion === null) {
            $this->warn('Ingestion #'.$ingestionId.' not found.');

            return;
        }

        $status = $ingestion->status instanceof IngestionStatus
            ? $ingestion->status->value
            : (string) $ingestion->status;

        $this->info('Ingestion #'.$ingestion->id.' status: '.$status);

        if ($status === IngestionStatus::Completed->value) {
            $leadId = $ingestion->parsed_data['lead_id'] ?? null;
            if ($leadId !== null) {
                $this->info('Lead created: #'.$leadId);
            }
        }

        if ($status === IngestionStatus::Failed->value && $ingestion->error) {
            $this->error($ingestion->error);
        }
    }
}
