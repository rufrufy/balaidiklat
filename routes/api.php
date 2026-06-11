<?php

use App\Http\Controllers\KirimChatWebhookController;
use App\Http\Controllers\RetribusiBillingController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/kirimchat', [KirimChatWebhookController::class, 'handle'])
    ->name('webhooks.kirimchat');

Route::get('/retribusi/{billing}', [RetribusiBillingController::class, 'apiShow'])
    ->name('api.retribusi.show');
