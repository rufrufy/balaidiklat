<?php

namespace App\Observers;

use App\Models\KamarReservasi;
use App\Services\KirimChatService;

class KamarReservasiObserver
{
    public function updated(KamarReservasi $reservasi): void
    {
        // Kirim notifikasi WA ketika pembayaran berubah jadi lunas
        if ($reservasi->wasChanged('payment_status') && $reservasi->payment_status === 'paid') {
            $this->sendPaymentNotification($reservasi);
        }
    }

    private function sendPaymentNotification(KamarReservasi $reservasi): void
    {
        $phone = $reservasi->phone_number;
        if (! $phone) {
            return;
        }

        $hargaText = number_format((int) $reservasi->total_harga, 0, ',', '.');
        $message = "✅ Pembayaran Anda telah dikonfirmasi LUNAS!\n\n"
            ."Kode booking: {$reservasi->kode}\n"
            ."Total: Rp{$hargaText}\n"
            ."Status: LUNAS\n\n"
            ."Terima kasih telah melakukan pembayaran. Silakan datang sesuai jadwal.\n"
            ."Ketik *menu* untuk kembali ke menu utama.";

        try {
            $kirimChat = app(KirimChatService::class);
            $kirimChat->sendText($phone, $message);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Gagal kirim notifikasi pembayaran lunas', [
                'reservasi_id' => $reservasi->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
