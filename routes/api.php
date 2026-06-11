<?php

use App\Http\Controllers\KirimChatWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/kirimchat', [KirimChatWebhookController::class, 'handle'])
    ->name('webhooks.kirimchat');
