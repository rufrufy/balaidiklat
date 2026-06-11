<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\KamarReservasi;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReservasiController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);
        $data['kode'] = $this->generateKode();

        DB::transaction(function () use ($data, $request): void {
            $reservasi = KamarReservasi::create($this->reservationPayload($data, $request->boolean('multiple_kamar')));
            $this->syncItems($reservasi, $data, $request);
        });

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
        $reservasi->delete();

        return redirect()->route('admin.dashboard', ['section' => 'reservasi'])->with('status', 'Reservasi berhasil dihapus.');
    }

    private function validatedData(Request $request): array
    {
        return $request->validate([
            'nama_pemesan' => ['required', 'string', 'max:255'],
            'instansi' => ['nullable', 'string', 'max:255'],
            'kegiatan' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'kamar_id' => ['nullable', 'exists:kamars,id'],
            'tanggal_masuk' => ['nullable', 'date'],
            'tanggal_keluar' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
            'jumlah_peserta' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'payment_status' => ['required', 'in:unpaid,partial,paid'],
            'catatan' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.kamar_id' => ['nullable', 'exists:kamars,id'],
            'items.*.tanggal_masuk' => ['nullable', 'date'],
            'items.*.tanggal_keluar' => ['nullable', 'date'],
        ]);
    }

    private function reservationPayload(array $data, bool $multipleKamar): array
    {
        $duration = $this->duration($data['tanggal_masuk'] ?? null, $data['tanggal_keluar'] ?? null);
        $kamar = $multipleKamar ? null : Kamar::find($data['kamar_id'] ?? null);
        $total = $multipleKamar ? 0 : (($kamar?->harga_per_malam ?? 0) * $duration);

        return [
            'kode' => $data['kode'],
            'nama_pemesan' => $data['nama_pemesan'],
            'instansi' => $data['instansi'] ?? null,
            'kegiatan' => $data['kegiatan'],
            'phone_number' => $data['phone_number'] ?? null,
            'kamar_id' => $multipleKamar ? null : ($data['kamar_id'] ?? null),
            'multiple_kamar' => $multipleKamar,
            'tanggal_masuk' => $data['tanggal_masuk'] ?? null,
            'tanggal_keluar' => $data['tanggal_keluar'] ?? null,
            'durasi_hari' => $duration,
            'jumlah_peserta' => $data['jumlah_peserta'],
            'total_harga' => $total,
            'status' => $data['status'],
            'payment_status' => $data['payment_status'],
            'catatan' => $data['catatan'] ?? null,
        ];
    }

    private function syncItems(KamarReservasi $reservasi, array $data, Request $request): void
    {
        $items = $request->boolean('multiple_kamar') ? ($data['items'] ?? []) : [[
            'kamar_id' => $data['kamar_id'] ?? null,
            'tanggal_masuk' => $data['tanggal_masuk'] ?? null,
            'tanggal_keluar' => $data['tanggal_keluar'] ?? null,
        ]];

        $total = 0;

        foreach ($items as $item) {
            if (empty($item['kamar_id']) || empty($item['tanggal_masuk']) || empty($item['tanggal_keluar'])) {
                continue;
            }

            $kamar = Kamar::find($item['kamar_id']);

            if (! $kamar) {
                continue;
            }

            $duration = $this->duration($item['tanggal_masuk'], $item['tanggal_keluar']);
            $subtotal = $kamar->harga_per_malam * $duration;
            $total += $subtotal;

            $reservasi->items()->create([
                'kamar_id' => $kamar->id,
                'tanggal_masuk' => $item['tanggal_masuk'],
                'tanggal_keluar' => $item['tanggal_keluar'],
                'durasi_hari' => $duration,
                'harga_per_malam' => $kamar->harga_per_malam,
                'subtotal' => $subtotal,
            ]);
        }

        $reservasi->update(['total_harga' => $total ?: $reservasi->total_harga]);
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
            $kode = 'RSV-'.now()->format('YmdHis').'-'.random_int(100, 999);
        } while (KamarReservasi::where('kode', $kode)->exists());

        return $kode;
    }
}
