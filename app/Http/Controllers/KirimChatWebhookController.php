<?php

namespace App\Http\Controllers;

use App\Models\ChatbotRule;
use App\Models\Kamar;
use App\Models\KamarReservasi;
use App\Models\LayananPengaduan;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\KamarAvailabilityService;
use App\Services\KirimChatService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

        // Jika user sedang diminta upload bukti pembayaran dan mengirim gambar,
        // tangani upload-nya lebih dulu (di luar alur rule berbasis teks).
        if ($session->state === 'pesan_upload_bukti') {
            $imageUrl = $this->extractImageUrl($payload);
            if ($imageUrl) {
                $this->handleBuktiPembayaran($session, $phoneNumber, $imageUrl, $kirimChat);

                return response()->json(['success' => true]);
            }
        }

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
            $session->update(['state' => 'main_menu', 'context' => []]);
            $this->sendReturnButtons(
                $phoneNumber,
                "Maaf, pilihan tidak dikenali.\nSilakan ketik *menu* atau tekan tombol di bawah untuk kembali ke menu utama.",
                $kirimChat
            );

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
                $this->sendMainMenu($phoneNumber, $customerName, $kirimChat);

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

            case 'bayar_pilihan':
                $this->sendPaymentChoice($phoneNumber, $kirimChat);

                return;

            case 'bayar_qris':
                $this->sendQris($session, $phoneNumber, $kirimChat);

                return;

            case 'bayar_transfer':
                $this->sendTransfer($session, $phoneNumber, $kirimChat);

                return;

            case 'input_nomor_kamar_gangguan':
                $this->inputNomorKamarGangguan($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'simpan_laporan':
                $this->simpanPengaduan('gangguan', $session, $phoneNumber, $rawInput, $customerName, $kirimChat);

                return;

            case 'simpan_saran':
                $this->simpanPengaduan('saran', $session, $phoneNumber, $rawInput, $customerName, $kirimChat);

                return;

            case 'input_rating_survey':
                $this->inputRatingSurvey($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'simpan_survey':
                $this->simpanSurvey($session, $phoneNumber, $rawInput, $customerName, $kirimChat);

                return;

            case 'cek_booking':
                $this->cekBooking($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'input_nama':
                $this->inputNama($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'input_tanggal_masuk':
                $this->inputTanggalMasuk($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'input_tanggal_keluar':
                $this->inputTanggalKeluar($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'input_no_hp':
                $this->inputNoHp($session, $phoneNumber, $rawInput, $customerName, $kirimChat);

                return;

            case 'kembali_menu':
                $session->update(['state' => 'main_menu', 'context' => []]);
                $this->sendMainMenu($phoneNumber, $customerName, $kirimChat);

                return;

            case 'selesai':
                $this->sendReturnButtons(
                    $phoneNumber,
                    $rule->reply_text ? $this->personalize($rule->reply_text, $customerName) : 'Terima kasih.',
                    $kirimChat
                );

                return;

            default:
                if ($rule->reply_text) {
                    $kirimChat->sendText($phoneNumber, $this->personalize($rule->reply_text, $customerName));
                }
        }
    }

    /**
     * Send the main menu as an interactive list message (clickable items).
     */
    private function sendMainMenu(string $phoneNumber, ?string $customerName, KirimChatService $kirimChat): void
    {
        $name = $customerName ?: 'Sahabat Balai';

        $body = "Halo, {$name} Selamat Datang di SAPA BALAI \u{1F44B}.\n"
            ."Smart Chatbot Layanan Balai Diklat Kota Semarang.\n\n"
            ."Silakan pilih menu layanan di bawah ini.";

        $kirimChat->sendList(
            $phoneNumber,
            $body,
            'Pilih Menu',
            [
                [
                    'title' => 'Menu Layanan',
                    'rows' => [
                        [
                            'id' => '1',
                            'title' => 'Info Layanan & Pesan',
                            'description' => 'Lihat info layanan dan pesan kamar/kelas',
                        ],
                        [
                            'id' => '3',
                            'title' => 'Laporan Gangguan',
                            'description' => 'Laporkan gangguan fasilitas',
                        ],
                        [
                            'id' => '4',
                            'title' => 'Saran',
                            'description' => 'Kirim saran dan masukan',
                        ],
                        [
                            'id' => '5',
                            'title' => 'Survey Kepuasan',
                            'description' => 'Isi survey kepuasan layanan',
                        ],
                        [
                            'id' => '6',
                            'title' => 'Customer Care',
                            'description' => 'Hubungi tim layanan pelanggan',
                        ],
                    ],
                ],
            ],
            'SAPA BALAI',
            'Ketik "menu" kapan saja untuk kembali.'
        );
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
        $kamars = Kamar::where('status', 'available')->orderBy('kode')->get();

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
            "INFORMASI LAYANAN BALAI DIKLAT KOTA SEMARANG\n\n"
            ."Balai Diklat menyediakan layanan sewa kamar dan ruang kelas untuk kegiatan diklat, rapat, maupun kegiatan resmi lainnya.\n\n"
            ."Berikut adalah pilihan kamar/kelas yang tersedia:\n\n"
            .implode("\n", $lines)
            ."\n\nKetik nomor untuk melihat detail & fasilitas, atau ketik *menu* untuk kembali."
        );
    }

    /**
     * Show the chosen room's keterangan/fasilitas from DB, then offer to order
     * via interactive buttons (Pesan / Menu Utama) and move to step-by-step flow.
     */
    private function sendKamarDetail(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $map = data_get($session->context, 'kamar_map', []);
        $choice = trim($rawInput);
        $kamarId = $map[$choice] ?? null;
        $kamar = $kamarId ? Kamar::find($kamarId) : null;

        if (! $kamar) {
            $this->sendReturnButtons(
                $phoneNumber,
                "Pilihan tidak dikenali. Ketik nomor kamar/kelas yang ada di daftar, atau kembali ke menu utama.",
                $kirimChat
            );

            return;
        }

        $harga = number_format((int) $kamar->harga_per_malam, 0, ',', '.');
        $fasilitas = $kamar->fasilitas ?: 'Informasi fasilitas belum tersedia.';

        $session->update([
            'state' => 'pesan_nama',
            'context' => array_merge($session->context ?? [], [
                'kamar_id' => $kamar->id,
                'kamar_nama' => $kamar->nama,
                'kamar_harga' => $kamar->harga_per_malam,
            ]),
        ]);

        $kirimChat->sendButtons(
            $phoneNumber,
            "{$kamar->nama} ({$kamar->tipeLabel()})\nTarif: Rp{$harga}/malam\n\nFasilitas/Keterangan:\n{$fasilitas}\n\nIngin melanjutkan pemesanan?",
            [
                ['id' => 'pesan', 'title' => 'Pesan'],
                ['id' => 'menu', 'title' => 'Menu Utama'],
            ]
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pemesanan step-by-step flow
    //   pesan_nama -> pesan_tanggal_masuk -> pesan_tanggal_keluar -> pesan_no_hp
    //   -> simpan_reservasi (dari context) -> pesan_pembayaran
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Step 1: nama pemesan. Tombol "Pesan" mengirim id='pesan' yang hanya
     * memicu prompt; input lain dianggap sebagai nama dan disimpan.
     */
    private function inputNama(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $value = trim($rawInput);

        if (in_array(Str::lower($value), ['pesan', 'menu', 'halo', ''], true)) {
            $kirimChat->sendText(
                $phoneNumber,
                "Silakan kirim *Nama* pemesan.\nContoh: Budi Santoso"
            );

            return;
        }

        $session->update([
            'state' => 'pesan_tanggal_masuk',
            'context' => array_merge($session->context ?? [], ['nama' => $value]),
        ]);

        $kirimChat->sendText(
            $phoneNumber,
            "Nama pemesan: *{$value}*\n\n"
            ."Silakan kirim *Tanggal Mulai* sewa dengan format DD-MM-YYYY (tanggal-bulan-tahun).\n"
            ."Contoh: 15-06-2026"
        );
    }

    /**
     * Step 2: tanggal mulai. Format harus DD-MM-YYYY; jika salah, ulangi.
     */
    private function inputTanggalMasuk(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $tanggal = $this->parseTanggalDdMmYyyy($rawInput);

        if (! $tanggal) {
            $kirimChat->sendText(
                $phoneNumber,
                "Format tanggal tidak sesuai. Silakan kirim *Tanggal Mulai* dengan format DD-MM-YYYY (tanggal-bulan-tahun).\n"
                ."Contoh: 15-06-2026"
            );

            return;
        }

        $session->update([
            'state' => 'pesan_tanggal_keluar',
            'context' => array_merge($session->context ?? [], ['tanggal_masuk' => $tanggal]),
        ]);

        $tanggalTeks = Carbon::parse($tanggal)->format('d-m-Y');
        $kirimChat->sendText(
            $phoneNumber,
            "Tanggal mulai: *{$tanggalTeks}*\n\n"
            ."Silakan kirim *Tanggal Selesai* sewa dengan format DD-MM-YYYY (tanggal-bulan-tahun).\n"
            ."Contoh: 17-06-2026"
        );
    }

    /**
     * Step 3: tanggal selesai. Harus DD-MM-YYYY dan >= tanggal mulai.
     */
    private function inputTanggalKeluar(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $tanggal = $this->parseTanggalDdMmYyyy($rawInput);

        if (! $tanggal) {
            $kirimChat->sendText(
                $phoneNumber,
                "Format tanggal tidak sesuai. Silakan kirim *Tanggal Selesai* dengan format DD-MM-YYYY (tanggal-bulan-tahun).\n"
                ."Contoh: 17-06-2026"
            );

            return;
        }

        $masuk = data_get($session->context, 'tanggal_masuk');
        if ($masuk && Carbon::parse($tanggal)->lte(Carbon::parse($masuk))) {
            $masukTeks = Carbon::parse($masuk)->format('d-m-Y');
            $kirimChat->sendText(
                $phoneNumber,
                "Tanggal selesai harus setelah tanggal mulai ({$masukTeks}). Silakan kirim *Tanggal Selesai* yang benar.\n"
                ."Contoh: 17-06-2026"
            );

            return;
        }

        $session->update([
            'state' => 'pesan_no_hp',
            'context' => array_merge($session->context ?? [], ['tanggal_keluar' => $tanggal]),
        ]);

        $tanggalTeks = Carbon::parse($tanggal)->format('d-m-Y');
        $kirimChat->sendText(
            $phoneNumber,
            "Tanggal selesai: *{$tanggalTeks}*\n\n"
            ."Terakhir, silakan kirim *No. WhatsApp/HP* yang bisa dihubungi.\n"
            ."Ketik *sama* untuk menggunakan nomor ini ({$phoneNumber})."
        );
    }

    /**
     * Step 4: nomor HP. "sama"/kosong -> pakai nomor pengirim. Lalu simpan
     * reservasi dari context dan tampilkan ringkasan + tombol Bayar/Menu.
     */
    private function inputNoHp(WhatsappSession $session, string $phoneNumber, string $rawInput, ?string $customerName, KirimChatService $kirimChat): void
    {
        $value = trim($rawInput);
        $waNumber = (! $value || in_array(Str::lower($value), ['sama', 'same', '-'], true))
            ? $phoneNumber
            : $value;

        $session->update([
            'context' => array_merge($session->context ?? [], ['wa' => $waNumber]),
        ]);

        $this->simpanReservasi($session, $phoneNumber, $rawInput, $customerName, $kirimChat);
    }

    /**
     * Parse strictly DD-MM-YYYY (atau DD/MM/YYYY) ke Y-m-d. Return null jika
     * format tidak cocok atau tanggal tidak valid.
     */
    private function parseTanggalDdMmYyyy(string $input): ?string
    {
        $value = trim($input);

        foreach (['d-m-Y', 'd/m/Y'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false && $parsed->format($format) === $value) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * Build a reservation from the step-by-step context (nama, tanggal_masuk,
     * tanggal_keluar, wa, kamar_id) and send the final interactive reply with
     * Menu Utama + Bayar.
     */
    private function simpanReservasi(WhatsappSession $session, string $phoneNumber, string $rawInput, ?string $customerName, KirimChatService $kirimChat): void
    {
        $ctx = $session->context ?? [];
        $nama = $ctx['nama'] ?? ($customerName ?: 'Pelanggan WhatsApp');
        $waNumber = $ctx['wa'] ?? $phoneNumber;
        $masuk = $ctx['tanggal_masuk'] ?? null;
        $keluar = $ctx['tanggal_keluar'] ?? null;

        $kamarId = data_get($ctx, 'kamar_id');
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

        // Simpan kode booking di context untuk langkah pembayaran berikutnya.
        $session->update([
            'state' => 'pesan_pembayaran',
            'context' => array_merge($session->context ?? [], ['booking_kode' => $reservasi->kode]),
        ]);

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
            [
                ['id' => 'menu', 'title' => 'Menu Utama'],
                ['id' => 'bayar', 'title' => 'Bayar'],
            ]
        );
    }

    /**
     * Parse a key-value multiline order form, e.g.
     *   Nama: Budi Santoso
     *   Masuk: 20 Juni 2026
     *   Keluar: 22 Juni 2026
     *   WA: 08123456789
     *
     * @return array{nama:?string,masuk:?string,keluar:?string,wa:?string}
     */
    private function parseReservasiForm(string $rawInput): array
    {
        $data = [];
        $hasKeyValue = false;

        foreach (preg_split('/\r\n|\r|\n/', $rawInput) as $line) {
            if (! str_contains($line, ':')) {
                continue;
            }

            $hasKeyValue = true;
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $data[Str::lower($key)] = $value;
        }

        $result = [
            'nama' => $data['nama'] ?? null,
            'masuk' => $data['masuk'] ?? ($data['tanggal masuk'] ?? null),
            'keluar' => $data['keluar'] ?? ($data['tanggal keluar'] ?? null),
            'wa' => $data['wa'] ?? ($data['no wa'] ?? ($data['whatsapp'] ?? null)),
        ];

        // Fallback: comma-separated positional format on a single line,
        // e.g. "Budi, 15-06-2026, 17-06-2026, 6281234567890".
        if (! $hasKeyValue && str_contains($rawInput, ',')) {
            $parts = array_map('trim', explode(',', trim($rawInput)));
            $result['nama'] = $result['nama'] ?: ($parts[0] ?? null);
            $result['masuk'] = $result['masuk'] ?: ($parts[1] ?? null);
            $result['keluar'] = $result['keluar'] ?: ($parts[2] ?? null);
            $result['wa'] = $result['wa'] ?: ($parts[3] ?? null);
        }

        return $result;
    }

    private function sendPaymentChoice(string $phoneNumber, KirimChatService $kirimChat): void
    {
        $kirimChat->sendButtons(
            $phoneNumber,
            "Silakan pilih metode pembayaran:",
            [
                ['id' => 'qris', 'title' => 'QRIS'],
                ['id' => 'transfer', 'title' => 'Transfer Bank'],
            ]
        );
    }

    private function sendQris(WhatsappSession $session, string $phoneNumber, KirimChatService $kirimChat): void
    {
        // Dummy link e-Retribusi (ganti dengan integrasi API asli nanti).
        $link = 'https://eretribusi.semarangkota.go.id/bayar/'.Str::upper(Str::random(10));

        $session->update(['state' => 'pesan_upload_bukti']);

        $kirimChat->sendText(
            $phoneNumber,
            "Pembayaran via QRIS\n\n"
            ."Silakan lakukan pembayaran melalui link/QR e-Retribusi berikut:\n{$link}\n\n"
            ."Setelah membayar, *kirim foto bukti pembayaran* langsung ke chat ini. Terima kasih."
        );
    }

    private function sendTransfer(WhatsappSession $session, string $phoneNumber, KirimChatService $kirimChat): void
    {
        // Dummy rekening (ganti dengan data resmi nanti).
        $session->update(['state' => 'pesan_upload_bukti']);

        $kirimChat->sendText(
            $phoneNumber,
            "Pembayaran via Transfer Bank\n\n"
            ."Silakan transfer ke salah satu rekening berikut:\n"
            ."- Bank Jateng: 3-001-12345-6 a.n. BKPP Kota Semarang\n"
            ."- BRI: 0123-01-001234-50-1 a.n. BKPP Kota Semarang\n\n"
            ."Setelah transfer, *kirim foto bukti pembayaran* langsung ke chat ini. Terima kasih."
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Laporan Gangguan Flow (2 steps: nomor kamar → isi laporan)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Step 1: User sends room number → save to context, ask for the report.
     */
    private function inputNomorKamarGangguan(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $nomorKamar = trim($rawInput);

        if (empty($nomorKamar)) {
            $kirimChat->sendText($phoneNumber, "Mohon kirim nomor kamar yang mengalami gangguan.\nContoh: A-101");

            return;
        }

        $session->update([
            'state' => 'gangguan_isi',
            'context' => array_merge($session->context ?? [], ['nomor_kamar' => $nomorKamar]),
        ]);

        $kirimChat->sendText(
            $phoneNumber,
            "Nomor kamar: *{$nomorKamar}*\n\nSilakan kirimkan detail gangguan yang terjadi."
        );
    }

    /**
     * Save complaint with room number from context.
     */
    private function simpanPengaduan(string $jenis, WhatsappSession $session, string $phoneNumber, string $rawInput, ?string $customerName, KirimChatService $kirimChat): void
    {
        $nomorKamar = data_get($session->context, 'nomor_kamar');

        LayananPengaduan::create([
            'jenis' => $jenis,
            'nama' => $customerName ?: 'Pelanggan WhatsApp',
            'phone_number' => $phoneNumber,
            'isi' => trim($rawInput),
            'nomor_kamar' => $nomorKamar,
            'status' => 'baru',
        ]);

        $session->update(['state' => 'main_menu', 'context' => []]);

        $label = $jenis === 'saran' ? 'Saran' : 'Laporan gangguan';
        $this->sendReturnButtons(
            $phoneNumber,
            "{$label} Anda sudah kami terima dan akan ditindaklanjuti. Terima kasih.",
            $kirimChat
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Survey Kepuasan Flow (2 steps: rating → komentar)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Step 1: User sends rating (1-5) → save to context, ask for comment.
     */
    private function inputRatingSurvey(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $rating = (int) trim($rawInput);

        if ($rating < 1 || $rating > 5) {
            $kirimChat->sendText(
                $phoneNumber,
                "Mohon berikan rating antara 1 sampai 5:\n\n"
                ."1 ⭐ Sangat Tidak Puas\n"
                ."2 ⭐⭐ Tidak Puas\n"
                ."3 ⭐⭐⭐ Cukup\n"
                ."4 ⭐⭐⭐⭐ Puas\n"
                ."5 ⭐⭐⭐⭐⭐ Sangat Puas"
            );

            return;
        }

        $session->update([
            'state' => 'survey_komentar',
            'context' => array_merge($session->context ?? [], ['survey_rating' => $rating]),
        ]);

        $stars = str_repeat('⭐', $rating);
        $kirimChat->sendText(
            $phoneNumber,
            "Rating Anda: {$stars} ({$rating}/5)\n\nSilakan kirim masukan atau komentar Anda tentang layanan kami."
        );
    }

    /**
     * Step 2: User sends comment → save survey to DB.
     */
    private function simpanSurvey(WhatsappSession $session, string $phoneNumber, string $rawInput, ?string $customerName, KirimChatService $kirimChat): void
    {
        $rating = data_get($session->context, 'survey_rating', 3);

        LayananPengaduan::create([
            'jenis' => 'survey',
            'nama' => $customerName ?: 'Pelanggan WhatsApp',
            'phone_number' => $phoneNumber,
            'isi' => trim($rawInput),
            'rating' => $rating,
            'status' => 'baru',
        ]);

        $session->update(['state' => 'main_menu', 'context' => []]);

        $this->sendReturnButtons(
            $phoneNumber,
            "Terima kasih atas survey kepuasan Anda! Masukan Anda sangat berarti untuk peningkatan layanan kami. 🙏",
            $kirimChat
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function cekBooking(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $kode = trim($rawInput);
        $reservasi = KamarReservasi::with('kamar')->where('kode', $kode)->first();

        if (! $reservasi) {
            $this->sendReturnButtons(
                $phoneNumber,
                "Kode booking \"{$kode}\" tidak ditemukan. Pastikan kode benar, atau kembali ke menu utama.",
                $kirimChat
            );

            return;
        }

        $hargaText = number_format((int) $reservasi->total_harga, 0, ',', '.');
        $kamarText = $reservasi->kamar ? $reservasi->kamar->nama : 'Belum dialokasikan';
        $tanggal = ($reservasi->tanggal_masuk && $reservasi->tanggal_keluar)
            ? $reservasi->tanggal_masuk->format('d M Y').' s/d '.$reservasi->tanggal_keluar->format('d M Y')
            : '-';
        $bayar = $reservasi->payment_status === 'paid' ? 'Lunas' : 'Belum dibayar';

        $detail = "Detail booking {$reservasi->kode}:\n\n"
            ."Pemesan: {$reservasi->nama_pemesan}\n"
            ."Kamar/kelas: {$kamarText}\n"
            ."Tanggal: {$tanggal}\n"
            ."Total: Rp{$hargaText}\n"
            ."Status reservasi: {$reservasi->status}\n"
            ."Status pembayaran: {$bayar}";

        // Belum dibayar -> tawarkan tombol Bayar; simpan kode untuk langkah bayar.
        if ($reservasi->payment_status !== 'paid') {
            $session->update([
                'state' => 'pesan_pembayaran',
                'context' => array_merge($session->context ?? [], ['booking_kode' => $reservasi->kode]),
            ]);

            $kirimChat->sendButtons($phoneNumber, $detail."\n\nSilakan lanjutkan pembayaran.", [
                ['id' => 'menu', 'title' => 'Menu Utama'],
                ['id' => 'bayar', 'title' => 'Bayar'],
            ]);

            return;
        }

        $this->sendReturnButtons($phoneNumber, $detail, $kirimChat);
    }

    /**
     * Download the WA-uploaded payment proof, store it (max 2MB), attach to the
     * reservation referenced in session context, and mark it paid.
     */
    private function handleBuktiPembayaran(WhatsappSession $session, string $phoneNumber, string $imageUrl, KirimChatService $kirimChat): void
    {
        $kode = data_get($session->context, 'booking_kode');
        $reservasi = $kode ? KamarReservasi::where('kode', $kode)->first() : null;

        if (! $reservasi) {
            $this->sendReturnButtons($phoneNumber, "Maaf, kami tidak menemukan reservasi terkait. Silakan kembali ke menu utama.", $kirimChat);

            return;
        }

        try {
            $response = Http::timeout(20)->get($imageUrl);
        } catch (\Throwable $e) {
            Log::error('Gagal unduh bukti pembayaran', ['error' => $e->getMessage(), 'url' => $imageUrl]);
            $response = null;
        }

        if (! $response || ! $response->successful()) {
            $kirimChat->sendText($phoneNumber, "Maaf, bukti pembayaran gagal diproses. Silakan kirim ulang fotonya.");

            return;
        }

        $body = $response->body();

        // Batasi maksimal 2MB.
        if (strlen($body) > 2 * 1024 * 1024) {
            $kirimChat->sendText($phoneNumber, "Ukuran foto melebihi 2MB. Silakan kirim foto bukti pembayaran yang lebih kecil.");

            return;
        }

        $ext = $this->guessImageExtension($response->header('Content-Type'), $imageUrl);
        $path = 'bukti-pembayaran/'.$reservasi->kode.'-'.Str::random(8).'.'.$ext;
        Storage::disk('public')->put($path, $body);

        // Hapus bukti lama bila ada.
        if ($reservasi->bukti_pembayaran) {
            Storage::disk('public')->delete($reservasi->bukti_pembayaran);
        }

        $reservasi->update([
            'bukti_pembayaran' => $path,
            'payment_status' => 'paid',
        ]);

        $session->update(['state' => 'main_menu']);

        $this->sendReturnButtons(
            $phoneNumber,
            "Terima kasih! Bukti pembayaran untuk booking {$reservasi->kode} sudah kami terima dan status pembayaran kini *Lunas*.",
            $kirimChat
        );
    }

    private function guessImageExtension(?string $contentType, string $url): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if ($contentType) {
            $ct = strtolower(trim(explode(';', $contentType)[0]));
            if (isset($map[$ct])) {
                return $map[$ct];
            }
        }

        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true) ? ($ext === 'jpeg' ? 'jpg' : $ext) : 'jpg';
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
            ?? Arr::get($payload, 'data.list_reply.id')
            ?? Arr::get($payload, 'data.raw.message.interactive.list_reply.id')
            ?? Arr::get($payload, 'data.raw.message.interactive.button_reply.id')
            ?? Arr::get($payload, 'data.raw.message.interactive.button.id')
            ?? Arr::get($payload, 'raw.message.interactive.list_reply.id')
            ?? Arr::get($payload, 'raw.message.interactive.button_reply.id');
    }

    /**
     * Extract an image/media URL from common KirimChat payload shapes.
     */
    private function extractImageUrl(array $payload): ?string
    {
        $url = Arr::get($payload, 'data.media_url')
            ?? Arr::get($payload, 'data.image.url')
            ?? Arr::get($payload, 'data.image.link')
            ?? Arr::get($payload, 'data.media.url')
            ?? Arr::get($payload, 'data.attachment.url')
            ?? Arr::get($payload, 'media_url')
            ?? Arr::get($payload, 'image.url')
            ?? Arr::get($payload, 'image.link')
            ?? Arr::get($payload, 'attachment.url');

        if (! $url) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
