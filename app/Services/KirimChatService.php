<?php

namespace App\Services;

use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KirimChatService
{
    public function sendText(string $phoneNumber, string $content): array
    {
        $payload = [
            'phone_number' => $phoneNumber,
            'channel' => 'whatsapp',
            'message_type' => 'text',
            'content' => $content,
        ];

        return $this->post('/messages/send', $payload, $phoneNumber, 'text', $content);
    }

    private function post(string $path, array $payload, string $phoneNumber, string $messageType, string $messageText): array
    {
        $response = Http::withToken(config('services.kirimchat.api_key'))
            ->acceptJson()
            ->asJson()
            ->post(rtrim((string) config('services.kirimchat.base_url'), '/') . $path, $payload);

        if ($response->failed()) {
            Log::error('KirimChat API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);
        }

        $result = $response->json() ?? [
            'success' => $response->successful(),
            'status' => $response->status(),
            'body' => $response->body(),
        ];

        WhatsappMessage::create([
            'phone_number' => $phoneNumber,
            'direction' => 'outbound',
            'message_type' => $messageType,
            'message_text' => $messageText,
            'payload' => ['request' => $payload, 'response' => $result],
        ]);

        return $result;
    }
}
