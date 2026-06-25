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
            'jenis_kelas' => ['nullable', 'string', 'max:255'],
            'jumlah' => ['nullable', 'integer', 'min:1'],
            'tanggal_masuk' => ['nullable', 'date'],
            'tanggal_keluar' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
            'jumlah_peserta' => [$isInstansi ? 'required' : 'nullable', 'integer', 'min:1'],
            'status' => ['required', 'in:pending,approved,rejected'],
            'payment_status' => ['nullable', 'in:unpaid,partial,paid'],
            'catatan' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.jenis_kelas' => ['nullable', 'string', 'max:255'],
            'items.*.jumlah' => ['nullable', 'integer', 'min:1'],
            'items.*.tanggal_masuk' => ['nullable', 'date'],
            'items.*.tanggal_keluar' => ['nullable', 'date'],
        ]);
    }

    private function reservationPayload(array $data, bool $multipleKamar): array
    {
        $duration = $this->duration($data['tanggal_masuk'] ?? null, $data['tanggal_keluar'] ?? null);
        $isInstansi = ($data['tipe_penyewa'] ?? 'perorangan') === 'instansi';

        return [
            'kode' => $data['kode'],
            'nama_pemesan' => $data['nama_pemesan'],
            'tipe_penyewa' => $data['tipe_penyewa'] ?? 'perorangan',
            'instansi' => $isInstansi ? ($data['instansi'] ?? null) : null,
            'kegiatan' => $isInstansi ? ($data['kegiatan'] ?? null) : null,
            'phone_number' => $data['phone_number'] ?? null,
            'jenis_kelas' => $multipleKamar ? null : ($data['jenis_kelas'] ?? null),
            'jumlah' => $multipleKamar ? 1 : ($data['jumlah'] ?? 1),
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

    private function calcTotal(array $data, bool $multipleKamar, int $duration): int
    {
        if ($multipleKamar) {
            $total = 0;
            foreach ($data['items'] ?? [] as $item) {
                if (empty($item['jenis_kelas'])) {
                    continue;
                }
                $kamar = Kamar::where('jenis_kelas', $item['jenis_kelas'])->first();
                if (! $kamar) {
                    continue;
                }
                $dur = $this->duration($item['tanggal_masuk'] ?? null, $item['tanggal_keluar'] ?? null);
                $total += $kamar->harga_per_malam * ($item['jumlah'] ?? 1) * $dur;
            }

            return $total;
        }

        $kamar = Kamar::where('jenis_kelas', $data['jenis_kelas'] ?? '')->first();

        return ($kamar?->harga_per_malam ?? 0) * ($data['jumlah'] ?? 1) * $duration;
    }

    private function syncItems(KamarReservasi $reservasi, array $data, Request $request): void
    {
        $items = $request->boolean('multiple_kamar') ? ($data['items'] ?? []) : [[
            'jenis_kelas' => $data['jenis_kelas'] ?? null,
            'jumlah' => $data['jumlah'] ?? 1,
            'tanggal_masuk' => $data['tanggal_masuk'] ?? null,
            'tanggal_keluar' => $data['tanggal_keluar'] ?? null,
        ]];

        $total = 0;

        foreach ($items as $item) {
            if (empty($item['jenis_kelas']) || empty($item['tanggal_masuk']) || empty($item['tanggal_keluar'])) {
                continue;
            }

            $kamar = Kamar::where('jenis_kelas', $item['jenis_kelas'])->first();
            if (! $kamar) {
                continue;
            }

            $duration = $this->duration($item['tanggal_masuk'], $item['tanggal_keluar']);
            $jumlah = $item['jumlah'] ?? 1;
            $subtotal = $kamar->harga_per_malam * $jumlah * $duration;
            $total += $subtotal;

            $reservasi->items()->create([
                'jenis_kelas' => $item['jenis_kelas'],
                'jumlah' => $jumlah,
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
            $kode = 'RSV-'.now()->format('YmdHis').'-'.random_int(100, 999);
        } while (KamarReservasi::where('kode', $kode)->exists());

        return $kode;
    }
}
