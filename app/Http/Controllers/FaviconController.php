<?php

namespace App\Http\Controllers;

class FaviconController extends Controller
{
    public function __invoke()
    {
        $path = public_path('assets/icons/favicon.ico');
        if (! is_file($path)) {
            abort(404);
        }

        return response()->file($path, [
            'Content-Type' => 'image/x-icon',
        ]);
    }
}
