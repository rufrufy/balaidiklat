<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class ChatbotRuleSeeder extends Seeder
{
    /**
     * Template menu chatbot berlapis berbasis rule.
     *
     * Konsep kedalaman:
     * - "state" = posisi user saat ini (kosong = berlaku di semua state).
     * - "keyword" + "match_type" = pemicu (apa yang user ketik / id tombol).
     * - "next_state" = state tujuan setelah balasan dikirim (membuat kedalaman).
     * - "priority" kecil diperiksa lebih dulu; taruh fallback "any" di prioritas besar.
     */
    public function run(): void
    {
        $rules = [
            // Reset global: berlaku di semua state, prioritas paling tinggi.
            [
                'nama' => 'Global - Menu utama',
                'keyword' => 'menu',
                'match_type' => 'contains',
                'state' => null,
                'reply_text' => "Selamat datang di Asrama Balai Diklat BKPP.\n\nSilakan pilih layanan:\n1. Harga kamar\n2. Pesan / booking kamar\n3. Cek status booking\n\nKetik angka pilihan Anda.",
                'next_state' => 'main_menu',
                'priority' => 1,
            ],
            [
                'nama' => 'Global - Sapaan',
                'keyword' => 'halo',
                'match_type' => 'contains',
                'state' => null,
                'reply_text' => "Halo! Ketik *menu* untuk melihat daftar layanan asrama.",
                'next_state' => 'main_menu',
                'priority' => 2,
            ],

            // Level 1: dari main_menu.
            [
                'nama' => 'Main - 1 Harga kamar',
                'keyword' => '1',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => "Daftar harga kamar:\n1. Kamar kecil - Rp250.000/malam\n2. Kamar besar - Rp450.000/malam\n3. Ruang aula - Rp1.500.000/hari\n\nKetik nomor untuk detail, atau 0 untuk kembali.",
                'next_state' => 'harga_kamar',
                'priority' => 10,
            ],
            [
                'nama' => 'Main - 2 Pesan kamar',
                'keyword' => '2',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => "Untuk pemesanan, silakan tulis: nama, tanggal masuk, tanggal keluar, dan jumlah peserta.\nContoh: Budi, 15 Juni, 17 Juni, 10 orang.",
                'next_state' => 'pesan_kamar',
                'priority' => 11,
            ],
            [
                'nama' => 'Main - 3 Cek booking',
                'keyword' => '3',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => "Silakan masukkan kode booking Anda.\nContoh: RSV-20260610-001",
                'next_state' => 'cek_booking',
                'priority' => 12,
            ],

            // Level 2: kedalaman dari harga_kamar.
            [
                'nama' => 'Harga - Kamar kecil',
                'keyword' => '1',
                'match_type' => 'exact',
                'state' => 'harga_kamar',
                'reply_text' => "Kamar kecil - Rp250.000/malam.\nFasilitas: kasur, AC, kamar mandi dalam.\n\nKetik 2 untuk pesan kamar ini, atau 0 untuk kembali.",
                'next_state' => 'harga_kamar',
                'priority' => 20,
            ],
            [
                'nama' => 'Harga - Kamar besar',
                'keyword' => '2',
                'match_type' => 'exact',
                'state' => 'harga_kamar',
                'reply_text' => "Kamar besar - Rp450.000/malam.\nFasilitas: kasur besar, AC, kamar mandi dalam, TV.\n\nKetik 2 untuk pesan kamar ini, atau 0 untuk kembali.",
                'next_state' => 'harga_kamar',
                'priority' => 21,
            ],
            [
                'nama' => 'Harga - Ruang aula',
                'keyword' => '3',
                'match_type' => 'exact',
                'state' => 'harga_kamar',
                'reply_text' => "Ruang aula - Rp1.500.000/hari.\nFasilitas: sound system, kursi, meja, AC.\n\nKetik 2 untuk pesan, atau 0 untuk kembali.",
                'next_state' => 'harga_kamar',
                'priority' => 22,
            ],
            [
                'nama' => 'Harga - Kembali',
                'keyword' => '0',
                'match_type' => 'exact',
                'state' => 'harga_kamar',
                'reply_text' => "Kembali ke menu utama.\n1. Harga kamar\n2. Pesan / booking kamar\n3. Cek status booking",
                'next_state' => 'main_menu',
                'priority' => 23,
            ],

            // Level 2: dari pesan_kamar - fallback "any" menampung detail bebas.
            [
                'nama' => 'Pesan - Terima detail',
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'pesan_kamar',
                'reply_text' => "Terima kasih, permintaan booking Anda sudah kami terima dan akan diproses admin.\n\nKetik *menu* untuk kembali ke menu utama.",
                'next_state' => 'main_menu',
                'priority' => 30,
            ],

            // Level 2: dari cek_booking - fallback "any" menerima kode booking.
            [
                'nama' => 'Cek booking - Terima kode',
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'cek_booking',
                'reply_text' => "Kami sedang memeriksa kode booking Anda. Admin akan mengonfirmasi status melalui chat ini.\n\nKetik *menu* untuk kembali.",
                'next_state' => 'main_menu',
                'priority' => 31,
            ],
        ];

        foreach ($rules as $rule) {
            ChatbotRule::updateOrCreate(
                ['nama' => $rule['nama']],
                array_merge($rule, ['is_active' => true])
            );
        }
    }
}
