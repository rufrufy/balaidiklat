<?php

namespace App\Services;

use App\Models\Kamar;
use App\Models\KamarReservasiItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KamarAvailabilityService
{
    /**
     * Return all kamar (jenis_kelas) with sisa kuota for the given date range.
     * Sisa kuota = kuota_total - sum(jumlah) dari reservasi items yang overlap
     * dengan range tanggal dan berstatus pending/approved.
     */
    public function availableRooms(?string $tanggalMasuk = null, ?string $tanggalKeluar = null): Collection
    {
        $kamars = Kamar::orderBy('jenis_kelas')->get();

        if (! $tanggalMasuk || ! $tanggalKeluar) {
            return $kamars->map(fn (Kamar $k) => $this->withAvailability($k, $k->kuota_total));
        }

        $booked = $this->bookedCountPerJenis($tanggalMasuk, $tanggalKeluar);

        return $kamars->map(function (Kamar $k) use ($booked): Kamar {
            $terpakai = $booked[$k->jenis_kelas] ?? 0;
            $sisa = max(0, $k->kuota_total - $terpakai);

            return $this->withAvailability($k, $sisa);
        });
    }

    /**
     * Whether a specific jenis_kelas has at least $jumlah unit available
     * on the given date range.
     */
    public function isBookable(string $jenisKelas, int $jumlah, ?string $tanggalMasuk = null, ?string $tanggalKeluar = null): bool
    {
        $kamar = Kamar::where('jenis_kelas', $jenisKelas)->first();

        if (! $kamar) {
            return false;
        }

        if (! $tanggalMasuk || ! $tanggalKeluar) {
            return $kamar->kuota_total >= $jumlah;
        }

        $booked = $this->bookedCountPerJenis($tanggalMasuk, $tanggalKeluar);
        $terpakai = $booked[$jenisKelas] ?? 0;
        $sisa = max(0, $kamar->kuota_total - $terpakai);

        return $sisa >= $jumlah;
    }

    /**
     * Sum of reserved units per jenis_kelas for overlapping date range.
     *
     * @return array<string,int>
     */
    private function bookedCountPerJenis(string $tanggalMasuk, string $tanggalKeluar): array
    {
        $rows = KamarReservasiItem::query()
            ->selectRaw('jenis_kelas, COALESCE(SUM(jumlah), 0) as total')
            ->whereHas('reservasi', function ($q) {
                $q->whereIn('status', ['pending', 'approved']);
            })
            ->where('tanggal_masuk', '<', $tanggalKeluar)
            ->where('tanggal_keluar', '>', $tanggalMasuk)
            ->groupBy('jenis_kelas')
            ->pluck('total', 'jenis_kelas');

        return $rows->map(fn ($v) => (int) $v)->all();
    }

    private function withAvailability(Kamar $kamar, int $sisa): Kamar
    {
        $kamar->setAttribute('sisa_kuota', $sisa);

        return $kamar;
    }

    /**
     * Parse free-form date input from WhatsApp (single date or range) into
     * [tanggal_masuk, tanggal_keluar]. Returns null if no date is found.
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
