<?php

namespace App\Services;

use App\Models\Kamar;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class KamarAvailabilityService
{
    /**
     * Total unit terpakai (pending/approved) per jenis_kelas untuk rentang tanggal.
     *
     * @return array<string, int>
     */
    public function bookedUnitsByKamar(?string $tanggalMasuk = null, ?string $tanggalKeluar = null): array
    {
        if (! $tanggalMasuk || ! $tanggalKeluar) {
            return [];
        }

        $rows = DB::table('kamar_reservasi_items')
            ->join('kamar_reservasis', 'kamar_reservasi_items.kamar_reservasi_id', '=', 'kamar_reservasis.id')
            ->whereIn('kamar_reservasis.status', ['pending', 'approved'])
            ->where('kamar_reservasi_items.tanggal_masuk', '<', $tanggalKeluar)
            ->where('kamar_reservasi_items.tanggal_keluar', '>', $tanggalMasuk)
            ->whereNotNull('kamar_reservasi_items.jenis_kelas')
            ->selectRaw('kamar_reservasi_items.jenis_kelas, COALESCE(SUM(kamar_reservasi_items.jumlah), 0) AS terpakai')
            ->groupBy('kamar_reservasi_items.jenis_kelas')
            ->pluck('terpakai', 'jenis_kelas');

        return $rows->all();
    }

    /**
     * Daftar kamar + atribut tersedia/stok_total/terpakai untuk rentang tanggal.
     */
    public function availableRoomsWithStock(?string $tanggalMasuk = null, ?string $tanggalKeluar = null): Collection
    {
        $rooms = Kamar::query()->orderBy('jenis_kelas')->get();

        $booked = $this->bookedUnitsByKamar($tanggalMasuk, $tanggalKeluar);

        return $rooms->map(function (Kamar $kamar) use ($booked): Kamar {
            $terpakai = (int) ($booked[$kamar->jenis_kelas] ?? 0);
            $stokTotal = (int) ($kamar->stok_total ?: ($kamar->kuota_total ?: 1));
            $kamar->setAttribute('tersedia', max(0, $stokTotal - $terpakai));
            $kamar->setAttribute('stok_total', $stokTotal);
            $kamar->setAttribute('terpakai', $terpakai);

            return $kamar;
        });
    }

    /**
     * Legacy: daftar kamar tanpa info stok.
     */
    public function availableRooms(?string $tanggalMasuk = null, ?string $tanggalKeluar = null): Collection
    {
        $query = Kamar::query();

        if ($tanggalMasuk && $tanggalKeluar) {
            $bookedJenis = DB::table('kamar_reservasi_items')
                ->join('kamar_reservasis', 'kamar_reservasi_items.kamar_reservasi_id', '=', 'kamar_reservasis.id')
                ->whereIn('kamar_reservasis.status', ['pending', 'approved'])
                ->where('kamar_reservasi_items.tanggal_masuk', '<', $tanggalKeluar)
                ->where('kamar_reservasi_items.tanggal_keluar', '>', $tanggalMasuk)
                ->whereNotNull('kamar_reservasi_items.jenis_kelas')
                ->pluck('kamar_reservasi_items.jenis_kelas')
                ->unique();

            if ($bookedJenis->isNotEmpty()) {
                $query->whereNotIn('jenis_kelas', $bookedJenis);
            }
        }

        return $query->orderBy('jenis_kelas')->get();
    }

    /**
     * Sisa unit Tersedia untuk satu kamar pada rentang tanggal.
     */
    public function availableStock(Kamar $kamar, ?string $tanggalMasuk = null, ?string $tanggalKeluar = null): int
    {
        $stokTotal = (int) ($kamar->stok_total ?: ($kamar->kuota_total ?: 1));

        if (! $tanggalMasuk || ! $tanggalKeluar) {
            return $stokTotal;
        }

        $terpakai = (int) DB::table('kamar_reservasi_items')
            ->join('kamar_reservasis', 'kamar_reservasi_items.kamar_reservasi_id', '=', 'kamar_reservasis.id')
            ->whereIn('kamar_reservasis.status', ['pending', 'approved'])
            ->where('kamar_reservasi_items.tanggal_masuk', '<', $tanggalKeluar)
            ->where('kamar_reservasi_items.tanggal_keluar', '>', $tanggalMasuk)
            ->where('kamar_reservasi_items.jenis_kelas', $kamar->jenis_kelas)
            ->sum('kamar_reservasi_items.jumlah');

        return max(0, $stokTotal - $terpakai);
    }

    public function isBookable(Kamar $kamar, int $jumlahUnit = 1, ?string $tanggalMasuk = null, ?string $tanggalKeluar = null): bool
    {
        return $this->availableStock($kamar, $tanggalMasuk, $tanggalKeluar) >= $jumlahUnit;
    }

    /**
     * Parse free-form date input WhatsApp → [tanggal_masuk, tanggal_keluar].
     * Pola: "12-06-2026", "12/06/2026", "2026-06-12", range " - "/" sampai "/" s/d ".
     *
     * @return array{0:string,1:string}|null
     */
    public function parseDateInput(string $input): ?array
    {
        $normalized = preg_replace('/\s+(sampai|s\/d|sd|hingga|-)\s+/i', '|', trim($input));
        $parts = array_values(array_filter(array_map('trim', explode('|', (string) $normalized))));

        $dates = [];
        foreach ($parts as $part) {
            $date = $this->parseSingleDate($part);
            if ($date) {
                $dates[] = $date;
            }
        }

        if ($dates === []) {
            return null;
        }

        $masuk = $dates[0];
        $keluar = $dates[1] ?? Carbon::parse($dates[0])->addDay()->format('Y-m-d');

        return [$masuk, $keluar];
    }

    private function parseSingleDate(string $value): ?string
    {
        $value = trim($value);

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d', 'Y/m/d', 'd-m-y', 'd/m/y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }
}
