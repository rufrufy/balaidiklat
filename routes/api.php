<?php

use App\Http\Controllers\BapendaCallbackController;
use App\Http\Controllers\KirimChatWebhookController;
use App\Http\Controllers\RetribusiBillingController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/kirimchat', [KirimChatWebhookController::class, 'handle'])
    ->name('webhooks.kirimchat');

Route::get('/retribusi/{billing}', [RetribusiBillingController::class, 'apiShow'])
    ->name('api.retribusi.show');

// Bapenda e-Retribusi integration
Route::prefix('bapenda')->group(function (): void {
    Route::post('/payment-callback', [BapendaCallbackController::class, 'paymentCallback'])
        ->name('api.bapenda.payment-callback');

    Route::get('/qris/{kodebayar}', function (string $kodebayar, \App\Services\ERetribusiService $service) {
        $result = $service->getQrisLink($kodebayar);

        return response()->json($result, $result['success'] ? 200 : 400);
    })->name('api.bapenda.qris');

    Route::get('/billing/{billing}/qris', [RetribusiBillingController::class, 'fetchQris'])
        ->name('api.bapenda.billing.qris');
});
