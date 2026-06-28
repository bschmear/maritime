<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ExtendInvoiceImportExecutionTime
{
    public function handle(Request $request, Closure $next): Response
    {
        $seconds = (int) config('invoice_import.max_execution_seconds', 300);

        if ($seconds > 0) {
            set_time_limit($seconds);
            ini_set('max_execution_time', (string) $seconds);
        }

        return $next($request);
    }
}
