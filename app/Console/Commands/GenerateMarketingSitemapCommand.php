<?php

namespace App\Console\Commands;

use App\Support\MarketingSitemapGenerator;
use Illuminate\Console\Command;

class GenerateMarketingSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate public/sitemap.xml for marketing and blog pages';

    public function handle(MarketingSitemapGenerator $generator): int
    {
        $path = $generator->generate();

        $this->components->info('Sitemap written to '.$path);

        return self::SUCCESS;
    }
}
