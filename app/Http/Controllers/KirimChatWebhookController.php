<?php

namespace App\Http\Controllers;

use App\Models\ChatbotRule;
use App\Models\Kamar;
use App\Models\KamarReservasi;
use App\Models\LayananPengaduan;
use App\Models\RetribusiBilling;
use App\Models\WhatsappMessage;
use App\Models\WhatsappSession;
use App\Services\ERetribusiService;
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
    ) {}

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

        if ($session->wasRecentlyCreated) {
            $this->sendDefaultMainMenu($phoneNumber, $customerName ?: 'Sahabat Balai', $kirimChat);

            return response()->json(['success' => true]);
        }

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

            case 'pilih_jenis':
                $this->pilihJenis($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'input_jumlah':
                $this->inputJumlah($session, $phoneNumber, $rawInput, $kirimChat);

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
                $this->sendTransfer($session, $phoneNumber, $rawInput, $kirimChat);

                return;

            case 'cek_status':
                $this->cekStatusPembayaran($session, $phoneNumber, $kirimChat);

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

            case 'form_pemesanan_landing':
                $this->handleFormPemesananLanding($session, $phoneNumber, $rawInput, $customerName, $kirimChat);

                return;

            case 'konfirmasi_pesan_landing':
                $this->konfirmasiPesanLanding($session, $phoneNumber, $kirimChat);

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
        $this->sendDefaultMainMenu($phoneNumber, $name, $kirimChat);
    }

    /**
     * Menu default jika belum ada aturan di DB.
     */
    private function sendDefaultMainMenu(string $phoneNumber, string $name, KirimChatService $kirimChat): void
    {
        $body = "Halo, {$name} Selamat Datang di SAPA BALAI \u{1F44B}.\n"
            ."Smart Chatbot Layanan Balai Diklat Kota Semarang.\n\n"
            .'Silakan pilih menu layanan di bawah ini.';

        $kirimChat->sendList(
            $phoneNumber,
            $body,
            'Pilih Menu',
            [
                [
                    'title' => 'Menu Layanan',
                    'rows' => [
                        ['id' => '1', 'title' => 'Info Layanan & Pesan', 'description' => 'Lihat info layanan dan pesan kamar/kelas'],
                        ['id' => '3', 'title' => 'Laporan Gangguan', 'description' => 'Laporkan gangguan fasilitas'],
                        ['id' => '4', 'title' => 'Saran', 'description' => 'Kirim saran dan masukan'],
                        ['id' => '5', 'title' => 'Survey Kepuasan', 'description' => 'Isi survey kepuasan layanan'],
                        ['id' => '6', 'title' => 'Cek Pemesanan', 'description' => 'Periksa status booking Anda'],
                        ['id' => '7', 'title' => 'Customer Care', 'description' => 'Hubungi tim layanan pelanggan'],
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
        $rooms = $this->availability->availableRoomsWithStock($masuk, $keluar)->filter(fn ($r) => $r->tersedia > 0)->values();

        if ($rooms->isEmpty()) {
            $session->update(['state' => 'pesan_cek_tanggal']);
            $this->sendReturnButtons(
                $phoneNumber,
                "Maaf, tidak ada kamar/kelas yang Tersedia pada {$masuk} s/d {$keluar}.\nSilakan kirim tanggal lain, atau kembali ke menu utama.",
                $kirimChat
            );

            return;
        }

        $lines = $rooms->map(static function ($room): string {
            $harga = number_format((int) $room->harga_per_malam, 0, ',', '.');

            return "- {$room->jenis_kelas} (Rp{$harga}) - Tersedia: {$room->tersedia} unit";
        })->implode("\n");

        $session->update([
            'state' => 'pilih_jenis',
            'context' => array_merge($session->context ?? [], [
                'tanggal_masuk' => $masuk,
                'tanggal_keluar' => $keluar,
            ]),
        ]);

        $kirimChat->sendText(
            $phoneNumber,
            "Kamar/kelas Tersedia {$masuk} s/d {$keluar}:\n{$lines}\n\n"
            ."Silakan isi data pemesanan dengan format:\n"
            ."Nama, Instansi, Kegiatan, Jumlah peserta\n"
            .'Contoh: Budi, BKPP, Diklat ASN, 20'
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
     * Tampilkan info ketersediaan hari ini (jenis kelas + sisa unit Tersedia) dan
     * minta user memilih jenis. State -> pilih_jenis.
     */
    private function sendKamarList(WhatsappSession $session, string $phoneNumber, KirimChatService $kirimChat): void
    {
        $kamars = Kamar::orderBy('jenis_kelas')->get();

        if ($kamars->isEmpty()) {
            $this->sendReturnButtons($phoneNumber, 'Mohon maaf, belum ada data jenis kelas yang tersedia saat ini.', $kirimChat);

            return;
        }

        $lines = [];
        $map = [];
        foreach ($kamars->values() as $index => $kamar) {
            $no = $index + 1;
            $map[(string) $no] = $kamar->jenis_kelas;
            $harga = number_format((int) $kamar->harga_per_malam, 0, ',', '.');
            $stok = (int) ($kamar->stok_total ?: ($kamar->kuota_total ?: 1));
            $lines[] = "{$no}. {$kamar->jenis_kelas} - Rp{$harga}/malam (Tersedia: {$stok} unit)";
        }

        $session->update([
            'state' => 'pilih_jenis',
            'context' => array_merge($session->context ?? [], ['jenis_map' => $map]),
        ]);

        $todayTeks = Carbon::today()->format('d-m-Y');
        $kirimChat->sendText(
            $phoneNumber,
            "INFORMASI LAYANAN BALAI DIKLAT KOTA SEMARANG\n\n"
            ."Balai Diklat menyediakan layanan sewa kamar dan ruang kelas untuk kegiatan diklat, rapat, maupun kegiatan resmi lainnya.\n\n"
            ."Ketersediaan hari ini ({$todayTeks}):\n\n"
            .implode("\n", $lines)
            ."\n\nKetik nomor jenis kelas yang ingin dipesan, atau ketik *menu* untuk kembali."
        );
    }

    /**
     * User memilih jenis kelas (dari nomor di list). Simpan jenis_kelas ke
     * context, lalu minta jumlah unit. State -> pesan_jumlah.
     */
    private function pilihJenis(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $map = data_get($session->context, 'jenis_map', []);
        $choice = trim($rawInput);
        $jenisKelas = $map[$choice] ?? null;

        if (! $jenisKelas) {
            $this->sendReturnButtons(
                $phoneNumber,
                'Pilihan tidak dikenali. Ketik nomor jenis kelas yang ada di daftar, atau kembali ke menu utama.',
                $kirimChat
            );

            return;
        }

        $kamar = Kamar::with('fotos')->where('jenis_kelas', $jenisKelas)->first();
        $tersedia = (int) ($kamar?->stok_total ?: ($kamar?->kuota_total ?? 0));
        $harga = $kamar ? number_format((int) $kamar->harga_per_malam, 0, ',', '.') : '0';
        $fasilitas = $kamar?->fasilitas ?: '-';

        $session->update([
            'state' => 'pesan_jumlah',
            'context' => array_merge($session->context ?? [], [
                'jenis_kelas' => $jenisKelas,
                'kuota_total' => $tersedia,
            ]),
        ]);

        $this->sendKamarPhotos($kamar, $phoneNumber, $kirimChat);

        $kirimChat->sendText(
            $phoneNumber,
            "Jenis kelas terpilih: *{$jenisKelas}*\n"
            ."Tarif: Rp{$harga}/malam\n"
            ."Tersedia: {$tersedia} unit\n"
            ."Fasilitas: {$fasilitas}\n\n"
            ."Silakan kirim *Jumlah unit* yang ingin dipesan.\n"
            .'Contoh: 1'
        );
    }

    private function sendKamarPhotos(?Kamar $kamar, string $phoneNumber, KirimChatService $kirimChat): void
    {
        if (! $kamar) {
            return;
        }

        $fotoPaths = $kamar->allFotoPaths();
        if ($fotoPaths->isEmpty()) {
            return;
        }

        $baseUrl = rtrim((string) config('app.url'), '/');
        if (! str_starts_with($baseUrl, 'http')) {
            $baseUrl = 'https://'.$baseUrl;
        }

        foreach ($fotoPaths->take(3) as $index => $path) {
            $mediaUrl = $baseUrl.'/storage/'.ltrim($path, '/');

            $caption = $index === 0
                ? "Foto {$kamar->jenis_kelas}"
                : null;

            try {
                $kirimChat->sendImage($phoneNumber, $mediaUrl, $caption);
            } catch (\Throwable $e) {
                Log::warning('Gagal kirim foto kamar via WA', [
                    'kamar_id' => $kamar->id,
                    'foto_path' => $path,
                    'media_url' => $mediaUrl,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Step: jumlah unit. Validasi integer >= 1. State -> pesan_tanggal_masuk.
     */
    private function inputJumlah(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $jumlah = (int) trim($rawInput);

        if ($jumlah < 1) {
            $kirimChat->sendText(
                $phoneNumber,
                "Mohon kirim *Jumlah unit* yang valid (angka minimal 1).\nContoh: 1"
            );

            return;
        }

        $session->update([
            'state' => 'pesan_tanggal_masuk',
            'context' => array_merge($session->context ?? [], ['jumlah' => $jumlah]),
        ]);

        $kirimChat->sendText(
            $phoneNumber,
            "Jumlah unit: *{$jumlah}*\n\n"
            ."Silakan kirim *Tanggal Mulai* sewa dengan format DD-MM-YYYY (tanggal-bulan-tahun).\n"
            .'Contoh: 15-06-2026'
        );
    }

    /**
     * Step: tanggal mulai. Format DD-MM-YYYY. State -> pesan_tanggal_keluar.
     */
    private function inputTanggalMasuk(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $tanggal = $this->parseTanggalDdMmYyyy($rawInput);

        if (! $tanggal) {
            $kirimChat->sendText(
                $phoneNumber,
                "Format tanggal tidak sesuai. Silakan kirim *Tanggal Mulai* dengan format DD-MM-YYYY (tanggal-bulan-tahun).\n"
                .'Contoh: 15-06-2026'
            );

            return;
        }

        if (Carbon::parse($tanggal)->lt(Carbon::today())) {
            $todayTeks = Carbon::today()->format('d-m-Y');
            $kirimChat->sendText(
                $phoneNumber,
                "Tanggal mulai tidak boleh sebelum hari ini ({$todayTeks}). Silakan kirim *Tanggal Mulai* yang benar.\n"
                ."Contoh: {$todayTeks}"
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
            .'Contoh: 17-06-2026'
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
                .'Contoh: 17-06-2026'
            );

            return;
        }

        $masuk = data_get($session->context, 'tanggal_masuk');
        if ($masuk && Carbon::parse($tanggal)->lte(Carbon::parse($masuk))) {
            $masukTeks = Carbon::parse($masuk)->format('d-m-Y');
            $kirimChat->sendText(
                $phoneNumber,
                "Tanggal selesai harus setelah tanggal mulai ({$masukTeks}). Silakan kirim *Tanggal Selesai* yang benar.\n"
                .'Contoh: 17-06-2026'
            );

            return;
        }

        $session->update([
            'state' => 'pesan_nama',
            'context' => array_merge($session->context ?? [], ['tanggal_keluar' => $tanggal]),
        ]);

        $tanggalTeks = Carbon::parse($tanggal)->format('d-m-Y');
        $kirimChat->sendText(
            $phoneNumber,
            "Tanggal selesai: *{$tanggalTeks}*\n\n"
            ."Silakan kirim *Nama* pemesan.\n"
            .'Contoh: Budi Santoso'
        );
    }

    /**
     * Step: nama pemesan. State -> pesan_no_hp.
     */
    private function inputNama(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $value = trim($rawInput);

        if (in_array(Str::lower($value), ['pesan', 'menu', 'halo', '', 'sama'], true)) {
            $kirimChat->sendText(
                $phoneNumber,
                "Silakan kirim *Nama* pemesan.\nContoh: Budi Santoso"
            );

            return;
        }

        $session->update([
            'state' => 'pesan_no_hp',
            'context' => array_merge($session->context ?? [], ['nama' => $value]),
        ]);

        $kirimChat->sendText(
            $phoneNumber,
            "Nama pemesan: *{$value}*\n\n"
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
     * Build a reservation from the step-by-step context (jenis_kelas, jumlah,
     * tanggal_masuk, tanggal_keluar, nama, wa) and send the final interactive
     * reply with Menu Utama + Bayar.
     */
    private function simpanReservasi(WhatsappSession $session, string $phoneNumber, string $rawInput, ?string $customerName, KirimChatService $kirimChat): void
    {
        $ctx = $session->context ?? [];
        $nama = $ctx['nama'] ?? ($customerName ?: 'Pelanggan WhatsApp');
        $waNumber = $ctx['wa'] ?? $phoneNumber;
        $masuk = $ctx['tanggal_masuk'] ?? null;
        $keluar = $ctx['tanggal_keluar'] ?? null;
        $jenisKelas = $ctx['jenis_kelas'] ?? null;
        $jumlah = (int) ($ctx['jumlah'] ?? 1);
        if ($jumlah < 1) {
            $jumlah = 1;
        }

        $kamar = $jenisKelas ? Kamar::where('jenis_kelas', $jenisKelas)->first() : null;
        $hargaPerMalam = (int) ($kamar?->harga_per_malam ?? 0);

        // Validasi ketersediaan unit
        if ($kamar && $masuk && $keluar) {
            $tersedia = $this->availability->availableStock($kamar, $masuk, $keluar);
            if ($tersedia < $jumlah) {
                $kirimChat->sendText(
                    $phoneNumber,
                    "Maaf, jumlah pemesanan kamar melebihi ketersediaan.\n\n"
                    ."Jenis Kelas: {$jenisKelas}\nTanggal: {$masuk} s/d {$keluar}\n"
                    ."Diminta: {$jumlah} unit\nTersedia: {$tersedia} unit\n\n"
                    .'Silakan kurangi jumlah unit atau pilih tanggal lain. Ketik *menu* untuk kembali.'
                );

                return;
            }
        }

        $duration = ($masuk && $keluar)
            ? (int) max(1, Carbon::parse($masuk)->diffInDays(Carbon::parse($keluar)) ?: 1)
            : 1;
        $total = $hargaPerMalam * $duration * $jumlah;

        $reservasi = KamarReservasi::create([
            'kode' => $this->generateReservationCode(),
            'nama_pemesan' => $nama,
            'tipe_penyewa' => 'perorangan',
            'phone_number' => $waNumber,
            'jenis_kelas' => $jenisKelas,
            'jumlah' => $jumlah,
            'multiple_kamar' => false,
            'tanggal_masuk' => $masuk,
            'tanggal_keluar' => $keluar,
            'durasi_hari' => $duration,
            'jumlah_peserta' => $jumlah,
            'total_harga' => $total,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'catatan' => 'Reservasi via WhatsApp chatbot.',
        ]);

        if ($jenisKelas && $masuk && $keluar) {
            $reservasi->items()->create([
                'jenis_kelas' => $jenisKelas,
                'jumlah' => $jumlah,
                'tanggal_masuk' => $masuk,
                'tanggal_keluar' => $keluar,
                'durasi_hari' => $duration,
                'harga_per_malam' => $hargaPerMalam,
                'subtotal' => $total,
            ]);
        }

        $this->createAndSendBapendaBilling($reservasi);

        $session->update([
            'state' => 'pesan_pembayaran',
            'context' => array_merge($session->context ?? [], ['booking_kode' => $reservasi->kode]),
        ]);

        $hargaText = number_format($total, 0, ',', '.');
        $summary = "Reservasi berhasil dibuat!\n\n"
            ."Kode booking: {$reservasi->kode}\n"
            .($jenisKelas ? "Jenis kelas: {$jenisKelas} ({$jumlah} unit)\n" : '')
            .(($masuk && $keluar) ? "Tanggal: {$masuk} s/d {$keluar}\n" : '')
            ."Total: Rp{$hargaText}\n"
            ."Status: menunggu konfirmasi & pembayaran.\n\n"
            .'Terima kasih telah memesan di Balai Diklat Kota Semarang.';

        $result = $kirimChat->sendButtons(
            $phoneNumber,
            $summary,
            [
                ['id' => 'menu', 'title' => 'Menu Utama'],
                ['id' => 'bayar', 'title' => 'Bayar'],
            ]
        );

        // Fallback: kalau interactive button gagal, kirim teks biasa
        if (! ($result['success'] ?? true)) {
            $kirimChat->sendText(
                $phoneNumber,
                $summary."\n\n"
                ."Balas *bayar* untuk melanjutkan pembayaran.\n"
                .'Balas *menu* untuk kembali ke menu utama.'
            );
        }
    }

    /**
     * Handler untuk form pemesanan dari landing page. Pesan masuk dengan marker
     * "FORM_PEMESANAN_LANDING" diikuti baris key:value. Parse, validasi ketersediaan
     * unit, lalu balas dengan ringkasan + tombol Pesan Sekarang / Menu Utama.
     */
    private function handleFormPemesananLanding(
        WhatsappSession $session,
        string $phoneNumber,
        string $rawInput,
        ?string $customerName,
        KirimChatService $kirimChat
    ): void {
        $data = $this->parseLandingForm($rawInput);

        if (! $data) {
            $this->sendReturnButtons(
                $phoneNumber,
                'Maaf, format pemesanan dari landing tidak dikenali. Silakan kirim ulang formulir dari halaman web atau ketik *menu*.',
                $kirimChat
            );

            return;
        }

        $nama = $data['nama'] ?: ($customerName ?: 'Pelanggan');
        $waNumber = $data['wa'] ?: $phoneNumber;
        $tipePenyewa = $data['tipe_penyewa'] ?? 'perorangan';
        $isInstansi = $tipePenyewa === 'instansi';
        $instansi = $data['instansi'] ?? null;
        $kegiatan = $data['kegiatan'] ?? null;
        $masuk = $data['masuk'] ?? null;
        $keluar = $data['keluar'] ?? null;
        $jenisKelas = $data['jenis_kelas'] ?? null;
        $jumlahUnit = (int) ($data['jumlah_unit'] ?? 1);
        if ($jumlahUnit < 1) {
            $jumlahUnit = 1;
        }
        $items = $data['items'] ?? [];

        $kamar = $jenisKelas ? Kamar::where('jenis_kelas', $jenisKelas)->first() : null;

        if (! $kamar || ! $masuk || ! $keluar) {
            $this->sendReturnButtons(
                $phoneNumber,
                'Data pemesanan tidak lengkap (jenis kelas/tanggal belum diisi). Silakan lengkapi formulir di landing page.',
                $kirimChat
            );

            return;
        }

        $tersedia = $this->availability->availableStock($kamar, $masuk, $keluar);
        if ($tersedia < $jumlahUnit) {
            $kirimChat->sendText(
                $phoneNumber,
                "Maaf, jumlah pemesanan kamar melebihi ketersediaan.\n\n"
                ."Jenis Kelas: {$kamar->jenis_kelas}\n"
                ."Tanggal: {$masuk} s/d {$keluar}\n"
                ."Diminta: {$jumlahUnit} unit\n"
                ."Tersedia: {$tersedia} unit\n\n"
                .'Silakan kurangi jumlah unit atau pilih tanggal/kamar lain. Ketik *menu* untuk kembali.'
            );

            return;
        }

        foreach ($items as $item) {
            $ikamar = isset($item['jenis_kelas']) ? Kamar::where('jenis_kelas', $item['jenis_kelas'])->first() : null;
            if (! $ikamar) {
                continue;
            }
            $iTersedia = $this->availability->availableStock($ikamar, $item['tanggal_masuk'] ?? null, $item['tanggal_keluar'] ?? null);
            $iUnit = (int) ($item['jumlah_unit'] ?? 1);
            if ($iTersedia < $iUnit) {
                $kirimChat->sendText(
                    $phoneNumber,
                    "Maaf, jumlah pemesanan kamar melebihi ketersediaan untuk item tambahan.\n\n"
                    ."Jenis Kelas: {$ikamar->jenis_kelas}\n"
                    .'Tanggal: '.($item['tanggal_masuk'] ?? '-').' s/d '.($item['tanggal_keluar'] ?? '-')."\n"
                    ."Diminta: {$iUnit} unit\n"
                    ."Tersedia: {$iTersedia} unit\n\n"
                    .'Silakan sesuaikan pemesanan. Ketik *menu* untuk kembali.'
                );

                return;
            }
        }

        $session->update([
            'state' => 'pesan_konfirmasi_landing',
            'context' => [
                'nama' => $nama,
                'wa' => $waNumber,
                'tipe_penyewa' => $tipePenyewa,
                'instansi' => $instansi,
                'kegiatan' => $kegiatan,
                'jenis_kelas' => $kamar->jenis_kelas,
                'kamar_harga' => $kamar->harga_per_malam,
                'tanggal_masuk' => $masuk,
                'tanggal_keluar' => $keluar,
                'jumlah_unit' => $jumlahUnit,
                'items' => $items,
            ],
        ]);

        $duration = (int) max(1, Carbon::parse($masuk)->diffInDays(Carbon::parse($keluar)) ?: 1);
        $total = $kamar->harga_per_malam * $duration * $jumlahUnit;
        foreach ($items as $item) {
            $ikamar = isset($item['jenis_kelas']) ? Kamar::where('jenis_kelas', $item['jenis_kelas'])->first() : null;
            if (! $ikamar) {
                continue;
            }
            $iDur = (int) max(1, Carbon::parse($item['tanggal_masuk'])->diffInDays(Carbon::parse($item['tanggal_keluar'])) ?: 1);
            $iUnit = (int) ($item['jumlah_unit'] ?? 1);
            $total += $ikamar->harga_per_malam * $iDur * $iUnit;
        }

        $hargaText = number_format($total, 0, ',', '.');
        $ringkasan = "RINGKASAN PEMESANAN (dari Landing Page)\n\n"
            ."Nama: {$nama}\n"
            ."No WA: {$waNumber}\n"
            .'Tipe Penyewa: '.($isInstansi ? 'Instansi' : 'Perorangan')."\n";
        if ($isInstansi) {
            $ringkasan .= 'Instansi: '.($instansi ?: '-')."\n";
            $ringkasan .= 'Kegiatan: '.($kegiatan ?: '-')."\n";
        }
        $ringkasan .= "Tanggal: {$masuk} s/d {$keluar}\n"
            ."Jenis Kelas: {$kamar->jenis_kelas}\n"
            ."Jumlah Unit: {$jumlahUnit}\n";
        if (! empty($items)) {
            $ringkasan .= "--- Item Tambahan ---\n";
            foreach ($items as $i => $item) {
                $ringkasan .= sprintf(
                    "Item %d: %s | %s s/d %s | %s unit\n",
                    $i + 1,
                    $item['jenis_kelas'] ?? '-',
                    $item['tanggal_masuk'] ?? '-',
                    $item['tanggal_keluar'] ?? '-',
                    $item['jumlah_unit'] ?? 1
                );
            }
        }
        $ringkasan .= "\nTotal: Rp{$hargaText}\n\nApakah Anda yakin ingin memesan?";

        $kirimChat->sendButtons($phoneNumber, $ringkasan, [
            ['id' => 'pesan_sekarang', 'title' => 'Pesan Sekarang'],
            ['id' => 'menu', 'title' => 'Menu Utama'],
        ]);
    }

    /**
     * Konfirmasi "Pesan Sekarang" dari form landing: simpan reservasi ke DB,
     * lalu lanjut ke flow pembayaran (Bayar / Menu Utama).
     */
    private function konfirmasiPesanLanding(WhatsappSession $session, string $phoneNumber, KirimChatService $kirimChat): void
    {
        $ctx = $session->context ?? [];
        $nama = $ctx['nama'] ?? 'Pelanggan';
        $waNumber = $ctx['wa'] ?? $phoneNumber;
        $tipePenyewa = $ctx['tipe_penyewa'] ?? 'perorangan';
        $isInstansi = $tipePenyewa === 'instansi';
        $instansi = $ctx['instansi'] ?? null;
        $kegiatan = $ctx['kegiatan'] ?? null;
        $masuk = $ctx['tanggal_masuk'] ?? null;
        $keluar = $ctx['tanggal_keluar'] ?? null;
        $jenisKelas = $ctx['jenis_kelas'] ?? null;
        $jumlahUnit = (int) ($ctx['jumlah_unit'] ?? 1);
        $items = $ctx['items'] ?? [];
        $kamar = $jenisKelas ? Kamar::where('jenis_kelas', $jenisKelas)->first() : null;

        if ($kamar && $masuk && $keluar) {
            $tersedia = $this->availability->availableStock($kamar, $masuk, $keluar);
            if ($tersedia < $jumlahUnit) {
                $kirimChat->sendText(
                    $phoneNumber,
                    "Maaf, saat konfirmasi ketersediaan kamar berkurang.\n\n"
                    ."Jenis Kelas: {$kamar->jenis_kelas}\nTersedia: {$tersedia} unit\nDiminta: {$jumlahUnit} unit\n\n"
                    .'Silakan ulangi pemesanan dengan jumlah yang lebih kecil. Ketik *menu* untuk kembali.'
                );

                return;
            }
        }

        $duration = ($masuk && $keluar)
            ? (int) max(1, Carbon::parse($masuk)->diffInDays(Carbon::parse($keluar)) ?: 1)
            : 1;
        $total = ($kamar?->harga_per_malam ?? 0) * $duration * $jumlahUnit;

        $reservasi = KamarReservasi::create([
            'kode' => $this->generateReservationCode(),
            'nama_pemesan' => $nama,
            'tipe_penyewa' => $tipePenyewa,
            'instansi' => $isInstansi ? $instansi : null,
            'kegiatan' => $isInstansi ? $kegiatan : null,
            'phone_number' => $waNumber,
            'jenis_kelas' => $jenisKelas,
            'jumlah' => $jumlahUnit,
            'multiple_kamar' => ! empty($items),
            'tanggal_masuk' => $masuk,
            'tanggal_keluar' => $keluar,
            'durasi_hari' => $duration,
            'jumlah_peserta' => $jumlahUnit,
            'total_harga' => $total,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'catatan' => 'Reservasi via Landing Page (form WhatsApp).',
        ]);

        if ($kamar && $masuk && $keluar) {
            $reservasi->items()->create([
                'jenis_kelas' => $kamar->jenis_kelas,
                'jumlah' => $jumlahUnit,
                'tanggal_masuk' => $masuk,
                'tanggal_keluar' => $keluar,
                'durasi_hari' => $duration,
                'harga_per_malam' => $kamar->harga_per_malam,
                'subtotal' => $kamar->harga_per_malam * $duration * $jumlahUnit,
            ]);
        }

        foreach ($items as $item) {
            $ikamar = isset($item['jenis_kelas']) ? Kamar::where('jenis_kelas', $item['jenis_kelas'])->first() : null;
            if (! $ikamar || empty($item['tanggal_masuk']) || empty($item['tanggal_keluar'])) {
                continue;
            }
            $iDur = (int) max(1, Carbon::parse($item['tanggal_masuk'])->diffInDays(Carbon::parse($item['tanggal_keluar'])) ?: 1);
            $iUnit = (int) ($item['jumlah_unit'] ?? 1);
            $iSub = $ikamar->harga_per_malam * $iDur * $iUnit;
            $total += $iSub;
            $reservasi->items()->create([
                'jenis_kelas' => $ikamar->jenis_kelas,
                'jumlah' => $iUnit,
                'tanggal_masuk' => $item['tanggal_masuk'],
                'tanggal_keluar' => $item['tanggal_keluar'],
                'durasi_hari' => $iDur,
                'harga_per_malam' => $ikamar->harga_per_malam,
                'subtotal' => $iSub,
            ]);
        }

        $reservasi->update(['total_harga' => $total]);

        $this->createAndSendBapendaBilling($reservasi);

        $session->update([
            'state' => 'pesan_pembayaran',
            'context' => array_merge($ctx, ['booking_kode' => $reservasi->kode, 'total_harga' => $total]),
        ]);

        $hargaText = number_format($total, 0, ',', '.');
        $kirimChat->sendButtons(
            $phoneNumber,
            "Reservasi berhasil dibuat!\n\n"
            ."Kode booking: {$reservasi->kode}\n"
            .($kamar ? "Jenis kelas: {$kamar->jenis_kelas}\n" : '')
            .(($masuk && $keluar) ? "Tanggal: {$masuk} s/d {$keluar}\n" : '')
            ."Total: Rp{$hargaText}\n"
            ."Status: menunggu pembayaran.\n\n"
            .'Pilih metode pembayaran atau kembali ke menu utama.',
            [
                ['id' => 'menu', 'title' => 'Menu Utama'],
                ['id' => 'bayar', 'title' => 'Bayar'],
            ]
        );
    }

    /**
     * Parse pesan form landing page. Format:
     *   FORM_PEMESANAN_LANDING
     *   Nama: ...
     *   No WA: ...
     *   Tipe Penyewa: ...
     *   Instansi: ...
     *   Kegiatan: ...
     *   Tanggal Masuk: YYYY-MM-DD
     *   Tanggal Keluar: YYYY-MM-DD
     *   Jenis Kelas: ...
     *   Jumlah Unit: N
     *   --- Item Tambahan ---
     *   Item 1: <jenis> | <masuk> s/d <keluar> | <unit> unit
     */
    private function parseLandingForm(string $rawInput): ?array
    {
        $text = trim($rawInput);
        if (! str_starts_with(strtoupper($text), 'FORM_PEMESANAN_LANDING')) {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $text);
        $data = [];
        $inItems = false;
        $items = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strtoupper($line) === 'FORM_PEMESANAN_LANDING') {
                continue;
            }
            if (str_starts_with($line, '--- Item Tambahan ---')) {
                $inItems = true;

                continue;
            }

            if ($inItems && preg_match('/^Item\s+\d+:\s*(.+?)\s*\|\s*(.+?)\s*s\/d\s*(.+?)\s*\|\s*(\d+)\s*unit$/i', $line, $m)) {
                $items[] = [
                    'jenis_kelas' => trim($m[1]),
                    'tanggal_masuk' => $this->normalizeDate(trim($m[2])),
                    'tanggal_keluar' => $this->normalizeDate(trim($m[3])),
                    'jumlah_unit' => (int) $m[4],
                ];

                continue;
            }

            if (! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $k = Str::lower($key);
            $data[$k] = $value;
        }

        return [
            'nama' => $data['nama'] ?? null,
            'wa' => $data['no wa'] ?? ($data['wa'] ?? ($data['whatsapp'] ?? null)),
            'tipe_penyewa' => ($data['tipe penyewa'] ?? '') === 'instansi' ? 'instansi' : 'perorangan',
            'instansi' => $data['instansi'] ?? null,
            'kegiatan' => $data['kegiatan'] ?? null,
            'masuk' => $this->normalizeDate($data['tanggal masuk'] ?? null),
            'keluar' => $this->normalizeDate($data['tanggal keluar'] ?? null),
            'jenis_kelas' => $data['jenis kelas'] ?? null,
            'jumlah_unit' => (int) ($data['jumlah unit'] ?? 1),
            'items' => $items,
        ];
    }

    private function normalizeDate(?string $value): ?string
    {
        if (! $value || $value === '-') {
            return null;
        }
        $value = trim($value);
        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return null;
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
        $result = $kirimChat->sendList(
            $phoneNumber,
            'Silakan pilih metode pembayaran:',
            'Pilih Metode',
            [
                [
                    'title' => 'Metode Pembayaran',
                    'rows' => [
                        ['id' => 'qris', 'title' => 'QRIS', 'description' => 'Bayar dengan QRIS e-Retribusi'],
                        ['id' => 'bank_jateng', 'title' => 'Bank Jateng', 'description' => 'I-Banking Bank Jateng'],
                        ['id' => 'bank_bri', 'title' => 'Bank BRI', 'description' => 'BRI Mobile transfer Bank Jateng'],
                        ['id' => 'bank_mandiri', 'title' => 'Bank Mandiri', 'description' => "Livin' transfer Bank Jateng"],
                        ['id' => 'bank_bni', 'title' => 'Bank BNI', 'description' => 'ATM BNI transfer bank lain'],
                        ['id' => 'bank_bca', 'title' => 'Bank BCA', 'description' => 'M-Banking BCA antar bank'],
                    ],
                ],
            ],
            'Pembayaran',
            'Kode bayar transfer: 73 + id billing.'
        );

        // Fallback: jika KirimChat API gagal mengirim interactive list, kirim teks.
        if (! ($result['success'] ?? true)) {
            $kirimChat->sendText(
                $phoneNumber,
                "Silakan pilih metode pembayaran:\n\n"
                ."O QRIS\n"
                ."O Bank Jateng\n"
                ."O Bank BRI\n"
                ."O Bank Mandiri\n"
                ."O Bank BNI\n"
                ."O Bank BCA\n\n"
                .'Balas nama metode, contoh: *QRIS* atau *Bank BRI*.'
            );
        }
    }

    private function sendQris(WhatsappSession $session, string $phoneNumber, KirimChatService $kirimChat): void
    {
        $kode = data_get($session->context, 'booking_kode');
        $reservasi = $kode ? KamarReservasi::where('kode', $kode)->first() : null;

        if (! $reservasi) {
            $kirimChat->sendText(
                $phoneNumber,
                'Maaf, data reservasi tidak ditemukan. Ketik *menu* untuk kembali ke menu utama.'
            );

            return;
        }

        $billing = $this->createBillingForReservasi($reservasi);
        $service = app(ERetribusiService::class);

        $needProcessing = $billing->status !== 'sent' || ! $billing->link_qris;

        if ($needProcessing) {
            $kirimChat->sendText(
                $phoneNumber,
                "Sedang memproses pembayaran Anda ke sistem e-Retribusi Bapenda...\n\nMohon tunggu sebentar."
            );
        }

        if ($billing->status !== 'sent') {
            $result = $service->sendBapendaBilling($billing);

            if (! $result['success']) {
                Log::error('sendQris: Bapenda billing failed', [
                    'reservasi_id' => $reservasi->id,
                    'billing_id' => $billing->id,
                    'error' => $result['message'] ?? 'Unknown',
                ]);

                $kirimChat->sendText(
                    $phoneNumber,
                    "Maaf, terjadi kesalahan saat membuat billing e-Retribusi. Tim kami akan menghubungi Anda shortly.\n\n"
                    .'Ketik *menu* untuk kembali, atau hubungi admin langsung.'
                );

                return;
            }
        }

        $billing->refresh();
        $linkQris = $billing->link_qris;

        if (! $linkQris) {
            $qrisResult = $service->fetchAndSaveQris($billing);
            if ($qrisResult['success'] && isset($qrisResult['link_qris'])) {
                $linkQris = $qrisResult['link_qris'];
            }
        }

        $session->update(['state' => 'pesan_upload_bukti']);

        $nominalText = 'Rp'.number_format($reservasi->total_harga, 0, ',', '.');
        $expiredText = $billing->tgl_expired
            ? Carbon::parse($billing->tgl_expired)->format('d M Y H:i')
            : now()->addDays(7)->format('d M Y H:i');

        $imageUrl = $billing->link_qris_image;

        if (! $imageUrl && $linkQris) {
            $billing->refresh();
            $imageUrl = $service->downloadQrisImage($billing);
        }

        if ($imageUrl) {
            $caption = "Pembayaran via QRIS\n\n"
                ."Reservasi: {$reservasi->kode}\n"
                ."Nominal: {$nominalText}\n"
                ."Berlaku sampai: {$expiredText}\n\n";

            if ($linkQris) {
                $caption .= "Link Pembayaran:\n{$linkQris}\n\n";
            }

            $caption .= "Scan QR code di atas atau klik link untuk membayar.\n\n"
                .'Setelah membayar, *kirim foto bukti pembayaran* langsung ke chat ini. Terima kasih.';

            $kirimChat->sendImage($phoneNumber, $imageUrl, $caption);
        } else {
            $message = "Pembayaran via QRIS\n\n"
                ."Reservasi: {$reservasi->kode}\n"
                ."Nominal: {$nominalText}\n"
                ."Berlaku sampai: {$expiredText}\n\n";

            if ($linkQris) {
                $message .= "Link QRIS:\n{$linkQris}\n\n";
            }

            if (! $linkQris) {
                $message .= "Link QRIS belum tersedia. Silakan ketik *bayar* lagi beberapa saat lagi atau hubungi admin.\n\n";
            }

            $message .= 'Setelah membayar via QRIS, *kirim foto bukti pembayaran* langsung ke chat ini. Terima kasih.';

            $kirimChat->sendText($phoneNumber, $message);
        }
    }

    private function sendTransfer(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $kode = data_get($session->context, 'booking_kode');
        $reservasi = $kode ? KamarReservasi::where('kode', $kode)->first() : null;

        if (! $reservasi) {
            $kirimChat->sendText(
                $phoneNumber,
                'Maaf, data reservasi tidak ditemukan. Ketik *menu* untuk kembali ke menu utama.'
            );

            return;
        }

        $billing = $this->createBillingForReservasi($reservasi);
        $service = app(ERetribusiService::class);

        if ($billing->status !== 'sent' || ! $billing->id_billing) {
            $kirimChat->sendText(
                $phoneNumber,
                "Sedang membuat kode bayar e-Retribusi Bapenda untuk transfer bank...\n\nMohon tunggu sebentar."
            );

            $result = $service->sendBapendaBilling($billing);

            if (! $result['success']) {
                Log::error('sendTransfer: Bapenda billing failed', [
                    'reservasi_id' => $reservasi->id,
                    'billing_id' => $billing->id,
                    'error' => $result['message'] ?? 'Unknown',
                ]);

                $kirimChat->sendText(
                    $phoneNumber,
                    "Maaf, terjadi kesalahan saat membuat kode bayar e-Retribusi. Tim kami akan menghubungi Anda.\n\nKetik *menu* untuk kembali, atau hubungi admin langsung."
                );

                return;
            }

            $billing->refresh();
        }

        $session->update(['state' => 'pesan_upload_bukti']);

        $bank = $this->resolveTransferBank($rawInput);
        $kodeBayar = '73'.(string) ($billing->kodebayar ?: $billing->id_billing);
        $nominalText = 'Rp'.number_format($reservasi->total_harga, 0, ',', '.');

        $message = "Pembayaran via {$bank['label']}\n\n"
            ."Reservasi: {$reservasi->kode}\n"
            ."Nominal: {$nominalText}\n"
            ."Kode bayar: {$kodeBayar}\n\n"
            .$this->buildTransferInstruction($bank['key'], $kodeBayar, $nominalText)
            ."\n\nSetelah transfer, *kirim foto bukti pembayaran* langsung ke chat ini. Sistem akan menyimpan bukti dan cek status pembayaran ke e-Retribusi Bapenda secara otomatis. Terima kasih.";

        $kirimChat->sendText($phoneNumber, $message);
    }

    /**
     * @return array{key:string,label:string}
     */
    private function resolveTransferBank(string $rawInput): array
    {
        $input = Str::lower($rawInput);

        if (str_contains($input, 'bri')) {
            return ['key' => 'bri', 'label' => 'Bank BRI'];
        }
        if (str_contains($input, 'mandiri')) {
            return ['key' => 'mandiri', 'label' => 'Bank Mandiri'];
        }
        if (str_contains($input, 'bni')) {
            return ['key' => 'bni', 'label' => 'Bank BNI'];
        }
        if (str_contains($input, 'bca')) {
            return ['key' => 'bca', 'label' => 'Bank BCA'];
        }

        return ['key' => 'jateng', 'label' => 'Bank Jateng'];
    }

    private function buildTransferInstruction(string $bankKey, string $kodeBayar, string $nominalText): string
    {
        return match ($bankKey) {
            'bri' => "BRI MOBILE\n"
                ."1. Login Aplikasi BRI MOBILE\n"
                ."2. Klik Menu Transfer\n"
                ."3. Klik Tombol Tambah Daftar Baru\n"
                ."4. Pilih Bank Jateng\n"
                ."5. Masukkan Nomor Rekening dengan kode bayar penulisan 73+kodebayar ({$kodeBayar})\n"
                ."6. Cek Nama yang Muncul didalam tampilan\n"
                ."7. Masukkan nominal transfer sesuai dengan tagihan pajak yang tertera di cetakan kode bayar terbaru ({$nominalText})\n"
                ."8. Klik Transfer\n"
                ."9. Cek Kembali Nomor Tujuan\n"
                .'10. Klik Transfer',
            'mandiri' => "Livin' Bank Mandiri\n"
                ."1. Masuk menu transfer\n"
                ."2. Masuk menu ke bank lain dalam negeri\n"
                ."3. Klik rekening tujuan\n"
                ."4. Pilih nama bank pilih bank jateng\n"
                ."5. Input format 73+kodebayar ({$kodeBayar})\n"
                ."6. Input nominal pajak sesuai cetakan kode bayar terbaru ({$nominalText})\n"
                ."7. Masukkan deskripsi dengan kode bayar\n"
                ."8. Klik lanjutkan\n"
                ."9. Lalu periksa nominal pajaknya dan nama Rekening\n"
                .'10. Klik lanjutkan',
            'bni' => "ATM Bank BNI\n"
                ."1. Klik menu lainnya\n"
                ."2. Pilih Transfer\n"
                ."3. Pilih Dari rekening tabungan\n"
                ."4. Pilih Ke rekening bank lain\n"
                ."5. Masukkan kode bayar 11373+kodebayar (113{$kodeBayar})\n"
                ."6. Input nominal pajak sesuai cetakan kode bayar terbaru ({$nominalText})\n"
                ."7. Pilih Dari rekening tabungan\n"
                ."8. Pilih tekan jika benar\n"
                ."9. Pastikan kodebayar, nama penerima, nominal pajak sama dengan cetakan kode bayar.\n"
                .'10. Lalu tekan Ya',
            'bca' => "M-Banking Bank BCA\n"
                ."1. Buka Aplikasi M-Banking Bank BCA\n"
                ."2. Klik menu m-Transfer\n"
                ."3. Klik menu Antar Bank pada kolom Daftar Transfer\n"
                ."4. Masukkan kode bayar pada No. Rekening Tujuan dengan kode awal 73+kodebayar ({$kodeBayar})\n"
                ."5. Pilih Bank Jateng, lalu Send\n"
                ."6. Klik menu Antar Bank pada kolom Transfer\n"
                ."7. Pilih Bank Jateng\n"
                ."8. Pilih Ke Rekening Tujuan, pilih sesuai kode bayar\n"
                ."9. Masukkan jumlah bayar sesuai yang tercantum di lembar Kode Bayar ({$nominalText})\n"
                .'10. Klik Send',
            default => "I-Banking Bank Jateng\n"
                ."1. Masuk i-banking Bank Jateng\n"
                ."2. Klik menu pembayaran\n"
                ."3. Klik pajak/retribusi\n"
                ."4. Pilih penyedia jasa Retribusi\n"
                ."5. Periksa data konfirmasi pembayaran pajak\n"
                ."6. Masukkan pin\n"
                .'7. Klik proses',
        };
    }

    private function createBillingForReservasi(KamarReservasi $reservasi): RetribusiBilling
    {
        $existing = RetribusiBilling::where('kamar_reservasi_id', $reservasi->id)
            ->whereIn('status', ['draft', 'sent', 'failed'])
            ->latest()
            ->first();

        if ($existing) {
            return $existing;
        }

        $jenisKelas = $reservasi->jenis_kelas ?? '-';
        $durasi = $reservasi->durasi_hari ?? 1;

        return RetribusiBilling::create([
            'kamar_reservasi_id' => $reservasi->id,
            'tanggal' => now(),
            'keterangan' => "Sewa {$jenisKelas} selama {$durasi} hari",
            'kredit' => $reservasi->total_harga ?? 0,
            'noskpd' => '1111',
            'periode' => (string) now()->year,
            'npwrd' => '-',
            'nama_wr' => $reservasi->nama_pemesan ?? 'BKPP',
            'no_ketetapan' => 'A'.$reservasi->id,
            'nominal' => $reservasi->total_harga ?? 0,
            'tahun' => (string) now()->year,
            'tgl_expired' => now()->addDays(7)->format('Y-m-d'),
            'keterangan_bapenda' => "Sewa {$jenisKelas} selama {$durasi} hari",
            'status' => 'draft',
        ]);
    }

    private function createAndSendBapendaBilling(KamarReservasi $reservasi): void
    {
        try {
            $billing = $this->createBillingForReservasi($reservasi);
            $service = app(ERetribusiService::class);
            $result = $service->sendBapendaBilling($billing);

            if (! $result['success']) {
                Log::warning('Auto Bapenda billing failed (non-blocking)', [
                    'reservasi_id' => $reservasi->id,
                    'billing_id' => $billing->id,
                    'error' => $result['message'] ?? 'Unknown',
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('Auto Bapenda billing exception (non-blocking)', [
                'reservasi_id' => $reservasi->id,
                'error' => $e->getMessage(),
            ]);
        }
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
                .'5 ⭐⭐⭐⭐⭐ Sangat Puas'
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
            'Terima kasih atas survey kepuasan Anda! Masukan Anda sangat berarti untuk peningkatan layanan kami. 🙏',
            $kirimChat
        );
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function cekBooking(WhatsappSession $session, string $phoneNumber, string $rawInput, KirimChatService $kirimChat): void
    {
        $kode = trim($rawInput);
        $reservasi = KamarReservasi::with('items')->where('kode', $kode)->first();

        if (! $reservasi) {
            $this->sendReturnButtons(
                $phoneNumber,
                "Kode booking \"{$kode}\" tidak ditemukan. Pastikan kode benar, atau kembali ke menu utama.",
                $kirimChat
            );

            return;
        }

        $jenisText = $reservasi->jenis_kelas
            ? $reservasi->jenis_kelas.' ('.($reservasi->jumlah ?? 1).' unit)'
            : 'Belum dialokasikan';
        $tanggal = ($reservasi->tanggal_masuk && $reservasi->tanggal_keluar)
            ? $reservasi->tanggal_masuk->format('d M Y').' s/d '.$reservasi->tanggal_keluar->format('d M Y')
            : '-';
        $hargaText = number_format((int) $reservasi->total_harga, 0, ',', '.');
        $bayar = $reservasi->payment_status === 'paid' ? 'Lunas' : 'Belum dibayar';

        $detail = "Detail booking {$reservasi->kode}:\n\n"
            ."Pemesan: {$reservasi->nama_pemesan}\n"
            ."Jenis kelas: {$jenisText}\n"
            ."Tanggal: {$tanggal}\n"
            ."Total: Rp{$hargaText}\n"
            ."Status reservasi: {$reservasi->status}\n"
            ."Status pembayaran: {$bayar}";

        if ($reservasi->payment_status !== 'paid') {
            $session->update([
                'state' => 'pesan_pembayaran',
                'context' => array_merge($session->context ?? [], ['booking_kode' => $reservasi->kode]),
            ]);

            $kirimChat->sendButtons($phoneNumber, $detail."\n\nSilakan pilih tindakan:", [
                ['id' => 'menu', 'title' => 'Menu Utama'],
                ['id' => 'cek_status', 'title' => 'Cek Status Pembayaran'],
                ['id' => 'bayar', 'title' => 'Bayar'],
            ]);

            return;
        }

        $this->sendReturnButtons($phoneNumber, $detail, $kirimChat);
    }

    private function cekStatusPembayaran(WhatsappSession $session, string $phoneNumber, KirimChatService $kirimChat): void
    {
        $kode = data_get($session->context, 'booking_kode');
        $reservasi = $kode ? KamarReservasi::with('retribusiBillings')->where('kode', $kode)->first() : null;

        if (! $reservasi) {
            $kirimChat->sendText($phoneNumber, 'Data reservasi tidak ditemukan. Ketik *menu* untuk kembali.');

            return;
        }

        $billing = $reservasi->retribusiBillings->last();

        if (! $billing || ! $billing->id_billing) {
            $kirimChat->sendButtons($phoneNumber, 'Belum ada billing e-Retribusi untuk reservasi ini.', [
                ['id' => 'menu', 'title' => 'Menu Utama'],
                ['id' => 'bayar', 'title' => 'Bayar'],
            ]);

            return;
        }

        $kirimChat->sendText($phoneNumber, 'Sedang memeriksa status pembayaran...');

        $service = app(ERetribusiService::class);
        $result = $service->checkBilling((string) $billing->id_billing);

        if ($result['success'] && isset($result['response']['data'])) {
            $data = $result['response']['data'];
            $tglBayar = $data['tgl_bayar'] ?? null;

            if (! empty($tglBayar)) {
                if (! $billing->isPaid()) {
                    $paidAt = Carbon::parse($tglBayar);
                    $billing->markPaid();
                    if ($billing->reservasi) {
                        $billing->reservasi->update(['payment_status' => 'paid']);
                    }
                }

                $kirimChat->sendButtons($phoneNumber,
                    "Status pembayaran: *LUNAS*\n"
                    ."Tanggal bayar: {$tglBayar}\n"
                    ."Kode booking: {$reservasi->kode}\n\n"
                    .'Terima kasih telah melakukan pembayaran.',
                    [['id' => 'menu', 'title' => 'Menu Utama']]
                );

                return;
            }
        }

        $kirimChat->sendButtons($phoneNumber,
            "Status pembayaran: *BELUM LUNAS*\n"
            ."Kode booking: {$reservasi->kode}\n\n"
            .'Silakan lanjutkan pembayaran.',
            [
                ['id' => 'menu', 'title' => 'Menu Utama'],
                ['id' => 'bayar', 'title' => 'Bayar'],
            ]
        );
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
            $this->sendReturnButtons($phoneNumber, 'Maaf, kami tidak menemukan reservasi terkait. Silakan kembali ke menu utama.', $kirimChat);

            return;
        }

        try {
            $response = Http::timeout(20)->get($imageUrl);
        } catch (\Throwable $e) {
            Log::error('Gagal unduh bukti pembayaran', ['error' => $e->getMessage(), 'url' => $imageUrl]);
            $response = null;
        }

        if (! $response || ! $response->successful()) {
            $kirimChat->sendText($phoneNumber, 'Maaf, bukti pembayaran gagal diproses. Silakan kirim ulang fotonya.');

            return;
        }

        $body = $response->body();

        // Batasi maksimal 2MB.
        if (strlen($body) > 2 * 1024 * 1024) {
            $kirimChat->sendText($phoneNumber, 'Ukuran foto melebihi 2MB. Silakan kirim foto bukti pembayaran yang lebih kecil.');

            return;
        }

        // Validasi konten: hanya gambar valid (magic bytes), tolak script/injeksi/file palsu.
        if (! $this->isValidImageContent($body)) {
            Log::warning('Upload bukti ditolak: konten bukan gambar valid', [
                'kode' => $kode,
                'content_type' => $response->header('Content-Type'),
                'size' => strlen($body),
                'first_bytes' => bin2hex(substr($body, 0, 16)),
            ]);
            $kirimChat->sendText($phoneNumber, 'Maaf, file yang dikirim bukan gambar yang valid. Silakan kirim foto bukti pembayaran asli (JPG/PNG/WebP).');

            return;
        }

        $ext = $this->guessImageExtension($response->header('Content-Type'), $imageUrl);
        $path = 'bukti-pembayaran/'.$reservasi->kode.'-'.Str::random(8).'.'.$ext;
        Storage::disk('public')->put($path, $body);

        // Hapus bukti lama bila ada.
        if ($reservasi->bukti_pembayaran) {
            Storage::disk('public')->delete($reservasi->bukti_pembayaran);
        }

        // Simpan bukti dulu (selalu), lalu cek status ke Bapenda.
        $reservasi->update([
            'bukti_pembayaran' => $path,
        ]);

        // Cek status pembayaran ke Bapenda e-Retribusi.
        $billing = $reservasi->retribusiBillings()->latest()->first();
        $verifiedByBapenda = false;

        if ($billing && $billing->id_billing) {
            $service = app(ERetribusiService::class);
            $statusResult = $service->checkBilling((string) $billing->id_billing);

            if ($statusResult['success'] && isset($statusResult['response']['data'])) {
                $data = $statusResult['response']['data'];
                $tglBayar = $data['tgl_bayar'] ?? null;

                if (! empty($tglBayar)) {
                    // Bapenda konfirmasi sudah dibayar → auto lunas.
                    if (! $billing->isPaid()) {
                        $billing->markPaid();
                    }
                    $reservasi->update(['payment_status' => 'paid']);
                    $verifiedByBapenda = true;
                }
            }
        }

        $session->update(['state' => 'main_menu']);

        if ($verifiedByBapenda) {
            $this->sendReturnButtons(
                $phoneNumber,
                "Terima kasih! Bukti pembayaran untuk booking *{$reservasi->kode}* sudah kami terima.\n\n"
                ."Status pembayaran: *LUNAS* ✅\n"
                .'Pembayaran Anda telah terverifikasi otomatis oleh sistem.',
                $kirimChat
            );
        } else {
            $this->sendReturnButtons(
                $phoneNumber,
                "Terima kasih! Bukti pembayaran untuk booking *{$reservasi->kode}* sudah kami terima.\n\n"
                ."Bukti pembayaran Anda akan *diverifikasi oleh admin*.\n"
                ."Kami akan mengonfirmasi setelah pembayaran diverifikasi.\n\n"
                .'Ketik *menu* untuk kembali ke menu utama.',
                $kirimChat
            );
        }
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

    /**
     * Validasi konten gambar via magic bytes (bukan cuma ekstensi/Content-Type).
     * Tolak file non-gambar (script, HTML, PHP, zip, dll) meski ekstensi/header dipalsukan.
     */
    private function isValidImageContent(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        $header = substr($body, 0, 12);

        // JPEG: FF D8 FF
        if (str_starts_with($header, "\xFF\xD8\xFF")) {
            return true;
        }

        // PNG: 89 50 4E 47 0D 0A 1A 0A
        if (str_starts_with($header, "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A")) {
            return true;
        }

        // WebP: RIFF....WEBP
        if (str_starts_with($header, 'RIFF') && strlen($body) >= 12 && substr($body, 8, 4) === 'WEBP') {
            return true;
        }

        // GIF: GIF89a or GIF87a
        if (str_starts_with($header, 'GIF89a') || str_starts_with($header, 'GIF87a')) {
            return true;
        }

        // BMP: BM
        if (str_starts_with($header, 'BM')) {
            return true;
        }

        return false;
    }

    private function generateReservationCode(): string
    {
        do {
            $kode = 'BKPP-'.now()->format('YmdHis').'-'.random_int(100, 999);
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
