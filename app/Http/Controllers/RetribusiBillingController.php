<?php

namespace App\Http\Controllers;

use App\Models\KamarReservasi;
use App\Models\RetribusiBilling;
use App\Services\ERetribusiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RetribusiBillingController extends Controller
{
    public function store(Request $request, KamarReservasi $reservasi): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['kamar_reservasi_id'] = $reservasi->id;

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
        $result = $service->send($billing);

        $message = $result['success']
            ? 'Billing berhasil dikirim ke eRetribusi.'
            : ($result['message'] ?? 'Billing gagal dikirim.');

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])->with('status', $message);
    }

    /**
     * Public API for the external eRetribusi app to pull the billing payload.
     */
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
        ]);
    }
}
