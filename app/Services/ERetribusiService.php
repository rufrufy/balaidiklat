<?php

namespace App\Services;

use App\Models\RetribusiBilling;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ERetribusiService
{
    /**
     * Push a retribusi billing record to the external eRetribusi app and
     * record the delivery result on the model.
     */
    public function send(RetribusiBilling $billing): array
    {
        $baseUrl = (string) config('services.eretribusi.base_url');
        $path = (string) config('services.eretribusi.send_path', '/api/billing');
        $payload = $billing->toRetribusiPayload();

        if ($baseUrl === '') {
            Log::warning('eRetribusi base URL not configured; billing not sent.', [
                'billing_id' => $billing->id,
            ]);

            $billing->update(['status' => 'failed']);

            return ['success' => false, 'message' => 'ERETRIBUSI_BASE_URL belum dikonfigurasi.'];
        }

        $response = Http::withToken((string) config('services.eretribusi.api_key'))
            ->acceptJson()
            ->asJson()
            ->post(rtrim($baseUrl, '/').$path, $payload);

        $result = $response->json() ?? [
            'status' => $response->status(),
            'body' => $response->body(),
        ];

        if ($response->failed()) {
            Log::error('eRetribusi API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            $billing->update([
                'status' => 'failed',
                'response_payload' => $result,
            ]);

            return ['success' => false, 'message' => 'Gagal mengirim ke eRetribusi.', 'response' => $result];
        }

        $billing->update([
            'status' => 'sent',
            'response_payload' => $result,
            'sent_at' => now(),
        ]);

        return ['success' => true, 'response' => $result];
    }
}
