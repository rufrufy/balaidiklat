<?php

namespace App\Http\Controllers;

use App\Services\KamarAvailabilityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminKamarAvailabilityController extends Controller
{
    public function __invoke(Request $request, KamarAvailabilityService $availability): View
    {
        $data = $request->validate([
            'tanggal_masuk' => ['nullable', 'date'],
            'tanggal_keluar' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
        ]);

        $kamars = collect();

        if (! empty($data['tanggal_masuk']) && ! empty($data['tanggal_keluar'])) {
            $kamars = $availability->availableRooms($data['tanggal_masuk'], $data['tanggal_keluar']);
        }

        return view('admin.kamar-availability', [
            'kamars' => $kamars,
            'tanggalMasuk' => $data['tanggal_masuk'] ?? null,
            'tanggalKeluar' => $data['tanggal_keluar'] ?? null,
        ]);
    }
}
