<?php

namespace App\Http\Controllers;

use App\Models\ChatbotRule;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\KirimChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KirimChatWebhookController extends Controller
{
    public function handle(Request $request, KirimChatService $kirimChat): JsonResponse
    {
        $this->validateWebhookSecret($request);

        $payload = $request->all();

        Log::info('KirimChat webhook received', ['payload' => $payload]);

        $phoneNumber = $this->extractPhoneNumber($payload);
        $messageText = $this->extractMessageText($payload);
        $interactiveId = $this->extractInteractiveId($payload);

        if (! $phoneNumber) {
            Log::info('KirimChat webhook acknowledged without phone number', [
                'event_type' => Arr::get($payload, 'event_type'),
                'event_id' => Arr::get($payload, 'event_id'),
            ]);

            return response()->json(['success' => true]);
        }

        if (! $this->shouldProcessChatbot($payload)) {
            // Outbound echoes (message.sent/delivered/read/failed) are status
            // callbacks for messages we already logged when sending, so we skip
            // them to avoid duplicate bubbles in the admin chat. Only genuine
            // inbound messages we are not chatbot-processing get stored here.
            if ($this->isInboundMessageEvent($payload)) {
                WhatsappMessage::create([
                    'phone_number' => $phoneNumber,
                    'direction' => $this->extractDirection($payload),
                    'message_type' => $this->extractMessageType($payload),
                    'message_text' => $messageText ?: $interactiveId,
                    'payload' => $payload,
                ]);
            }

            return response()->json(['success' => true]);
        }

        $input = trim(Str::lower((string) ($interactiveId ?: $messageText)));

        WhatsappMessage::create([
            'phone_number' => $phoneNumber,
            'direction' => 'inbound',
            'message_type' => $interactiveId ? 'interactive' : 'text',
            'message_text' => $input,
            'payload' => $payload,
        ]);

        $session = WhatsappSession::firstOrCreate(
            ['phone_number' => $phoneNumber],
            ['state' => 'main_menu', 'context' => []]
        );

        $session->update(['last_message_at' => now()]);

        $this->runChatbot($session, $phoneNumber, $input, $kirimChat);

        return response()->json(['success' => true]);
    }

    /**
     * Resolve the reply purely from configurable chatbot rules. The first
     * active rule (ordered by priority ascending) whose keyword and state
     * match the input wins. State + next_state build the menu depth.
     */
    private function runChatbot(WhatsappSession $session, string $phoneNumber, string $input, KirimChatService $kirimChat): void
    {
        $rule = ChatbotRule::where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->first(fn (ChatbotRule $rule): bool => $rule->matches($input, $session->state));

        if (! $rule) {
            $kirimChat->sendText($phoneNumber, "Maaf, pesan belum dikenali. Ketik *menu* untuk kembali ke menu utama.");

            return;
        }

        $kirimChat->sendText($phoneNumber, $rule->reply_text);

        if ($rule->next_state) {
            $session->update(['state' => $rule->next_state]);
        }
    }

    private function validateWebhookSecret(Request $request): void
    {
        if (! config('services.kirimchat.require_webhook_secret')) {
            return;
        }

        $secret = config('services.kirimchat.webhook_secret');

        if (! $secret) {
            return;
        }

        $signature = (string) $request->header('X-KirimChat-Signature');
        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        if (! hash_equals($expected, $signature)) {
            abort(401, 'Invalid webhook secret');
        }
    }

    private function shouldProcessChatbot(array $payload): bool
    {
        $eventType = Arr::get($payload, 'event_type');
        $channel = Arr::get($payload, 'data.channel');
        $direction = $this->extractDirection($payload);

        if ($eventType && $eventType !== 'message.received') {
            return false;
        }

        if ($channel && $channel !== 'whatsapp') {
            return false;
        }

        return $direction === 'inbound';
    }

    private function isInboundMessageEvent(array $payload): bool
    {
        $eventType = Arr::get($payload, 'event_type');

        if ($eventType && $eventType !== 'message.received') {
            return false;
        }

        return $this->extractDirection($payload) === 'inbound';
    }

    private function extractPhoneNumber(array $payload): ?string
    {
        return Arr::get($payload, 'phone_number')
            ?? Arr::get($payload, 'from')
            ?? Arr::get($payload, 'customer.phone_number')
            ?? Arr::get($payload, 'data.phone_number')
            ?? Arr::get($payload, 'data.customer_phone')
            ?? Arr::get($payload, 'data.customer.phone_number');
    }

    private function extractMessageText(array $payload): ?string
    {
        return Arr::get($payload, 'message')
            ?? Arr::get($payload, 'text')
            ?? Arr::get($payload, 'content')
            ?? Arr::get($payload, 'data.message')
            ?? Arr::get($payload, 'data.text')
            ?? Arr::get($payload, 'data.content')
            ?? Arr::get($payload, 'data.caption');
    }

    private function extractDirection(array $payload): string
    {
        return Arr::get($payload, 'direction')
            ?? Arr::get($payload, 'data.direction')
            ?? 'inbound';
    }

    private function extractMessageType(array $payload): ?string
    {
        return Arr::get($payload, 'message_type')
            ?? Arr::get($payload, 'data.message_type')
            ?? Arr::get($payload, 'type');
    }

    private function extractInteractiveId(array $payload): ?string
    {
        return Arr::get($payload, 'interactive.id')
            ?? Arr::get($payload, 'interactive.reply.id')
            ?? Arr::get($payload, 'data.interactive.id')
            ?? Arr::get($payload, 'data.interactive.reply.id')
            ?? Arr::get($payload, 'button_reply.id')
            ?? Arr::get($payload, 'list_reply.id')
            ?? Arr::get($payload, 'data.button_reply.id')
            ?? Arr::get($payload, 'data.list_reply.id');
    }
}
