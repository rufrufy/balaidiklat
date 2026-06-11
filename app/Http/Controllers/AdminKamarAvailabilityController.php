<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminKamarAvailabilityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $data = $request->validate([
            'tanggal_masuk' => ['nullable', 'date'],
            'tanggal_keluar' => ['nullable', 'date', 'after_or_equal:tanggal_masuk'],
        ]);

        $kamars = collect();

        if (! empty($data['tanggal_masuk']) && ! empty($data['tanggal_keluar'])) {
            $kamars = Kamar::query()
                ->where('status', '!=', 'maintenance')
                ->whereDoesntHave('reservasiItems', function ($query) use ($data): void {
                    $query->where('tanggal_masuk', '<', $data['tanggal_keluar'])
                        ->where('tanggal_keluar', '>', $data['tanggal_masuk']);
                })
                ->orderBy('kode')
                ->get();
        }

        return view('admin.kamar-availability', [
            'kamars' => $kamars,
            'tanggalMasuk' => $data['tanggal_masuk'] ?? null,
            'tanggalKeluar' => $data['tanggal_keluar'] ?? null,
        ]);
    }
}
