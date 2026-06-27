<?php

namespace App\Console\Commands;

use App\Support\MarketingSitemapGenerator;
use Illuminate\Console\Command;

class GenerateMarketingSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Warm the cached marketing sitemap XML';

    public function handle(MarketingSitemapGenerator $generator): int
    {
        $xml = $generator->warmCache();
        $urlCount = substr_count($xml, '<loc>');

        $this->components->info('Sitemap cache warmed.');
        $this->line('Base URL: '.config('app.url'));
        $this->line('URL entries: '.$urlCount);

        if ($urlCount === 0) {
            $this->components->error('Sitemap contains no URLs. Check APP_URL and route registration.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
