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

    /**
     * Send an interactive list message. WhatsApp allows up to 10 rows per section.
     * Each row is ['id' => string, 'title' => string, 'description' => ?string].
     *
     * @param string $buttonLabel  Label for the list button (max 20 chars)
     * @param array<int, array{title:string, rows:array<int, array{id:string,title:string,description?:string}>}> $sections
     */
    public function sendList(string $phoneNumber, string $bodyText, string $buttonLabel, array $sections, ?string $headerText = null, ?string $footerText = null): array
    {
        $formattedSections = array_map(static function (array $section): array {
            return [
                'title' => $section['title'],
                'rows' => array_map(static function (array $row): array {
                    $formatted = ['id' => $row['id'], 'title' => $row['title']];
                    if (! empty($row['description'])) {
                        $formatted['description'] = $row['description'];
                    }

                    return $formatted;
                }, $section['rows']),
            ];
        }, $sections);

        $interactive = [
            'type' => 'list',
            'body' => ['text' => $bodyText],
            'action' => [
                'button' => $buttonLabel,
                'sections' => $formattedSections,
            ],
        ];

        if ($headerText) {
            $interactive['header'] = ['type' => 'text', 'text' => $headerText];
        }
        if ($footerText) {
            $interactive['footer'] = ['text' => $footerText];
        }

        $payload = [
            'phone_number' => $phoneNumber,
            'channel' => 'whatsapp',
            'message_type' => 'interactive',
            'interactive' => $interactive,
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
