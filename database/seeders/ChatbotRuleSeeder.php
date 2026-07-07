<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class ChatbotRuleSeeder extends Seeder
{
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
            .'6. Customer Care';

        $rules = [
            ['nama' => 'Global - Menu utama', 'keyword' => 'menu', 'match_type' => 'contains', 'state' => null, 'reply_text' => $menuUtama, 'action' => 'main_menu', 'next_state' => 'main_menu', 'priority' => 1, 'menu_label' => null, 'menu_description' => null, 'menu_order' => 0],
            ['nama' => 'Global - Sapaan', 'keyword' => 'halo', 'match_type' => 'contains', 'state' => null, 'reply_text' => $menuUtama, 'action' => 'main_menu', 'next_state' => 'main_menu', 'priority' => 2, 'menu_label' => null, 'menu_description' => null, 'menu_order' => 0],

            ['nama' => 'Main - 1 Info & Pesan', 'keyword' => '1', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => null, 'action' => 'list_kamar', 'next_state' => 'pilih_jenis', 'priority' => 10, 'menu_label' => 'Info Layanan & Pesan', 'menu_description' => 'Lihat info layanan dan pesan kamar/kelas', 'menu_order' => 1],
            ['nama' => 'Main - 3 Laporan gangguan', 'keyword' => '3', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => "Laporan Gangguan.\nSilakan tuliskan gangguan yang Anda alami, tim kami akan menindaklanjuti.", 'action' => null, 'next_state' => 'laporan_gangguan', 'priority' => 12, 'menu_label' => 'Laporan Gangguan', 'menu_description' => 'Laporkan gangguan fasilitas', 'menu_order' => 2],
            ['nama' => 'Main - 4 Saran', 'keyword' => '4', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => "Saran.\nSilakan tuliskan saran Anda untuk pelayanan Balai Diklat.", 'action' => null, 'next_state' => 'saran', 'priority' => 13, 'menu_label' => 'Saran', 'menu_description' => 'Kirim saran dan masukan', 'menu_order' => 3],
            ['nama' => 'Main - 5 Survey kepuasan', 'keyword' => '5', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => "Survey Kepuasan.\nSeberapa puas Anda dengan layanan kami? Balas dengan angka 1 (kurang) sampai 5 (sangat puas).", 'action' => null, 'next_state' => 'survey', 'priority' => 14, 'menu_label' => 'Survey Kepuasan', 'menu_description' => 'Isi survey kepuasan layanan', 'menu_order' => 4],
            ['nama' => 'Main - 6 Customer care', 'keyword' => '6', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => "Customer Care.\nMasukkan kode booking Anda untuk melihat detail pemesanan.\nContoh: BKPP-20260611120000-123", 'action' => null, 'next_state' => 'customer_care', 'priority' => 15, 'menu_label' => 'Customer Care', 'menu_description' => 'Hubungi tim layanan pelanggan', 'menu_order' => 5],

            ['nama' => 'Pilih jenis - Pilih', 'keyword' => '', 'match_type' => 'any', 'state' => 'pilih_jenis', 'reply_text' => null, 'action' => 'pilih_jenis', 'next_state' => null, 'priority' => 20],

            ['nama' => 'Pesan - Step Jumlah', 'keyword' => '', 'match_type' => 'any', 'state' => 'pesan_jumlah', 'reply_text' => null, 'action' => 'input_jumlah', 'next_state' => null, 'priority' => 30],
            ['nama' => 'Pesan - Step Tanggal masuk', 'keyword' => '', 'match_type' => 'any', 'state' => 'pesan_tanggal_masuk', 'reply_text' => null, 'action' => 'input_tanggal_masuk', 'next_state' => null, 'priority' => 31],
            ['nama' => 'Pesan - Step Tanggal keluar', 'keyword' => '', 'match_type' => 'any', 'state' => 'pesan_tanggal_keluar', 'reply_text' => null, 'action' => 'input_tanggal_keluar', 'next_state' => null, 'priority' => 32],
            ['nama' => 'Pesan - Step Nama', 'keyword' => '', 'match_type' => 'any', 'state' => 'pesan_nama', 'reply_text' => null, 'action' => 'input_nama', 'next_state' => null, 'priority' => 33],
            ['nama' => 'Pesan - Step No HP', 'keyword' => '', 'match_type' => 'any', 'state' => 'pesan_no_hp', 'reply_text' => null, 'action' => 'input_no_hp', 'next_state' => null, 'priority' => 34],

            ['nama' => 'Bayar - Pilihan', 'keyword' => 'bayar', 'match_type' => 'contains', 'state' => 'pesan_pembayaran', 'reply_text' => null, 'action' => 'bayar_pilihan', 'next_state' => 'pesan_metode_bayar', 'priority' => 40],
            ['nama' => 'Bayar - QRIS', 'keyword' => 'qris', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_qris', 'next_state' => null, 'priority' => 41],
            ['nama' => 'Bayar - Bank Jateng', 'keyword' => 'jateng', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_transfer', 'next_state' => null, 'priority' => 42],
            ['nama' => 'Bayar - Bank BRI', 'keyword' => 'bri', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_transfer', 'next_state' => null, 'priority' => 43],
            ['nama' => 'Bayar - Bank Mandiri', 'keyword' => 'mandiri', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_transfer', 'next_state' => null, 'priority' => 44],
            ['nama' => 'Bayar - Bank BNI', 'keyword' => 'bni', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_transfer', 'next_state' => null, 'priority' => 45],
            ['nama' => 'Bayar - Bank BCA', 'keyword' => 'bca', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_transfer', 'next_state' => null, 'priority' => 46],
            ['nama' => 'Bayar - Transfer Legacy', 'keyword' => 'transfer', 'match_type' => 'contains', 'state' => 'pesan_metode_bayar', 'reply_text' => null, 'action' => 'bayar_transfer', 'next_state' => null, 'priority' => 47],
            ['nama' => 'Bayar - Reminder upload bukti', 'keyword' => '', 'match_type' => 'any', 'state' => 'pesan_upload_bukti', 'reply_text' => 'Silakan kirim *foto bukti pembayaran* (maksimal 2MB) langsung ke chat ini. Setelah bukti diterima, sistem akan cek status pembayaran ke e-Retribusi Bapenda secara otomatis. Ketik *menu* untuk kembali.', 'action' => null, 'next_state' => 'pesan_upload_bukti', 'priority' => 48],

            ['nama' => 'Gangguan - Simpan', 'keyword' => '', 'match_type' => 'any', 'state' => 'laporan_gangguan', 'reply_text' => null, 'action' => 'simpan_laporan', 'next_state' => null, 'priority' => 60],
            ['nama' => 'Saran - Simpan', 'keyword' => '', 'match_type' => 'any', 'state' => 'saran', 'reply_text' => null, 'action' => 'simpan_saran', 'next_state' => null, 'priority' => 61],
            ['nama' => 'Survey - Input rating', 'keyword' => '', 'match_type' => 'any', 'state' => 'survey', 'reply_text' => null, 'action' => 'input_rating_survey', 'next_state' => null, 'priority' => 62],
            ['nama' => 'Survey - Simpan', 'keyword' => '', 'match_type' => 'any', 'state' => 'survey_komentar', 'reply_text' => null, 'action' => 'simpan_survey', 'next_state' => null, 'priority' => 63],
            ['nama' => 'Customer care - Cek booking', 'keyword' => '', 'match_type' => 'any', 'state' => 'customer_care', 'reply_text' => null, 'action' => 'cek_booking', 'next_state' => 'main_menu', 'priority' => 64],
        ];

        ChatbotRule::query()->delete();

        foreach ($rules as $rule) {
            ChatbotRule::create(array_merge($rule, ['is_active' => true]));
        }
    }
}
