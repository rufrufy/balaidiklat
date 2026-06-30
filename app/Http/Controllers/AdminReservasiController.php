<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\KamarReservasi;
use App\Models\RetribusiBilling;
use App\Services\ERetribusiService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminReservasiController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['kode'] = $this->generateKode();

        $reservasi = null;

        DB::transaction(function () use ($data, $request, &$reservasi): void {
            $reservasi = KamarReservasi::create($this->reservationPayload($data, $request->boolean('multiple_kamar')));
            $this->syncItems($reservasi, $data, $request);
        });

        if ($reservasi) {
            $this->createAndSendBapendaBilling($reservasi);
        }

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])->with('status', 'Reservasi berhasil ditambahkan.');
    }

    public function update(Request $request, KamarReservasi $reservasi): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['kode'] = $reservasi->kode;

        DB::transaction(function () use ($reservasi, $data, $request): void {
            $reservasi->update($this->reservationPayload($data, $request->boolean('multiple_kamar')));
            $reservasi->items()->delete();
            $this->syncItems($reservasi, $data, $request);
        });

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])->with('status', 'Reservasi berhasil diperbarui.');
    }

    public function destroy(KamarReservasi $reservasi): RedirectResponse
    {
        $this->deleteBillingsFromBapenda($reservasi);

        $reservasi->delete();

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])->with('status', 'Reservasi berhasil dihapus.');
    }

    private function deleteBillingsFromBapenda(KamarReservasi $reservasi): void
    {
        $billings = $reservasi->retribusiBillings()
            ->whereNotNull('id_billing')
            ->whereIn('status', ['draft', 'sent', 'failed'])
            ->where(function ($q): void {
                $q->where('payment_callback_status', '!=', 'paid')
                    ->orWhereNull('payment_callback_status');
            })
            ->get();

        if ($billings->isEmpty()) {
            return;
        }

        $service = app(ERetribusiService::class);

        foreach ($billings as $billing) {
            try {
                $result = $service->deleteBilling((string) $billing->id_billing);

                if ($result['success']) {
                    $billing->update(['status' => 'deleted', 'payment_callback_status' => 'deleted']);
                } else {
                    Log::warning('Failed to delete billing from Bapenda during reservasi destroy', [
                        'reservasi_id' => $reservasi->id,
                        'billing_id' => $billing->id,
                        'id_billing' => $billing->id_billing,
                        'error' => $result['message'] ?? 'Unknown',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Exception deleting billing from Bapenda during reservasi destroy', [
                    'reservasi_id' => $reservasi->id,
                    'billing_id' => $billing->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function togglePayment(KamarReservasi $reservasi): RedirectResponse
    {
        $reservasi->update([
            'payment_status' => $reservasi->payment_status === 'paid' ? 'unpaid' : 'paid',
        ]);

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])
            ->with('status', 'Status pembayaran diperbarui menjadi '.($reservasi->payment_status === 'paid' ? 'Lunas' : 'Belum dibayar').'.');
    }

    private function validatedData(Request $request): array
    {
        $isInstansi = $request->input('tipe_penyewa') === 'instansi';

        return $request->validate([
            'nama_pemesan' => ['required', 'string', 'max:255'],
            'tipe_penyewa' => ['required', 'in:perorangan,instansi'],
            'instansi' => [$isInstansi ? 'required' : 'nullable', 'string', 'max:255'],
            'kegiatan' => [$isInstansi ? 'required' : 'nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'kamar_id' => ['nullable', 'exists:kamars,id'],
            'jenis_kelas' => ['nullable', 'string', 'max:255'],
            'jumlah' => ['nullable', 'integer', 'min:1'],
            'tanggal_masuk' => ['nullable', 'date', 'after_or_equal:today'],
            'tanggal_keluar' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
            'jumlah_unit' => ['nullable', 'integer', 'min:1'],
            'jumlah_peserta' => [$isInstansi ? 'required' : 'nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'payment_status' => ['nullable', 'in:unpaid,partial,paid'],
            'catatan' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.kamar_id' => ['nullable', 'exists:kamars,id'],
            'items.*.jenis_kelas' => ['nullable', 'string', 'max:255'],
            'items.*.jumlah' => ['nullable', 'integer', 'min:1'],
            'items.*.tanggal_masuk' => ['nullable', 'date'],
            'items.*.tanggal_keluar' => ['nullable', 'date'],
            'items.*.jumlah_unit' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function reservationPayload(array $data, bool $multipleKamar): array
    {
        $duration = $this->duration($data['tanggal_masuk'] ?? null, $data['tanggal_keluar'] ?? null);
        $kamar = $multipleKamar ? null : $this->resolveKamar($data);
        $unit = $multipleKamar ? 1 : max(1, (int) ($data['jumlah'] ?? $data['jumlah_unit'] ?? 1));
        $isInstansi = ($data['tipe_penyewa'] ?? 'perorangan') === 'instansi';

        return [
            'kode' => $data['kode'],
            'nama_pemesan' => $data['nama_pemesan'],
            'tipe_penyewa' => $data['tipe_penyewa'] ?? 'perorangan',
            'instansi' => $isInstansi ? ($data['instansi'] ?? null) : null,
            'kegiatan' => $isInstansi ? ($data['kegiatan'] ?? null) : null,
            'phone_number' => $data['phone_number'] ?? null,
            'jenis_kelas' => $multipleKamar ? null : ($kamar?->jenis_kelas ?? ($data['jenis_kelas'] ?? null)),
            'kamar_id' => $multipleKamar ? null : ($kamar?->id ?? ($data['kamar_id'] ?? null)),
            'jumlah' => $multipleKamar ? 1 : $unit,
            'multiple_kamar' => $multipleKamar,
            'tanggal_masuk' => $data['tanggal_masuk'] ?? null,
            'tanggal_keluar' => $data['tanggal_keluar'] ?? null,
            'durasi_hari' => $duration,
            'jumlah_peserta' => $isInstansi ? ($data['jumlah_peserta'] ?? 1) : 1,
            'total_harga' => $this->calcTotal($data, $multipleKamar, $duration),
            'status' => $data['status'],
            'payment_status' => $data['payment_status'] ?? 'unpaid',
            'catatan' => $data['catatan'] ?? null,
        ];
    }

    private function resolveKamar(array $data): ?Kamar
    {
        if (! empty($data['kamar_id'])) {
            return Kamar::find($data['kamar_id']);
        }
        if (! empty($data['jenis_kelas'])) {
            return Kamar::where('jenis_kelas', $data['jenis_kelas'])->first();
        }

        return null;
    }

    private function resolveKamarFromItem(array $item): ?Kamar
    {
        if (! empty($item['kamar_id'])) {
            return Kamar::find($item['kamar_id']);
        }
        if (! empty($item['jenis_kelas'])) {
            return Kamar::where('jenis_kelas', $item['jenis_kelas'])->first();
        }

        return null;
    }

    private function calcTotal(array $data, bool $multipleKamar, int $duration): int
    {
        if ($multipleKamar) {
            $total = 0;
            foreach ($data['items'] ?? [] as $item) {
                $kamar = $this->resolveKamarFromItem($item);
                if (! $kamar) {
                    continue;
                }
                $dur = $this->duration($item['tanggal_masuk'] ?? null, $item['tanggal_keluar'] ?? null);
                $total += $kamar->harga_per_malam * ($item['jumlah'] ?? $item['jumlah_unit'] ?? 1) * $dur;
            }

            return $total;
        }

        $kamar = $this->resolveKamar($data);

        return ($kamar?->harga_per_malam ?? 0) * ($data['jumlah'] ?? $data['jumlah_unit'] ?? 1) * $duration;
    }

    private function syncItems(KamarReservasi $reservasi, array $data, Request $request): void
    {
        $items = $request->boolean('multiple_kamar') ? ($data['items'] ?? []) : [[
            'kamar_id' => $data['kamar_id'] ?? null,
            'jenis_kelas' => $data['jenis_kelas'] ?? null,
            'jumlah' => $data['jumlah'] ?? $data['jumlah_unit'] ?? 1,
            'tanggal_masuk' => $data['tanggal_masuk'] ?? null,
            'tanggal_keluar' => $data['tanggal_keluar'] ?? null,
        ]];

        $total = 0;

        foreach ($items as $item) {
            $kamar = $this->resolveKamarFromItem($item);
            if (! $kamar || empty($item['tanggal_masuk']) || empty($item['tanggal_keluar'])) {
                continue;
            }

            $duration = $this->duration($item['tanggal_masuk'], $item['tanggal_keluar']);
            $unit = (int) ($item['jumlah'] ?? $item['jumlah_unit'] ?? 1);
            if ($unit < 1) {
                $unit = 1;
            }
            $subtotal = $kamar->harga_per_malam * $duration * $unit;
            $total += $subtotal;

            $reservasi->items()->create([
                'jenis_kelas' => $kamar->jenis_kelas,
                'jumlah' => $unit,
                'tanggal_masuk' => $item['tanggal_masuk'],
                'tanggal_keluar' => $item['tanggal_keluar'],
                'durasi_hari' => $duration,
                'harga_per_malam' => $kamar->harga_per_malam,
                'subtotal' => $subtotal,
            ]);
        }

        if ($total > 0) {
            $reservasi->update(['total_harga' => $total]);
        }
    }

    private function duration(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 1;
        }

        return (int) max(1, Carbon::parse($start)->diffInDays(Carbon::parse($end)) ?: 1);
    }

    private function generateKode(): string
    {
        do {
            $kode = 'BKPP-'.now()->format('YmdHis').'-'.random_int(100, 999);
        } while (KamarReservasi::where('kode', $kode)->exists());

        return $kode;
    }

    private function createAndSendBapendaBilling(KamarReservasi $reservasi): void
    {
        try {
            $existing = RetribusiBilling::where('kamar_reservasi_id', $reservasi->id)
                ->where('status', 'sent')
                ->latest()
                ->first();

            if ($existing) {
                return;
            }

            $jenisKelas = $reservasi->jenis_kelas ?? '-';
            $durasi = $reservasi->durasi_hari ?? 1;

            $billing = RetribusiBilling::create([
                'kamar_reservasi_id' => $reservasi->id,
                'tanggal' => now(),
                'keterangan' => "Sewa {$jenisKelas} selama {$durasi} hari",
                'kredit' => $reservasi->total_harga ?? 0,
                'noskpd' => '1111',
                'periode' => (string) now()->year,
                'npwrd' => '-',
                'nama_wr' => $reservasi->nama_pemesan ?? 'BKPP',
                'no_ketetapan' => 'A'.$reservasi->id,
                'nominal' => $reservasi->total_harga ?? 0,
                'tahun' => (string) now()->year,
                'tgl_expired' => now()->addDays(7)->format('Y-m-d'),
                'keterangan_bapenda' => "Sewa {$jenisKelas} selama {$durasi} hari",
                'status' => 'draft',
            ]);

            $service = app(ERetribusiService::class);
            $result = $service->sendBapendaBilling($billing);

            if (! $result['success']) {
                Log::warning('Admin auto Bapenda billing failed (non-blocking)', [
                    'reservasi_id' => $reservasi->id,
                    'billing_id' => $billing->id,
                    'error' => $result['message'] ?? 'Unknown',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Admin auto Bapenda billing exception (non-blocking)', [
                'reservasi_id' => $reservasi->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function toggleStatus(Request $request, KamarReservasi $reservasi): RedirectResponse
    {
        $status = $request->input('status');
        $allowed = ['pending', 'approved', 'rejected'];
        if (! in_array($status, $allowed, true)) {
            return redirect()->route('admin.dashboard', ['section' => 'reservasi'])->withErrors(['status' => 'Status tidak valid.']);
        }
        $reservasi->update(['status' => $status]);

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])->with('status', "Status reservasi diubah menjadi \"{$status}\".");
    }
}
