<?php

use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminChatbotRuleController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminKamarController;
use App\Http\Controllers\AdminKamarAvailabilityController;
use App\Http\Controllers\AdminRekapController;
use App\Http\Controllers\AdminReservasiController;
use App\Http\Controllers\AdminWhatsappChatController;
use App\Http\Controllers\KirimChatWebhookController;
use App\Http\Controllers\AdminPengaduanController;
use App\Http\Controllers\RetribusiBillingController;
use App\Models\Kamar;
use App\Models\KamarReservasi;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('landing', ['kamars' => Kamar::latest()->get()]);
})->name('landing');

Route::post('/lacak-booking', function () {
    $data = request()->validate([
        'kode' => ['required', 'string', 'max:80'],
        'phone_number' => ['nullable', 'string', 'max:40'],
    ]);

    $reservasi = KamarReservasi::with(['kamar', 'items.kamar'])
        ->where('kode', $data['kode'])
        ->when($data['phone_number'] ?? null, fn ($query, $phone) => $query->where('phone_number', $phone))
        ->first();

    return view('landing', [
        'kamars' => Kamar::latest()->get(),
        'trackingResult' => $reservasi,
        'trackingCode' => $data['kode'],
    ]);
})->name('booking.track');

Route::post('/webhooks/kirimchat', [KirimChatWebhookController::class, 'handle'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->name('webhooks.kirimchat.public');

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.store');
Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/admin/dashboard', AdminDashboardController::class)->name('admin.dashboard');
    Route::get('/admin/kamar/availability', AdminKamarAvailabilityController::class)->name('admin.kamar.availability');
    Route::post('/admin/kamar', [AdminKamarController::class, 'store'])->name('admin.kamar.store');
    Route::patch('/admin/kamar/{kamar}', [AdminKamarController::class, 'update'])->name('admin.kamar.update');
    Route::post('/admin/kamar/{kamar}/duplicate', [AdminKamarController::class, 'duplicate'])->name('admin.kamar.duplicate');
    Route::delete('/admin/kamar/{kamar}', [AdminKamarController::class, 'destroy'])->name('admin.kamar.destroy');
    Route::post('/admin/reservasi', [AdminReservasiController::class, 'store'])->name('admin.reservasi.store');
    Route::patch('/admin/reservasi/{reservasi}', [AdminReservasiController::class, 'update'])->name('admin.reservasi.update');
    Route::post('/admin/reservasi/{reservasi}/toggle-payment', [AdminReservasiController::class, 'togglePayment'])->name('admin.reservasi.toggle-payment');
    Route::delete('/admin/reservasi/{reservasi}', [AdminReservasiController::class, 'destroy'])->name('admin.reservasi.destroy');
    Route::post('/admin/reservasi/{reservasi}/retribusi', [RetribusiBillingController::class, 'store'])->name('admin.retribusi.store');
    Route::patch('/admin/retribusi/{billing}', [RetribusiBillingController::class, 'update'])->name('admin.retribusi.update');
    Route::post('/admin/retribusi/{billing}/send', [RetribusiBillingController::class, 'send'])->name('admin.retribusi.send');
    Route::post('/admin/chatbot-rules', [AdminChatbotRuleController::class, 'store'])->name('admin.chatbot-rules.store');
    Route::patch('/admin/chatbot-rules/{rule}', [AdminChatbotRuleController::class, 'update'])->name('admin.chatbot-rules.update');
    Route::delete('/admin/chatbot-rules/{rule}', [AdminChatbotRuleController::class, 'destroy'])->name('admin.chatbot-rules.destroy');
    Route::get('/admin/whatsapp/messages', [AdminWhatsappChatController::class, 'index'])->name('admin.whatsapp.messages');
    Route::post('/admin/whatsapp/send', [AdminWhatsappChatController::class, 'send'])->name('admin.whatsapp.send');
    Route::get('/admin/rekap-bulanan', [AdminRekapController::class, 'index'])->name('admin.rekap.bulanan');
    Route::patch('/admin/pengaduan/{pengaduan}', [AdminPengaduanController::class, 'update'])->name('admin.pengaduan.update');
    Route::delete('/admin/pengaduan/{pengaduan}', [AdminPengaduanController::class, 'destroy'])->name('admin.pengaduan.destroy');
});
