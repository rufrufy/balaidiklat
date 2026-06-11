<?php

namespace App\Http\Controllers;

use App\Models\ChatbotRule;
use App\Models\Kamar;
use App\Models\KamarReservasi;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\KamarAvailabilityService;
use App\Services\KirimChatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class KirimChatWebhookController extends Controller
{
    public function __construct(
        private readonly KamarAvailabilityService $availability,
    ) {
    }

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
            // Outbound status callbacks (message.sent/delivered/read/failed) are
            // already logged when we send, so we skip them to avoid duplicate
            // bubbles. Only genuine inbound messages get stored here.
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

        $rawInput = (string) ($interactiveId ?: $messageText);
        $input = trim(Str::lower($rawInput));

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

        $customerName = $this->extractCustomerName($payload);

        $this->runChatbot($session, $phoneNumber, $input, $rawInput, $customerName, $kirimChat);

        return response()->json(['success' => true]);
    }

    /**
     * Resolve the reply purely from configurable chatbot rules. The first
     * active rule (priority ascending) whose keyword + state match wins.
     * State + next_state build menu depth; action triggers special behavior.
     */
    private function runChatbot(
        WhatsappSession $session,
        string $phoneNumber,
        string $input,
        string $rawInput,
        ?string $customerName,
        KirimChatService $kirimChat
    ): void {
        $rule = ChatbotRule::where('is_active', true)
            ->orderBy('priority')
            ->get()
            ->first(fn (ChatbotRule $rule): bool => $rule->matches($input, $session->state));

        if (! $rule) {
            $this->sendMainMenu($phoneNumber, $customerName, $kirimChat);
            $session->update(['state' => 'main_menu']);

            return;
        }

        if ($rule->next_state) {
            $session->update(['state' => $rule->next_state]);
        }

        $this->dispatchRule($rule, $session, $phoneNumber, $rawInput, $customerName, $kirimChat);
    }

    private function dispatchRule(
        ChatbotRule $rule,
        WhatsappSession $session,
        string $phoneNumber,
        string $rawInput,
        ?string $customerName,
        KirimChatService $kirimChat
    ): void {
        switch ($rule->action) {
            case 'main_menu':
                if ($rule->reply_text) {
                    $kirimChat->sendText($phoneNumber, $this->personalize($rule->reply_text, $customerName));
                } else {
                    $this->sendMainMenu($phoneNumber, $customerName, $kirimChat);
                }

                return;

            case 'check_availability':
                $this->sendAvailability($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'list_kamar':
                $this->sendKamarList($session, $phoneNumber, $kirimChat);

                return;

            case 'pilih_kamar':
                $this->sendKamarDetail($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'simpan_reservasi':
                $this->simpanReservasi($session, $phoneNumber, $rawInput, $customerName, $kirimChat);

                return;

            default:
                if ($rule->reply_text) {
                    $kirimChat->sendText($phoneNumber, $this->personalize($rule->reply_text, $customerName));
                }
        }
    }

    private function sendMainMenu(string $phoneNumber, ?string $customerName, KirimChatService $kirimChat): void
    {
        $name = $customerName ?: 'Sahabat Balai';

        $body = "Halo, {$name} Selamat Datang di SAPA BALAI \u{1F44B}.\n"
            ."Smart Chatbot Layanan Balai Diklat Kota Semarang.\n\n"
            ."Silakan pilih menu layanan dengan mengetik angka 1 sampai 6.\n\n"
            ."1. Informasi layanan balai diklat\n"
            ."2. Pemesanan kamar/kelas\n"
            ."3. Laporan Gangguan\n"
            ."4. Saran\n"
            ."5. Survey kepuasan\n"
            ."6. Customer Care";

        $kirimChat->sendText($phoneNumber, $body);
    }

    private function sendReturnButtons(string $phoneNumber, string $bodyText, KirimChatService $kirimChat): void
    {
        $kirimChat->sendButtons($phoneNumber, $bodyText, [
            ['id' => 'menu', 'title' => 'Menu Utama'],
        ]);
    }

    private function sendAvailability(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $range = $this->availability->parseDateInput($rawInput);

        if (! $range) {
            $kirimChat->sendText(
                $phoneNumber,
                "Mohon kirim tanggal yang ingin dicek.\nContoh: 15-06-2026 atau 15-06-2026 sampai 17-06-2026."
            );

            return;
        }

        [$masuk, $keluar] = $range;
        $rooms = $this->availability->availableRooms($masuk, $keluar);

        if ($rooms->isEmpty()) {
            // Jadwal tidak tersedia: tawarkan jadwal lain / kembali ke menu utama.
            $session->update(['state' => 'pesan_cek_tanggal']);
            $this->sendReturnButtons(
                $phoneNumber,
                "Maaf, tidak ada kamar/kelas yang tersedia pada {$masuk} s/d {$keluar}.\nSilakan kirim tanggal lain, atau kembali ke menu utama.",
                $kirimChat
            );

            return;
        }

        $lines = $rooms->map(static function ($room): string {
            $harga = number_format((int) $room->harga_per_malam, 0, ',', '.');

            return "- {$room->kode} {$room->nama} (Rp{$harga})";
        })->implode("\n");

        // Jadwal tersedia: lanjut ke pengisian data pemesanan.
        $session->update([
            'state' => 'pesan_isi_data',
            'context' => array_merge($session->context ?? [], [
                'tanggal_masuk' => $masuk,
                'tanggal_keluar' => $keluar,
            ]),
        ]);

        $kirimChat->sendText(
            $phoneNumber,
            "Kamar/kelas tersedia {$masuk} s/d {$keluar}:\n{$lines}\n\n"
            ."Silakan isi data pemesanan dengan format:\n"
            ."Nama, Instansi, Kegiatan, Jumlah peserta\n"
            ."Contoh: Budi, BKPP, Diklat ASN, 20"
        );
    }

    private function personalize(string $text, ?string $customerName): string
    {
        return str_replace(
            ['{{customer_name}}', '{{nama}}'],
            $customerName ?: 'Sahabat Balai',
            $text
        );
    }

    /**
     * List kamar/kelas straight from the manajemen kamar DB. Stores the
     * ordered ids in session context so a numeric reply maps to a room.
     */
    private function sendKamarList(WhatsappSession $session, string $phoneNumber, KirimChatService $kirimChat): void
    {
        $kamars = Kamar::where('status', '!=', 'maintenance')->orderBy('kode')->get();

        if ($kamars->isEmpty()) {
            $this->sendReturnButtons($phoneNumber, "Mohon maaf, belum ada data kamar/kelas yang tersedia saat ini.", $kirimChat);

            return;
        }

        $lines = [];
        $map = [];
        foreach ($kamars->values() as $index => $kamar) {
            $no = $index + 1;
            $map[(string) $no] = $kamar->id;
            $harga = number_format((int) $kamar->harga_per_malam, 0, ',', '.');
            $lines[] = "{$no}. {$kamar->nama} ({$kamar->tipeLabel()}) - Rp{$harga}";
        }

        $session->update([
            'context' => array_merge($session->context ?? [], ['kamar_map' => $map]),
        ]);

        $kirimChat->sendText(
            $phoneNumber,
            "Pilihan kamar/kelas yang tersedia:\n\n".implode("\n", $lines)."\n\nKetik nomor untuk melihat detail/fasilitas."
        );
    }

    /**
     * Show the chosen room's keterangan/fasilitas from DB, then offer to order.
     */
    private function sendKamarDetail(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $map = data_get($session->context, 'kamar_map', []);
        $choice = trim($rawInput);
        $kamarId = $map[$choice] ?? null;
        $kamar = $kamarId ? Kamar::find($kamarId) : null;

        if (! $kamar) {
            $kirimChat->sendText($phoneNumber, "Pilihan tidak dikenali. Ketik nomor kamar/kelas yang ada di daftar.");

            return;
        }

        $harga = number_format((int) $kamar->harga_per_malam, 0, ',', '.');
        $fasilitas = $kamar->fasilitas ?: 'Informasi fasilitas belum tersedia.';

        $session->update([
            'state' => 'pesan_isi_data',
            'context' => array_merge($session->context ?? [], ['kamar_id' => $kamar->id]),
        ]);

        $kirimChat->sendText(
            $phoneNumber,
            "{$kamar->nama} ({$kamar->tipeLabel()})\nTarif: Rp{$harga}\n\nFasilitas/Keterangan:\n{$fasilitas}\n\n"
            ."Untuk memesan, kirim data dengan format:\n"
            ."Nama, Tanggal masuk, Tanggal keluar, No WhatsApp\n"
            ."Contoh: Budi, 15-06-2026, 17-06-2026, 6281234567890"
        );
    }

    /**
     * Parse the WA order form and create a reservation in DB, then send the
     * final interactive reply with a Menu Utama button.
     */
    private function simpanReservasi(WhatsappSession $session, string $phoneNumber, string $rawInput, ?string $customerName, KirimChatService $kirimChat): void
    {
        $parts = array_map('trim', explode(',', $rawInput));
        $nama = $parts[0] ?? ($customerName ?: 'Pelanggan WhatsApp');
        $masukRaw = $parts[1] ?? null;
        $keluarRaw = $parts[2] ?? null;
        $waNumber = $parts[3] ?? $phoneNumber;

        $masuk = $masukRaw ? ($this->availability->parseDateInput($masukRaw)[0] ?? null) : null;
        $keluar = $keluarRaw ? ($this->availability->parseDateInput($keluarRaw)[0] ?? null) : null;

        $kamarId = data_get($session->context, 'kamar_id');
        $kamar = $kamarId ? Kamar::find($kamarId) : null;

        $duration = ($masuk && $keluar)
            ? (int) max(1, Carbon::parse($masuk)->diffInDays(Carbon::parse($keluar)) ?: 1)
            : 1;
        $total = ($kamar?->harga_per_malam ?? 0) * $duration;

        $reservasi = KamarReservasi::create([
            'kode' => $this->generateReservationCode(),
            'nama_pemesan' => $nama,
            'tipe_penyewa' => 'perorangan',
            'phone_number' => $waNumber,
            'kamar_id' => $kamar?->id,
            'multiple_kamar' => false,
            'tanggal_masuk' => $masuk,
            'tanggal_keluar' => $keluar,
            'durasi_hari' => $duration,
            'jumlah_peserta' => 1,
            'total_harga' => $total,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'catatan' => 'Reservasi via WhatsApp chatbot.',
        ]);

        if ($kamar && $masuk && $keluar) {
            $reservasi->items()->create([
                'kamar_id' => $kamar->id,
                'tanggal_masuk' => $masuk,
                'tanggal_keluar' => $keluar,
                'durasi_hari' => $duration,
                'harga_per_malam' => $kamar->harga_per_malam,
                'subtotal' => $total,
            ]);
        }

        $session->update(['state' => 'main_menu', 'context' => []]);

        $hargaText = number_format($total, 0, ',', '.');
        $kirimChat->sendButtons(
            $phoneNumber,
            "Reservasi berhasil dibuat!\n\n"
            ."Kode booking: {$reservasi->kode}\n"
            .($kamar ? "Kamar/kelas: {$kamar->nama}\n" : '')
            .(($masuk && $keluar) ? "Tanggal: {$masuk} s/d {$keluar}\n" : '')
            ."Total: Rp{$hargaText}\n"
            ."Status: menunggu konfirmasi & pembayaran.\n\n"
            ."Terima kasih telah memesan di Balai Diklat Kota Semarang.",
            [['id' => 'menu', 'title' => 'Menu Utama']]
        );
    }

    private function generateReservationCode(): string
    {
        do {
            $kode = 'RSV-'.now()->format('YmdHis').'-'.random_int(100, 999);
        } while (KamarReservasi::where('kode', $kode)->exists());

        return $kode;
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

    private function extractCustomerName(array $payload): ?string
    {
        return Arr::get($payload, 'customer.name')
            ?? Arr::get($payload, 'data.customer_name')
            ?? Arr::get($payload, 'data.customer.name')
            ?? Arr::get($payload, 'data.profile.name')
            ?? Arr::get($payload, 'profile.name');
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
