<?php

namespace App\Http\Controllers;

use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\KirimChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminWhatsappChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $phoneNumber = $request->query('phone_number');

        return response()->json([
            'sessions' => WhatsappSession::latest('last_message_at')->limit(50)->get()->map(fn (WhatsappSession $session): array => [
                'phone_number' => $session->phone_number,
                'state' => $session->state,
                'mode' => $session->mode,
                'human_taken_at' => optional($session->human_taken_at)->format('d M Y H:i'),
                'last_message_at' => optional($session->last_message_at)->format('d M Y H:i'),
            ]),
            'messages' => WhatsappMessage::query()
                ->when($phoneNumber, fn ($query) => $query->where('phone_number', $phoneNumber))
                ->latest()
                ->limit(80)
                ->get()
                ->reverse()
                ->values()
                ->map(fn (WhatsappMessage $message): array => [
                'phone_number' => $message->phone_number,
                'direction' => $message->direction,
                'message_type' => $message->message_type,
                'message_text' => $message->message_text ?: '-',
                'created_at' => $message->created_at->format('d M Y H:i:s'),
            ]),
        ]);
    }

    /**
     * Admin mengirim pesan manual ke customer. Setelah kirim, sesi otomatis
     * beralih ke mode 'human' -> bot berhenti membalas sampai dilepaskan
     * (atau auto-resume setelah timeout idle).
     */
    public function send(Request $request, KirimChatService $kirimChat): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string'],
        ]);

        $kirimChat->sendText($data['phone_number'], $data['message']);

        WhatsappMessage::create([
            'phone_number' => $data['phone_number'],
            'direction' => 'outbound',
            'message_type' => 'text',
            'message_text' => $data['message'],
            'payload' => ['source' => 'admin_manual'],
        ]);

        WhatsappSession::updateOrCreate(
            ['phone_number' => $data['phone_number']],
            [
                'mode' => 'human',
                'human_taken_at' => now(),
                'last_message_at' => now(),
            ],
        );

        return response()->json(['success' => true]);
    }

    /**
     * Admin mengambil alih percakapan tanpa mengirim pesan terlebih dahulu.
     * Bot di-pause sampai admin melepaskan kembali.
     */
    public function takeover(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:40'],
        ]);

        WhatsappSession::updateOrCreate(
            ['phone_number' => $data['phone_number']],
            [
                'mode' => 'human',
                'human_taken_at' => now(),
                'last_message_at' => now(),
            ],
        );

        return response()->json(['success' => true]);
    }

    /**
     * Melepaskan percakapan kembali ke bot. State & context direset ke menu.
     */
    public function release(Request $request): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:40'],
        ]);

        $session = WhatsappSession::where('phone_number', $data['phone_number'])->first();

        if ($session) {
            $session->update([
                'mode' => 'bot',
                'state' => 'main_menu',
                'context' => [],
                'human_taken_at' => null,
            ]);
        }

        return response()->json(['success' => true]);
    }
}
