<?php

namespace App\Services;

use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class KirimChatService
{
    /**
     * Kirimdev (Meta WhatsApp Cloud API style).
     * Endpoint: POST {base_url}/{phone_number_id}/messages
     * Body: { messaging_product, recipient_type, to, type, text/interactive/image }
     */
    public function sendText(string $phoneNumber, string $content): array
    {
        $content = mb_substr($content, 0, 4096);
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phoneNumber),
            'type' => 'text',
            'text' => ['body' => $content, 'preview_url' => true],
        ];

        return $this->post($payload, $phoneNumber, 'text', $content);
    }

    public function sendImage(string $phoneNumber, string $mediaUrl, ?string $caption = null): array
    {
        $image = ['link' => $mediaUrl];
        if ($caption) {
            $image['caption'] = mb_substr($caption, 0, 1024);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phoneNumber),
            'type' => 'image',
            'image' => $image,
        ];

        return $this->post($payload, $phoneNumber, 'image', $caption ?: $mediaUrl);
    }

    /**
     * Send an interactive reply-buttons message. WhatsApp allows max 3 buttons.
     * Each button is ['id' => string, 'title' => string].
     *
     * @param array<int, array{id:string,title:string}> $buttons
     */
    public function sendButtons(string $phoneNumber, string $bodyText, array $buttons): array
    {
        $bodyText = mb_substr($bodyText ?: 'Pilih opsi:', 0, 1024);
        $replyButtons = array_map(static fn (array $button): array => [
            'type' => 'reply',
            'reply' => ['id' => $button['id'], 'title' => mb_substr($button['title'], 0, 20)],
        ], array_slice($buttons, 0, 3));

        if (empty($replyButtons)) {
            return $this->sendText($phoneNumber, $bodyText);
        }

        $interactive = [
            'type' => 'reply_buttons',
            'body' => ['text' => $bodyText],
            'action' => ['buttons' => $replyButtons],
        ];

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phoneNumber),
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        $result = $this->post($payload, $phoneNumber, 'interactive', $bodyText);

        // Fallback: kalau interactive gagal, kirim sebagai teks biasa
        if (! ($result['success'] ?? true)) {
            $text = $bodyText . "\n\n";
            foreach ($replyButtons as $i => $btn) {
                $text .= ($i + 1) . '. ' . $btn['reply']['title'] . ' (balas *' . $btn['reply']['id'] . '*)\n';
            }
            return $this->sendText($phoneNumber, $text);
        }

        return $result;
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
        $bodyText = mb_substr($bodyText, 0, 1024);
        $buttonLabel = mb_substr($buttonLabel, 0, 20);
        $headerText = $headerText ? mb_substr($headerText, 0, 60) : null;
        $footerText = $footerText ? mb_substr($footerText, 0, 60) : null;

        $formattedSections = array_map(static function (array $section): array {
            $sectionTitle = mb_substr($section['title'] ?? '', 0, 24);

            return [
                'title' => $sectionTitle,
                'rows' => array_map(static function (array $row): array {
                    $formatted = [
                        'id' => $row['id'],
                        'title' => mb_substr($row['title'], 0, 24),
                    ];
                    if (! empty($row['description'])) {
                        $formatted['description'] = mb_substr($row['description'], 0, 72);
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
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $this->normalizePhone($phoneNumber),
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        return $this->post($payload, $phoneNumber, 'interactive', $bodyText);
    }

    private function post(array $payload, string $phoneNumber, string $messageType, string $messageText): array
    {
        $phoneNumberId = (string) config('services.kirimchat.phone_number_id');
        if ($phoneNumberId === '') {
            Log::error('Kirimdev API error: KIRIMCHAT_PHONE_NUMBER_ID kosong', [
                'phone' => $phoneNumber,
            ]);

            return ['success' => false, 'error' => 'phone_number_id_not_configured'];
        }

        $url = rtrim((string) config('services.kirimchat.base_url'), '/') . '/' . $phoneNumberId . '/messages';

        try {
            $response = Http::timeout(30)
                ->withToken(config('services.kirimchat.api_key'))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::error('Kirimdev API connection error', [
                'error' => $e->getMessage(),
                'url' => $url,
                'phone' => $phoneNumber,
                'message_type' => $messageType,
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }

        if ($response->failed()) {
            Log::error('Kirimdev API error', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);
        } else {
            Log::info('Kirimdev API success', [
                'phone' => $phoneNumber,
                'message_type' => $messageType,
                'status' => $response->status(),
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

    /**
     * Normalize phone ke E.164 (628xxx) tanpa + prefix.
     */
    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($phone, '0')) {
            $phone = '62' . substr($phone, 1);
        } elseif (! str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }
}
