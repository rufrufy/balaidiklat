<?php

namespace App\Services;

use App\Models\ChatbotTemplate;
use Illuminate\Support\Facades\Log;

class ChatbotTemplateService
{
    /**
     * Render template by key with placeholder substitution.
     * Missing placeholders stay as-is (visible, easy to spot).
     */
    public function render(string $key, array $vars = []): ?string
    {
        $template = ChatbotTemplate::where('key', $key)->first();

        if (! $template) {
            Log::warning('Chatbot template not found', ['key' => $key]);

            return null;
        }

        $content = $template->content;

        foreach ($vars as $placeholder => $value) {
            $content = str_replace("{{{$placeholder}}}", (string) $value, $content);
        }

        return $content;
    }

    /**
     * Render + send via KirimChatService::sendText().
     */
    public function send(
        KirimChatService $kirimChat,
        string $phoneNumber,
        string $templateKey,
        array $vars = []
    ): array {
        $content = $this->render($templateKey, $vars);

        if ($content === null) {
            $content = 'Maaf, terjadi kesalahan sistem. Ketik *menu* untuk kembali.';
        }

        return $kirimChat->sendText($phoneNumber, $content);
    }

    /**
     * List all available placeholder keys for frontend picker.
     */
    public function availablePlaceholders(): array
    {
        return [
            'customer_name' => 'Nama customer',
            'phone_number' => 'Nomor WA customer',
            'jenis_kelas' => 'Jenis kelas terpilih',
            'harga' => 'Harga formatted (Rp1.000.000)',
            'harga_label' => 'Label harga (/malam atau /hari)',
            'tersedia' => 'Jumlah unit tersedia',
            'fasilitas' => 'Fasilitas kamar',
            'jumlah' => 'Jumlah unit dipesan',
            'tanggal_masuk' => 'Tanggal masuk (Y-m-d)',
            'tanggal_keluar' => 'Tanggal keluar (Y-m-d)',
            'today' => 'Tanggal hari ini (d-m-Y)',
            'nama' => 'Nama pemesan',
            'kode' => 'Kode booking',
            'total' => 'Total harga formatted',
            'detail' => 'Detail reservasi (jenis+tanggal)',
            'nomor_kamar' => 'Nomor kamar (gangguan)',
            'isi_laporan' => 'Isi laporan gangguan',
            'rating' => 'Rating survey (1-5)',
            'bank_label' => 'Nama bank',
            'kode_bayar' => 'Kode bayar e-Retribusi',
            'instruksi_bank' => 'Instruksi transfer per-bank',
            'expired' => 'Tanggal expired billing',
            'link' => 'Link QRIS/pembayaran',
            'status' => 'Status reservasi',
            'payment_status' => 'Status pembayaran',
            'kamar_list' => 'Daftar kamar (dinamis, multi-line)',
        ];
    }
}
