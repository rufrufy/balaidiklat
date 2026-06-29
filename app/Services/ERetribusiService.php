<?php

namespace App\Services;

use App\Models\RetribusiBilling;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ERetribusiService
{
    public function send(RetribusiBilling $billing): array
    {
        return $this->sendBapendaBilling($billing);
    }

    /**
     * Push billing ke Bapenda e-Retribusi /api/v2/prod/retribusi/store.
     * Simpan response (id_billing, link_ssrd) ke billing record.
     */
    public function sendBapendaBilling(RetribusiBilling $billing): array
    {
        $baseUrl = (string) config('services.bapenda.base_url');
        $storePath = (string) config('services.bapenda.store_path', '/api/v2/prod/retribusi/store');
        $vcode = (string) config('services.bapenda.vcode');
        $token = (string) config('services.bapenda.token');

        if ($baseUrl === '' || $token === '' || $vcode === '') {
            Log::warning('Bapenda API not configured; billing not sent.', ['billing_id' => $billing->id]);

            $billing->update(['status' => 'failed']);

            return ['success' => false, 'message' => 'Bapenda API credentials belum dikonfigurasi.'];
        }

        $payload = $billing->toBapendaStorePayload();
        $endpoint = rtrim($baseUrl, '/').$storePath;

        $response = Http::withHeaders([
            'vcode' => $vcode,
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ])
            ->timeout(30)
            ->post($endpoint.'?'.http_build_query($payload));

        $result = $response->json() ?? [
            'status' => $response->status(),
            'body' => $response->body(),
        ];

        if ($response->failed()) {
            Log::error('Bapenda store API error', [
                'billing_id' => $billing->id,
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            $billing->update([
                'status' => 'failed',
                'response_payload' => $result,
            ]);

            return ['success' => false, 'message' => 'Gagal mengirim ke Bapenda e-Retribusi.', 'response' => $result];
        }

        $data = $result['data'] ?? [];
        $updateData = [
            'status' => 'sent',
            'response_payload' => $result,
            'sent_at' => now(),
        ];

        if (isset($data['id_billing'])) {
            $updateData['id_billing'] = $data['id_billing'];
            $updateData['kodebayar'] = (string) $data['id_billing'];
        }
        if (isset($data['link_ssrd'])) {
            $updateData['link_ssrd'] = $data['link_ssrd'];
        }
        if (isset($data['no_ketetapan'])) {
            $updateData['no_ketetapan'] = $data['no_ketetapan'];
        }

        $billing->update($updateData);

        Log::info('Bapenda billing sent', [
            'billing_id' => $billing->id,
            'id_billing' => $data['id_billing'] ?? null,
            'link_ssrd' => $data['link_ssrd'] ?? null,
        ]);

        return ['success' => true, 'response' => $result, 'data' => $data];
    }

    /**
     * Get QRIS link from Bapenda /api/bapenda/getLinkQris using basic auth.
     */
    public function getQrisLink(string $kodebayar): array
    {
        $baseUrl = (string) config('services.bapenda.qris_base_url');
        $qrisPath = (string) config('services.bapenda.qris_path', '/api/bapenda/getLinkQris');
        $user = (string) config('services.bapenda.qris_user');
        $pass = (string) config('services.bapenda.qris_pass');

        if ($baseUrl === '' || $user === '' || $pass === '') {
            return ['success' => false, 'message' => 'Bapenda QRIS credentials belum dikonfigurasi.'];
        }

        $endpoint = rtrim($baseUrl, '/').$qrisPath;

        $response = Http::withBasicAuth($user, $pass)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(30)
            ->post($endpoint, [
                'kodebayar' => $kodebayar,
            ]);

        $result = $response->json() ?? [
            'status' => $response->status(),
            'body' => $response->body(),
        ];

        if ($response->failed()) {
            Log::error('Bapenda QRIS API error', [
                'kodebayar' => $kodebayar,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'message' => 'Gagal mendapatkan link QRIS.', 'response' => $result];
        }

        return ['success' => true, 'response' => $result];
    }

    /**
     * Fetch & persist QRIS link for a billing record.
     */
    public function fetchAndSaveQris(RetribusiBilling $billing): array
    {
        if (! $billing->kodebayar) {
            return ['success' => false, 'message' => 'Billing belum memiliki kodebayar. Kirim ke Bapenda dahulu.'];
        }

        $result = $this->getQrisLink($billing->kodebayar);

        if (! $result['success']) {
            return $result;
        }

        $updateData = [
            'qris_response_payload' => $result['response'],
        ];

        $linkQris = $result['response']['data']['link_qris']
            ?? $result['response']['link_qris']
            ?? $result['response']['data']['link']
            ?? null;

        if ($linkQris) {
            $updateData['link_qris'] = $linkQris;
        }

        $billing->update($updateData);

        return ['success' => true, 'link_qris' => $linkQris, 'response' => $result['response']];
    }
}
