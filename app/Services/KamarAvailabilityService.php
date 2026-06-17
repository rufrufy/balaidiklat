<?php

namespace App\Services;

use App\Models\Kamar;
use App\Models\KamarReservasiItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class KamarAvailabilityService
{
    /**
     * Return rooms that are available (status = 'available') and have no
     * conflicting reservations on the given date range. If no dates are
     * provided, returns all rooms with status 'available'.
     */
    public function availableRooms(?string $tanggalMasuk = null, ?string $tanggalKeluar = null): Collection
    {
        $query = Kamar::query()->where('status', 'available');

        if ($tanggalMasuk && $tanggalKeluar) {
            // Exclude rooms that have an overlapping reservation (pending or approved)
            $bookedKamarIds = KamarReservasiItem::query()
                ->whereHas('reservasi', function ($q) {
                    $q->whereIn('status', ['pending', 'approved']);
                })
                ->where('tanggal_masuk', '<', $tanggalKeluar)
                ->where('tanggal_keluar', '>', $tanggalMasuk)
                ->pluck('kamar_id')
                ->unique();

            if ($bookedKamarIds->isNotEmpty()) {
                $query->whereNotIn('id', $bookedKamarIds);
            }
        }

        return $query->orderBy('kode')->get();
    }

    /**
     * Whether a specific room can be booked on a given date range.
     */
    public function isBookable(Kamar $kamar, ?string $tanggalMasuk = null, ?string $tanggalKeluar = null): bool
    {
        if ($kamar->status !== 'available') {
            return false;
        }

        if ($tanggalMasuk && $tanggalKeluar) {
            $hasConflict = KamarReservasiItem::query()
                ->where('kamar_id', $kamar->id)
                ->whereHas('reservasi', function ($q) {
                    $q->whereIn('status', ['pending', 'approved']);
                })
                ->where('tanggal_masuk', '<', $tanggalKeluar)
                ->where('tanggal_keluar', '>', $tanggalMasuk)
                ->exists();

            return ! $hasConflict;
        }

        return true;
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
