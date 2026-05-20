<?php

use App\Http\Controllers\Api\SupportTicketApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('ticket.support')->group(function () {
    Route::apiResource('support-tickets', SupportTicketApiController::class)
        ->only(['index', 'store', 'show']);

    Route::post('support-tickets/{support_ticket}/responses', [SupportTicketApiController::class, 'storeResponse'])
        ->name('support-tickets.responses.store');

    Route::put('support-tickets/{support_ticket}/reopen', [SupportTicketApiController::class, 'reopen'])
        ->name('support-tickets.reopen');
});
