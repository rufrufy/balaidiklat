<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\KamarReservasi;
use App\Models\ChatbotRule;
use App\Models\LayananPengaduan;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use Illuminate\View\View;

class AdminDashboardController extends Controller
{
    public function __invoke(): View
    {
        $kamars = Kamar::with('fotos')->latest()->get();
        $reservasis = KamarReservasi::with(['kamar', 'items.kamar', 'retribusiBillings'])->latest()->get();
        $sessions = WhatsappSession::latest('last_message_at')->get();
        $messages = WhatsappMessage::latest()->limit(50)->get()->reverse()->values();
        $rules = ChatbotRule::orderBy('priority')->latest()->get();
        $pengaduans = LayananPengaduan::latest()->get();

        return view('admin.dashboard', [
            'kamars' => $kamars,
            'reservasis' => $reservasis,
            'sessions' => $sessions,
            'messages' => $messages,
            'rules' => $rules,
            'pengaduans' => $pengaduans,
            'stats' => [
                'kamar' => $kamars->count(),
                'ruang_kelas' => $kamars->filter(fn ($k) => $k->tipe === 'ruang_kelas')->count(),
                'reservasi' => $reservasis->count(),
                'sessions' => $sessions->count(),
                'messages' => $messages->count(),
                'rules' => $rules->count(),
                'pengaduan' => $pengaduans->count(),
            ],
        ]);
    }
}
