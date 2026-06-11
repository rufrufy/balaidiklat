<?php

namespace App\Http\Controllers;

use App\Models\KamarReservasi;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminRekapController extends Controller
{
    public function index(Request $request): View
    {
        $bulan = $request->query('bulan', now()->format('Y-m'));
        $start = $bulan.'-01';

        $reservasis = KamarReservasi::with(['kamar', 'items.kamar'])
            ->where(function ($query) use ($start): void {
                $query->whereYear('tanggal_masuk', substr($start, 0, 4))
                    ->whereMonth('tanggal_masuk', substr($start, 5, 2));
            })
            ->latest('tanggal_masuk')
            ->get();

        return view('admin.rekap-bulanan', [
            'bulan' => $bulan,
            'reservasis' => $reservasis,
            'stats' => [
                'total' => $reservasis->count(),
                'approved' => $reservasis->where('status', 'approved')->count(),
                'pending' => $reservasis->where('status', 'pending')->count(),
                'peserta' => $reservasis->sum('jumlah_peserta'),
            ],
        ]);
    }
}
