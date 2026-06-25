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
    $kamars = Kamar::with('fotos')->latest()->get();
    $availableKamars = Kamar::orderBy('jenis_kelas')->get();

    return view('landing', [
        'kamars' => $kamars,
        'availableKamars' => $availableKamars,
        'whatsappBotNumber' => preg_replace('/\D/', '', config('app.whatsapp_bot_number', '62878455351641')),
    ]);
})->name('landing');

Route::post('/cek-ketersediaan', function () {
    $data = request()->validate([
        'tanggal_masuk' => ['required', 'date'],
        'tanggal_keluar' => ['required', 'date', 'after:tanggal_masuk'],
    ]);

    $service = app(\App\Services\KamarAvailabilityService::class);
    $rooms = $service->availableRoomsWithStock($data['tanggal_masuk'], $data['tanggal_keluar']);

    $rooms->load('fotos');

    return response()->json([
        'rooms' => $rooms->map(function ($room) {
            $fotos = $room->allFotoPaths()->map(fn ($path) => asset('storage/'.$path))->values();

            return [
                'id' => $room->id,
                'kode' => $room->kode,
                'nama' => $room->nama,
                'tipe' => $room->tipeLabel(),
                'harga' => number_format($room->harga_per_malam, 0, ',', '.'),
                'fasilitas' => $room->fasilitas,
                'status' => $room->status,
                'fotos' => $fotos,
                'stok_total' => (int) $room->stok_total,
                'tersedia' => (int) $room->tersedia,
                'terpakai' => (int) $room->terpakai,
            ];
        }),
        'tanggal_masuk' => $data['tanggal_masuk'],
        'tanggal_keluar' => $data['tanggal_keluar'],
    ]);
})->name('cek.ketersediaan');

Route::post('/kirim-pemesanan-whatsapp', function (\Illuminate\Http\Request $request) {
    $data = $request->validate([
        'nama_pemesan' => ['required', 'string', 'max:255'],
        'phone_number' => ['required', 'string', 'max:40'],
        'tipe_penyewa' => ['required', 'in:perorangan,instansi'],
        'instansi' => ['nullable', 'string', 'max:255'],
        'kegiatan' => ['nullable', 'string', 'max:255'],
        'tanggal_masuk' => ['required', 'date'],
        'tanggal_keluar' => ['required', 'date', 'after:tanggal_masuk'],
        'kamar_id' => ['required', 'exists:kamars,id'],
        'jumlah_unit' => ['required', 'integer', 'min:1'],
        'multiple' => ['nullable', 'boolean'],
        'items' => ['nullable', 'array'],
        'items.*.kamar_id' => ['nullable', 'exists:kamars,id'],
        'items.*.tanggal_masuk' => ['nullable', 'date'],
        'items.*.tanggal_keluar' => ['nullable', 'date'],
        'items.*.jumlah_unit' => ['nullable', 'integer', 'min:1'],
    ]);

    $kamar = \App\Models\Kamar::find($data['kamar_id']);
    $lines = [
        'FORM_PEMESANAN_LANDING',
        'Nama: '.$data['nama_pemesan'],
        'No WA: '.$data['phone_number'],
        'Tipe Penyewa: '.$data['tipe_penyewa'],
    ];

    if ($data['tipe_penyewa'] === 'instansi') {
        $lines[] = 'Instansi: '.($data['instansi'] ?? '-');
        $lines[] = 'Kegiatan: '.($data['kegiatan'] ?? '-');
    }

    $lines[] = 'Tanggal Masuk: '.$data['tanggal_masuk'];
    $lines[] = 'Tanggal Keluar: '.$data['tanggal_keluar'];
    $lines[] = 'Jenis Kelas: '.$kamar->jenis_kelas;
    $lines[] = 'Jumlah Unit: '.$data['jumlah_unit'];

    if (! empty($data['multiple']) && ! empty($data['items'])) {
        $lines[] = '--- Item Tambahan ---';
        foreach ($data['items'] as $i => $item) {
            if (empty($item['kamar_id'])) {
                continue;
            }
            $ik = \App\Models\Kamar::find($item['kamar_id']);
            if (! $ik) {
                continue;
            }
            $lines[] = sprintf(
                'Item %d: %s | %s s/d %s | %s unit',
                $i + 1,
                $ik->jenis_kelas,
                $item['tanggal_masuk'] ?? '-',
                $item['tanggal_keluar'] ?? '-',
                $item['jumlah_unit'] ?? 1
            );
        }
    }

    $lines[] = 'Mohon proses pemesanan ini. Terima kasih.';

    $text = implode("\n", $lines);
    $waNumber = preg_replace('/\D/', '', config('app.whatsapp_bot_number', '62878455351641'));
    $url = 'https://wa.me/'.$waNumber.'?text='.rawurlencode($text);

    return response()->json(['success' => true, 'whatsapp_url' => $url, 'message' => $text]);
})->name('kirim.pemesanan.whatsapp');

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
        'kamars' => Kamar::with('fotos')->latest()->get(),
        'availableKamars' => Kamar::orderBy('jenis_kelas')->get(),
        'whatsappBotNumber' => preg_replace('/\D/', '', config('app.whatsapp_bot_number', '62878455351641')),
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
