<?php

namespace App\Services;

use App\Models\RetribusiBilling;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

        $endpoint = $baseUrl.$qrisPath;
        $fullKodebayar = '73'.$kodebayar;
        $basicAuth = base64_encode($user.':'.$pass);

        $payload = json_encode(['kodebayar' => $fullKodebayar]);

        $maxAttempts = 3;
        $result = null;
        $rawResponse = '';
        $httpStatus = 0;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $endpoint,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic '.$basicAuth,
                ],
            ]);

            $rawResponse = (string) curl_exec($curl);
            $httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);

            $result = json_decode($rawResponse, true) ?? [
                'status' => $httpStatus,
                'body' => $rawResponse,
            ];

            $respCode = $result['resp_code'] ?? null;
            $linkQris = $result['url'] ?? null;

            Log::info('Bapenda QRIS cURL response', [
                'kodebayar' => $fullKodebayar,
                'attempt' => $attempt.'/'.$maxAttempts,
                'http_status' => $httpStatus,
                'resp_code' => $respCode,
                'resp_desc' => $result['resp_desc'] ?? null,
                'url_returned' => $linkQris,
                'raw_body' => $rawResponse,
                'curl_error' => $curlError !== '' ? $curlError : null,
                'endpoint' => $endpoint,
            ]);

            if ($respCode === '00' && ! empty($linkQris)) {
                return ['success' => true, 'response' => $result, 'link_qris' => $linkQris];
            }

            if ($curlError !== '' || $httpStatus >= 400) {
                Log::error('Bapenda QRIS cURL error', [
                    'kodebayar' => $fullKodebayar,
                    'http_status' => $httpStatus,
                    'curl_error' => $curlError,
                    'raw_body' => $rawResponse,
                ]);

                return ['success' => false, 'message' => 'Gagal mendapatkan link QRIS (HTTP '.$httpStatus.'): '.$curlError, 'response' => $result];
            }

            if ($attempt < $maxAttempts) {
                sleep(2);
            }
        }

        Log::warning('Bapenda QRIS cURL failed after '.$maxAttempts.' attempts', [
            'kodebayar' => $fullKodebayar,
            'resp_code' => $result['resp_code'] ?? null,
            'resp_desc' => $result['resp_desc'] ?? null,
        ]);

        return [
            'success' => false,
            'message' => 'Bapenda gagal generate QRIS setelah '.$maxAttempts.' percobaan: '.($result['resp_desc'] ?? 'Unknown error'),
            'response' => $result,
        ];
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

        $linkQris = $result['link_qris']
            ?? ($result['response']['url'] ?? null);

        $updateData = [
            'qris_response_payload' => $result['response'],
        ];

        if ($linkQris) {
            $updateData['link_qris'] = $linkQris;
        }

        $billing->update($updateData);

        return ['success' => true, 'link_qris' => $linkQris, 'response' => $result['response']];
    }

    /**
     * Check billing status di Bapenda e-Retribusi.
     * POST /api/v2/prod/retribusi/check?id_billing=xxxx
     */
    public function checkBilling(string $idBilling): array
    {
        $baseUrl = (string) config('services.bapenda.base_url');
        $vcode = (string) config('services.bapenda.vcode');
        $token = (string) config('services.bapenda.token');

        if ($baseUrl === '' || $token === '' || $vcode === '') {
            return ['success' => false, 'message' => 'Bapenda API credentials belum dikonfigurasi.'];
        }

        $endpoint = rtrim($baseUrl, '/').'/api/v2/prod/retribusi/check?id_billing='.urlencode($idBilling);

        $response = Http::withHeaders([
            'vcode' => $vcode,
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ])
            ->timeout(30)
            ->post($endpoint);

        $result = $response->json() ?? [
            'status' => $response->status(),
            'body' => $response->body(),
        ];

        if ($response->failed()) {
            Log::error('Bapenda check API error', [
                'id_billing' => $idBilling,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'message' => 'Gagal check status billing.', 'response' => $result];
        }

        return ['success' => true, 'response' => $result];
    }

    /**
     * Delete billing di Bapenda e-Retribusi.
     * POST /api/v2/prod/retribusi/delete?id_billing=xxxx
     */
    public function deleteBilling(string $idBilling): array
    {
        $baseUrl = (string) config('services.bapenda.base_url');
        $vcode = (string) config('services.bapenda.vcode');
        $token = (string) config('services.bapenda.token');

        if ($baseUrl === '' || $token === '' || $vcode === '') {
            return ['success' => false, 'message' => 'Bapenda API credentials belum dikonfigurasi.'];
        }

        $endpoint = rtrim($baseUrl, '/').'/api/v2/prod/retribusi/delete?id_billing='.urlencode($idBilling);

        $response = Http::withHeaders([
            'vcode' => $vcode,
            'Authorization' => 'Bearer '.$token,
            'Content-Type' => 'application/json',
        ])
            ->timeout(30)
            ->post($endpoint);

        $result = $response->json() ?? [
            'status' => $response->status(),
            'body' => $response->body(),
        ];

        if ($response->failed()) {
            Log::error('Bapenda delete API error', [
                'id_billing' => $idBilling,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return ['success' => false, 'message' => 'Gagal menghapus billing di Bapenda.', 'response' => $result];
        }

        Log::info('Bapenda billing deleted', ['id_billing' => $idBilling]);

        return ['success' => true, 'response' => $result];
    }

    /**
     * Download QRIS image from Bank Jateng CDN via the API page.
     * 1. Fetch the Bank Jateng QRIS page (HTML)
     * 2. Extract the PNG URL from <img id="qrResults">
     * 3. Download the PNG and save to storage
     * 4. Update billing's link_qris_image field
     */
    public function downloadQrisImage(RetribusiBilling $billing): ?string
    {
        $linkQris = $billing->link_qris;
        if (! $linkQris) {
            return null;
        }

        try {
            $cleanUrl = (string) preg_replace('#(?<!:)/+#', '/', $linkQris);

            $browserHeaders = [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/149.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/*,*/*;q=0.8',
                'Accept-Language' => 'id',
            ];

            $response = Http::timeout(30)
                ->withHeaders($browserHeaders)
                ->withOptions(['verify' => true, 'allow_redirects' => ['max' => 5]])
                ->get($cleanUrl);

            if ($response->failed()) {
                Log::warning('QRIS page fetch failed', [
                    'billing_id' => $billing->id,
                    'url' => $cleanUrl,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $body = $response->body();
            $contentType = $response->header('Content-Type') ?? '';

            if (empty($body)) {
                Log::warning('QRIS page response empty', ['billing_id' => $billing->id, 'url' => $cleanUrl]);

                return null;
            }

            if (str_contains($contentType, 'image/') || str_starts_with($body, "\x89PNG") || str_starts_with($body, "\xFF\xD8\xFF")) {
                return $this->saveQrisToStorage($body, $contentType, $billing);
            }

            preg_match('/<img[^>]+id="qrResults"[^>]+src="([^"]+)"/i', $body, $matches);
            $pngUrl = $matches[1] ?? null;

            if (! $pngUrl) {
                preg_match('#/uploads/(\d+\.png)#i', $body, $fallbackMatches);
                $pngUrl = $fallbackMatches[1]
                    ? 'https://bimaqr.bankjateng.co.id/uploads/'.$fallbackMatches[1]
                    : null;
            }

            if (! $pngUrl) {
                Log::warning('QRIS page has no PNG image', [
                    'billing_id' => $billing->id,
                    'url' => $cleanUrl,
                    'body_preview' => substr($body, 0, 500),
                ]);

                return null;
            }

            $cleanPngUrl = (string) preg_replace('#(?<!:)/+#', '/', $pngUrl);

            $imageResponse = Http::timeout(30)
                ->withHeaders([
                    'User-Agent' => $browserHeaders['User-Agent'],
                    'Accept' => 'image/*',
                    'Accept-Language' => 'id',
                    'Referer' => 'https://bimaqr.bankjateng.co.id/',
                ])
                ->withOptions(['verify' => true, 'allow_redirects' => ['max' => 5]])
                ->get($cleanPngUrl);

            if ($imageResponse->failed()) {
                Log::warning('QRIS PNG download failed', [
                    'billing_id' => $billing->id,
                    'png_url' => $cleanPngUrl,
                    'status' => $imageResponse->status(),
                ]);

                return null;
            }

            $pngBody = $imageResponse->body();
            $pngContentType = $imageResponse->header('Content-Type') ?? '';

            if (empty($pngBody)) {
                Log::warning('QRIS PNG body empty', ['billing_id' => $billing->id, 'png_url' => $cleanPngUrl]);

                return null;
            }

            return $this->saveQrisToStorage($pngBody, $pngContentType, $billing);
        } catch (\Throwable $e) {
            Log::error('QRIS image download error', [
                'billing_id' => $billing->id,
                'link' => $linkQris,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function saveQrisToStorage(string $body, string $contentType, RetribusiBilling $billing): ?string
    {
        $ext = str_contains($contentType, 'png') || str_starts_with($body, "\x89PNG")
            ? 'png'
            : (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg') || str_starts_with($body, "\xFF\xD8\xFF") ? 'jpg' : 'png');
        $filename = 'qris/'.($billing->no_ketetapan ?: 'billing-'.$billing->id).'-'.Str::random(8).'.'.$ext;

        Storage::disk('public')->put($filename, $body);

        $url = asset('storage/'.$filename);

        $billing->update(['link_qris_image' => $url]);

        Log::info('QRIS image saved to storage', [
            'billing_id' => $billing->id,
            'filename' => $filename,
            'size' => strlen($body),
            'url' => $url,
        ]);

        return $url;
    }
}
