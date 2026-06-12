<?php

namespace App\Services;

use App\Models\Kamar;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KamarAvailabilityService
{
    /**
     * A room is bookable on ANY date only when its status is 'available'.
     * Any other status (limited/full/maintenance) means it can never be booked,
     * regardless of date. Date range is kept for signature compatibility.
     */
    public function availableRooms(?string $tanggalMasuk = null, ?string $tanggalKeluar = null): Collection
    {
        return Kamar::query()
            ->where('status', 'available')
            ->orderBy('kode')
            ->get();
    }

    /**
     * Whether a specific room can be booked. Status-only rule.
     */
    public function isBookable(Kamar $kamar): bool
    {
        return $kamar->status === 'available';
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
