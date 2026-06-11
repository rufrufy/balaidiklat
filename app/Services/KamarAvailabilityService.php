<?php

namespace App\Services;

use App\Models\Kamar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KamarAvailabilityService
{
    /**
     * Return rooms that are not under maintenance and have no reservation item
     * overlapping the requested date range.
     */
    public function availableRooms(string $tanggalMasuk, string $tanggalKeluar): Collection
    {
        return Kamar::query()
            ->where('status', '!=', 'maintenance')
            ->whereDoesntHave('reservasiItems', function ($query) use ($tanggalMasuk, $tanggalKeluar): void {
                $query->where('tanggal_masuk', '<', $tanggalKeluar)
                    ->where('tanggal_keluar', '>', $tanggalMasuk);
            })
            ->orderBy('kode')
            ->get();
    }

    /**
     * Parse free-form date input from WhatsApp (single date or range) into
     * [tanggal_masuk, tanggal_keluar]. Returns null if no date is found.
     *
     * Accepts patterns like "12-06-2026", "12/06/2026", "2026-06-12", and
     * ranges separated by " - ", " sampai ", or " s/d ".
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
