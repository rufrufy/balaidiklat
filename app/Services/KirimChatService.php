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

    /**
     * Send an interactive reply-buttons message. WhatsApp allows max 3 buttons.
     * Each button is ['id' => string, 'title' => string].
     *
     * @param array<int, array{id:string,title:string}> $buttons
     */
    public function sendButtons(string $phoneNumber, string $bodyText, array $buttons): array
    {
        $replyButtons = array_map(static fn (array $button): array => [
            'type' => 'reply',
            'reply' => ['id' => $button['id'], 'title' => $button['title']],
        ], array_slice($buttons, 0, 3));

        $payload = [
            'phone_number' => $phoneNumber,
            'channel' => 'whatsapp',
            'message_type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => ['buttons' => $replyButtons],
            ],
        ];

        return $this->post('/messages/send', $payload, $phoneNumber, 'interactive', $bodyText);
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
