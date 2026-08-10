<?php

namespace App\Http\Controllers;

use App\Models\KamarReservasi;
use App\Models\RetribusiBilling;
use App\Services\KirimChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BapendaCallbackController extends Controller
{
    public function paymentCallback(Request $request, KirimChatService $kirimChat): JsonResponse
    {
        $this->validateCallbackToken($request);

        $payload = $request->all();

        Log::info('Bapenda payment callback received', ['payload' => $payload]);

        $kodebayar = $this->extractKodebayar($payload);
        $status = $this->extractStatus($payload);

        if (! $kodebayar) {
            Log::warning('Bapenda callback: kodebayar not found', ['payload' => $payload]);

            return response()->json(['success' => false, 'message' => 'kodebayar tidak ditemukan.'], 422);
        }

        $billing = RetribusiBilling::where('kodebayar', $kodebayar)
            ->orWhere('id_billing', $kodebayar)
            ->first();

        if (! $billing) {
            Log::warning('Bapenda callback: billing not found', ['kodebayar' => $kodebayar]);

            return response()->json(['success' => false, 'message' => 'Billing tidak ditemukan.'], 404);
        }

        if ($billing->isPaid()) {
            Log::info('Bapenda callback: billing already paid, skipping', ['billing_id' => $billing->id]);

            return response()->json(['success' => true, 'message' => 'Billing sudah berstatus terbayar.']);
        }

        $isPaid = $this->isStatusPaid($status);

        if (! $isPaid) {
            $billing->update(['payment_callback_status' => $status]);

            return response()->json(['success' => true, 'message' => 'Status pembayaran diperbarui: '.$status]);
        }

        $billing->update([
            'payment_callback_status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->updateReservasiPayment($billing, $kirimChat);

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran terkonfirmasi. Status reservasi diperbarui menjadi LUNAS.',
            'data' => [
                'billing_id' => $billing->id,
                'kodebayar' => $billing->kodebayar,
                'paid_at' => $billing->paid_at?->toIso8601String(),
            ],
        ]);
    }

    private function validateCallbackToken(Request $request): void
    {
        $expected = (string) config('services.bapenda.callback_token');

        if ($expected === '') {
            abort(500, 'BAPENDA_CALLBACK_TOKEN belum dikonfigurasi.');
        }

        $token = $request->header('X-Bapenda-Token')
            ?? $request->header('Authorization')
            ?? $request->query('token');

        if ($token && Str::startsWith($token, 'Bearer ')) {
            $token = Str::after($token, 'Bearer ');
        }

        if (! $token || ! hash_equals($expected, (string) $token)) {
            abort(401, 'Invalid callback token');
        }
    }

    private function extractKodebayar(array $payload): ?string
    {
        $candidates = [
            $payload['kodebayar'] ?? null,
            $payload['id_billing'] ?? null,
            $payload['data']['kodebayar'] ?? null,
            $payload['data']['id_billing'] ?? null,
            $payload['no_ketetapan'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '') {
                return (string) $candidate;
            }
        }

        return null;
    }

    private function extractStatus(array $payload): ?string
    {
        return $payload['status']
            ?? $payload['payment_status']
            ?? $payload['data']['status']
            ?? $payload['data']['payment_status']
            ?? $payload['resp_code']
            ?? null;
    }

    private function isStatusPaid(?string $status): bool
    {
        if ($status === null) {
            return false;
        }

        $paidValues = ['paid', 'lunas', 'success', '00', 'settlement', 'completed'];
        $normalized = Str::lower(trim($status));

        return in_array($normalized, $paidValues, true);
    }

    private function updateReservasiPayment(RetribusiBilling $billing, KirimChatService $kirimChat): void
    {
        $reservasi = $billing->reservasi;

        if (! $reservasi) {
            Log::warning('Bapenda callback: reservasi not found for billing', ['billing_id' => $billing->id]);

            return;
        }

        $reservasi->update(['payment_status' => 'paid']);

        Log::info('Reservasi payment_status updated to paid', [
            'reservasi_id' => $reservasi->id,
            'kode' => $reservasi->kode,
            'billing_id' => $billing->id,
        ]);

        $this->sendWhatsAppNotification($reservasi, $kirimChat);
    }

    private function sendWhatsAppNotification(KamarReservasi $reservasi, KirimChatService $kirimChat): void
    {
        $phone = $reservasi->phone_number;

        if (! $phone) {
            Log::info('Bapenda callback: no phone number, skipping WA notification', [
                'reservasi_id' => $reservasi->id,
            ]);

            return;
        }

        $kode = $reservasi->kode;
        $nama = $reservasi->nama_pemesan ?? 'Bapak/Ibu';
        $total = $reservasi->total_harga ?? 0;
        $totalText = number_format($total, 0, ',', '.');

        $message = "Pembayaran Terkonfirmasi ✅\n\n"
            ."Yth. {$nama},\n\n"
            ."Pembayaran untuk reservasi dengan kode *{$kode}* telah kami terima dan terverifikasi.\n"
            ."Total: Rp{$totalText}\n"
            ."Status: *LUNAS*\n\n"
            ."Reservasi Anda sedang diproses. Terima kasih telah mempercayakan layanan kami.\n\n"
            ."— Balai Diklat BKPP Kota Semarang";

        try {
            $kirimChat->sendText($phone, $message);

            Log::info('WA notification sent for paid reservasi', [
                'reservasi_id' => $reservasi->id,
                'phone' => $phone,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send WA notification for paid reservasi', [
                'reservasi_id' => $reservasi->id,
                'phone' => $phone,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
