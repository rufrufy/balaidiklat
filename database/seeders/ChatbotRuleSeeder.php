<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class ChatbotRuleSeeder extends Seeder
{
    /**
     * Rule SAPA BALAI, sepenuhnya rule-based, mengikuti flowchart resmi.
     *
     * Konsep:
     * - "state" = posisi user (kosong = berlaku di semua state).
     * - "keyword" + "match_type" = pemicu (angka, teks, atau id tombol).
     * - "next_state" = state tujuan setelah balasan (membentuk kedalaman).
     * - "action" = perilaku khusus: main_menu / check_availability.
     * - "priority" kecil diperiksa lebih dulu; fallback "any" di prioritas besar.
     *
     * Alur flowchart:
     * main_menu (1-6)
     *   1/2 -> jenis_sewa (pilih 1-5 jenis sewa: kamar, kelas, gedung, transit, lapangan)
     *           -> tampil tarif+fasilitas -> pesan_cek_tanggal
     *              -> check_availability: tersedia -> pesan_isi_data, tidak -> tetap
     *                 -> isi data -> pesan_tagihan (RINCIAN TAGIHAN/SSRD)
     *                    -> pilih pembayaran -> pesan_bayar (QRIS / Transfer)
     *                       -> konfirmasi -> main_menu
     *   3 Laporan Gangguan / 4 Saran / 5 Survey / 6 Customer Care -> layanan lain -> main_menu
     */
    public function run(): void
    {
        $menuUtama = "Halo, {{customer_name}} Selamat Datang di SAPA BALAI \u{1F44B}.\n"
            ."Smart Chatbot Layanan Balai Diklat Kota Semarang.\n\n"
            ."Silakan pilih menu layanan dengan mengetik angka 1 sampai 6.\n\n"
            ."1. Informasi layanan balai diklat\n"
            ."2. Pemesanan kamar/kelas\n"
            ."3. Laporan Gangguan\n"
            ."4. Saran\n"
            ."5. Survey kepuasan\n"
            ."6. Customer Care";

        $rules = [
            // ---- Reset global ----
            ['nama' => 'Global - Menu utama', 'keyword' => 'menu', 'match_type' => 'contains', 'state' => null, 'reply_text' => $menuUtama, 'action' => 'main_menu', 'next_state' => 'main_menu', 'priority' => 1],
            ['nama' => 'Global - Sapaan', 'keyword' => 'halo', 'match_type' => 'contains', 'state' => null, 'reply_text' => $menuUtama, 'action' => 'main_menu', 'next_state' => 'main_menu', 'priority' => 2],

            // ---- Level 1: MENU UTAMA (1-6) ----
            // 1 Informasi layanan -> tampilkan daftar kamar/kelas dari DB (action list_kamar).
            ['nama' => 'Main - 1 Informasi layanan', 'keyword' => '1', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => null, 'action' => 'list_kamar', 'next_state' => 'pilih_kamar', 'priority' => 10],
            // 2 Pemesanan kamar/kelas -> juga tampilkan daftar kamar/kelas dari DB.
            ['nama' => 'Main - 2 Pemesanan kamar/kelas', 'keyword' => '2', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => null, 'action' => 'list_kamar', 'next_state' => 'pilih_kamar', 'priority' => 11],
            ['nama' => 'Main - 3 Laporan gangguan', 'keyword' => '3', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => "Laporan Gangguan.\nSilakan tuliskan gangguan yang Anda alami, tim kami akan menindaklanjuti.", 'action' => null, 'next_state' => 'laporan_gangguan', 'priority' => 12],
            ['nama' => 'Main - 4 Saran', 'keyword' => '4', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => "Saran.\nSilakan tuliskan saran Anda untuk pelayanan Balai Diklat.", 'action' => null, 'next_state' => 'saran', 'priority' => 13],
            ['nama' => 'Main - 5 Survey kepuasan', 'keyword' => '5', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => "Survey Kepuasan.\nSeberapa puas Anda dengan layanan kami? Balas dengan angka 1 (kurang) sampai 5 (sangat puas).", 'action' => null, 'next_state' => 'survey', 'priority' => 14],
            ['nama' => 'Main - 6 Customer care', 'keyword' => '6', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => "Customer Care.\nMasukkan kode booking Anda untuk melihat detail pemesanan.\nContoh: RSV-20260611120000-123", 'action' => null, 'next_state' => 'customer_care', 'priority' => 15],

            // ---- Level 2: PILIH KAMAR (nomor) -> detail fasilitas dari DB + minta data pesan ----
            ['nama' => 'Pilih kamar - Detail', 'keyword' => '', 'match_type' => 'any', 'state' => 'pilih_kamar', 'reply_text' => null, 'action' => 'pilih_kamar', 'next_state' => null, 'priority' => 20],

            // ---- Level 3: ISI DATA PEMESANAN -> simpan ke DB + balasan interactive (Menu Utama + Bayar) ----
            ['nama' => 'Pesan - Simpan reservasi', 'keyword' => '', 'match_type' => 'any', 'state' => 'pesan_isi_data', 'reply_text' => null, 'action' => 'simpan_reservasi', 'next_state' => null, 'priority' => 30],

            // ---- Level 4: PEMBAYARAN ----
            // Tombol "Bayar" (id=bayar) atau ketik bayar -> tampilkan pilihan QRIS/Transfer.
            ['nama' => 'Bayar - Pilihan', 'keyword' => 'bayar', 'match_type' => 'contains', 'state' => 'pesan_pembayaran', 'reply_text' => null, 'action' => 'bayar_pilihan', 'next_state' => 'pesan_metode_bayar', 'priority' => 40],
            // Pilih QRIS (tombol id=qris atau ketik qris).
            ['nama' => 'Bayar - QRIS', 'keyword' => 'qris', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_qris', 'next_state' => 'pesan_upload_bukti', 'priority' => 41],
            // Pilih Transfer Bank (tombol id=transfer atau ketik transfer).
            ['nama' => 'Bayar - Transfer', 'keyword' => 'transfer', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_transfer', 'next_state' => 'pesan_upload_bukti', 'priority' => 42],
            // Upload bukti: jika user kirim teks (bukan gambar), ingatkan kirim foto. Gambar ditangani controller di luar rule.
            ['nama' => 'Bayar - Reminder upload bukti', 'keyword' => '', 'match_type' => 'any', 'state' => 'pesan_upload_bukti', 'reply_text' => "Silakan kirim *foto bukti pembayaran* (maksimal 2MB) langsung ke chat ini, atau ketik *menu* untuk kembali.", 'action' => null, 'next_state' => 'pesan_upload_bukti', 'priority' => 43],

            // ---- LAYANAN LAIN: simpan ke DB + tombol Menu Utama ----
            ['nama' => 'Gangguan - Simpan', 'keyword' => '', 'match_type' => 'any', 'state' => 'laporan_gangguan', 'reply_text' => null, 'action' => 'simpan_laporan', 'next_state' => null, 'priority' => 60],
            ['nama' => 'Saran - Simpan', 'keyword' => '', 'match_type' => 'any', 'state' => 'saran', 'reply_text' => null, 'action' => 'simpan_saran', 'next_state' => null, 'priority' => 61],
            ['nama' => 'Survey - Terima', 'keyword' => '', 'match_type' => 'any', 'state' => 'survey', 'reply_text' => "Terima kasih sudah mengisi survey kepuasan kami.", 'action' => 'selesai', 'next_state' => 'main_menu', 'priority' => 62],
            ['nama' => 'Customer care - Cek booking', 'keyword' => '', 'match_type' => 'any', 'state' => 'customer_care', 'reply_text' => null, 'action' => 'cek_booking', 'next_state' => 'main_menu', 'priority' => 63],
        ];

        // Buat aturan baru, hapus yang lama dulu agar bersih.
        ChatbotRule::query()->delete();

        foreach ($rules as $rule) {
            ChatbotRule::create(array_merge($rule, ['is_active' => true]));
        }
    }
}
