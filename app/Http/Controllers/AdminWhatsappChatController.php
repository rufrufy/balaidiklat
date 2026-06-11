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

    public function send(Request $request, KirimChatService $kirimChat): JsonResponse
    {
        $data = $request->validate([
            'phone_number' => ['required', 'string', 'max:40'],
            'message' => ['required', 'string'],
        ]);

        $kirimChat->sendText($data['phone_number'], $data['message']);

        return response()->json(['success' => true]);
    }
}
