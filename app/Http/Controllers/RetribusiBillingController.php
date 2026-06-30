<?php

namespace App\Http\Controllers;

use App\Models\KamarReservasi;
use App\Models\RetribusiBilling;
use App\Services\ERetribusiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RetribusiBillingController extends Controller
{
    public function store(Request $request, KamarReservasi $reservasi): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['kamar_reservasi_id'] = $reservasi->id;

        $this->populateBapendaDefaults($data, $reservasi);

        RetribusiBilling::create($data);

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])
            ->with('status', 'Billing eRetribusi berhasil dibuat.');
    }

    public function update(Request $request, RetribusiBilling $billing): RedirectResponse
    {
        $billing->update($this->validatedData($request));

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])
            ->with('status', 'Billing eRetribusi berhasil diperbarui.');
    }

    public function send(RetribusiBilling $billing, ERetribusiService $service): RedirectResponse
    {
        $result = $service->sendBapendaBilling($billing);

        $message = $result['success']
            ? 'Billing berhasil dikirim ke Bapenda e-Retribusi. ID Billing: '.($billing->fresh()->id_billing ?? '-')
            : ($result['message'] ?? 'Billing gagal dikirim.');

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])->with('status', $message);
    }

    public function fetchQris(RetribusiBilling $billing, ERetribusiService $service): JsonResponse
    {
        try {
            if ($billing->link_qris) {
                $imageUrl = $billing->link_qris_image;

                if (! $imageUrl) {
                    $imageUrl = $this->downloadQrisImage($billing->link_qris, $billing);
                }

                return response()->json([
                    'success' => true,
                    'link_qris' => $billing->link_qris,
                    'image_url' => $imageUrl,
                ]);
            }

            $result = $service->fetchAndSaveQris($billing);

            if ($result['success'] && isset($result['link_qris'])) {
                $imageUrl = $this->downloadQrisImage($result['link_qris'], $billing);
                $result['image_url'] = $imageUrl;
            }
        } catch (\Throwable $e) {
            Log::error('fetchQris exception', [
                'billing_id' => $billing->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal terhubung ke server QRIS: '.$e->getMessage(),
            ], 500);
        }

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    private function downloadQrisImage(string $linkQris, RetribusiBilling $billing): ?string
    {
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
                Log::warning('QRIS page response empty', ['url' => $cleanUrl]);

                return null;
            }

            // If response is already an image (direct CDN URL), save it directly
            if (str_contains($contentType, 'image/') || str_starts_with($body, "\x89PNG") || str_starts_with($body, "\xFF\xD8\xFF")) {
                return $this->saveQrisImage($body, $contentType, $billing->no_ketetapan, $billing->id);
            }

            // Otherwise, it's an HTML page from Bank Jateng API. Extract PNG URL from <img id="qrResults">
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
                Log::warning('QRIS PNG body empty', ['png_url' => $cleanPngUrl]);

                return null;
            }

            return $this->saveQrisImage($pngBody, $pngContentType, $billing->no_ketetapan, $billing->id);
        } catch (\Throwable $e) {
            Log::error('fetchQris download image error', [
                'billing_id' => $billing->id,
                'link' => $linkQris,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function saveQrisImage(string $body, string $contentType, string $prefix, int $billingId): ?string
    {
        $ext = str_contains($contentType, 'png') || str_starts_with($body, "\x89PNG")
            ? 'png'
            : (str_contains($contentType, 'jpeg') || str_contains($contentType, 'jpg') || str_starts_with($body, "\xFF\xD8\xFF") ? 'jpg' : 'png');
        $filename = 'qris/'.$prefix.'-'.Str::random(8).'.'.$ext;

        Storage::disk('public')->put($filename, $body);

        $url = asset('storage/'.$filename);

        RetribusiBilling::where('id', $billingId)->update(['link_qris_image' => $url]);

        Log::info('QRIS image saved', [
            'billing_id' => $billingId,
            'filename' => $filename,
            'size' => strlen($body),
        ]);

        return $url;
    }

    public function apiShow(RetribusiBilling $billing): JsonResponse
    {
        return response()->json($billing->toRetribusiPayload());
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'tanggal' => ['required', 'date'],
            'keterangan' => ['required', 'string', 'max:255'],
            'kredit' => ['required', 'integer', 'min:0'],
            'noskpd' => ['nullable', 'string', 'max:50'],
            'periode' => ['nullable', 'string', 'max:10'],
            'sts_ssrd' => ['nullable', 'string', 'max:50'],
            'namapenyetor' => ['nullable', 'string', 'max:255'],
            't_nama' => ['nullable', 'string', 'max:255'],
            'npwrd' => ['nullable', 'string', 'max:50'],
            'rekening' => ['nullable', 'string', 'max:255'],
            // Bapenda fields
            'no_ketetapan' => ['nullable', 'string', 'max:50'],
            'nominal' => ['nullable', 'integer', 'min:0'],
            'tahun' => ['nullable', 'string', 'max:4'],
            'tgl_expired' => ['nullable', 'date'],
            'nama_wr' => ['nullable', 'string', 'max:255'],
            'keterangan_bapenda' => ['nullable', 'string', 'max:255'],
        ]);
    }

    private function populateBapendaDefaults(array &$data, KamarReservasi $reservasi): void
    {
        $data['nominal'] = $data['nominal'] ?? $reservasi->total_harga ?? $data['kredit'] ?? 0;
        $data['tahun'] = $data['tahun'] ?? (string) now()->year;
        $data['tgl_expired'] = $data['tgl_expired'] ?? now()->addDays(7)->format('Y-m-d');
        $data['nama_wr'] = $data['nama_wr'] ?? $reservasi->nama_pemesan ?? 'BKPP';
        $data['no_ketetapan'] = $data['no_ketetapan'] ?? 'A'.$reservasi->id;

        $jenisKelas = $reservasi->jenis_kelas ?? '-';
        $durasi = $reservasi->durasi_hari ?? 1;
        $data['keterangan_bapenda'] = $data['keterangan_bapenda'] ?? "Sewa {$jenisKelas} selama {$durasi} hari";
    }

    public function checkStatus(RetribusiBilling $billing, ERetribusiService $service): JsonResponse
    {
        if (! $billing->id_billing) {
            return response()->json(['success' => false, 'message' => 'Billing belum memiliki id_billing.'], 422);
        }

        $result = $service->checkBilling((string) $billing->id_billing);

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $tglBayar = $data['tgl_bayar'] ?? null;

            if (! empty($tglBayar)) {
                if (! $billing->isPaid()) {
                    $paidAt = \Illuminate\Support\Carbon::parse($tglBayar);

                    $billing->update([
                        'payment_callback_status' => 'paid',
                        'paid_at' => $paidAt,
                    ]);

                    if ($billing->reservasi) {
                        $billing->reservasi->update(['payment_status' => 'paid']);
                    }
                }

                $result['payment_status'] = 'paid';
                $result['paid_at'] = $tglBayar;
            } else {
                $result['payment_status'] = 'unpaid';
            }
        }

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function destroyBilling(RetribusiBilling $billing, ERetribusiService $service): JsonResponse
    {
        if (! $billing->id_billing) {
            return response()->json(['success' => false, 'message' => 'Billing belum memiliki id_billing.'], 422);
        }

        $result = $service->deleteBilling((string) $billing->id_billing);

        if ($result['success']) {
            $billing->update([
                'status' => 'deleted',
                'payment_callback_status' => 'deleted',
            ]);
        }

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
