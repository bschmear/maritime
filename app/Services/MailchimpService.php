<?php

declare(strict_types=1);

namespace App\Services;

use MailchimpMarketing\ApiClient;

class MailchimpService
{
    protected ApiClient $client;

    public function __construct()
    {
        $this->client = new ApiClient;
        $apiKey = config('services.mailchimp.api_key');
        $server = config('services.mailchimp.server_prefix');
        if ($apiKey && $server) {
            $this->client->setConfig([
                'apiKey' => $apiKey,
                'server' => $server,
            ]);
        }
    }

    public function client(): ApiClient
    {
        return $this->client;
    }

    public function withToken(string $token, string $server): ApiClient
    {
        $this->client->setConfig([
            'accessToken' => $token,
            'server' => $server,
        ]);

        return $this->client;
    }
}
