<?php

namespace App\Http\Controllers;

use App\Support\MarketingSitemapGenerator;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(MarketingSitemapGenerator $generator): Response
    {
        $xml = $generator->cached();

        if (substr_count($xml, '<loc>') === 0) {
            abort(503, 'Sitemap is temporarily unavailable.');
        }

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
