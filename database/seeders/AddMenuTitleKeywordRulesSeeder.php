<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class AddMenuTitleKeywordRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'nama' => 'Main - Informasi layanan (judul)',
                'keyword' => 'informasi layanan',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => null,
                'action' => 'list_kamar',
                'next_state' => 'pilih_kamar',
                'priority' => 16,
            ],
            [
                'nama' => 'Main - Pemesanan kamar (judul)',
                'keyword' => 'pemesanan kamar',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => null,
                'action' => 'list_kamar',
                'next_state' => 'pilih_kamar',
                'priority' => 17,
            ],
            [
                'nama' => 'Main - Laporan gangguan (judul)',
                'keyword' => 'laporan gangguan',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => "Laporan Gangguan.\nMohon kirim nomor kamar yang mengalami gangguan.\nContoh: A-101",
                'action' => null,
                'next_state' => 'gangguan_nomor_kamar',
                'priority' => 18,
            ],
            [
                'nama' => 'Main - Saran (judul)',
                'keyword' => 'saran',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => "Saran.\nSilakan tuliskan saran Anda untuk pelayanan Balai Diklat.",
                'action' => null,
                'next_state' => 'saran',
                'priority' => 19,
            ],
            [
                'nama' => 'Main - Survey kepuasan (judul)',
                'keyword' => 'survey kepuasan',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => "Survey Kepuasan Layanan.\n\nMohon berikan rating antara 1 sampai 5:\n\n1 ⭐ Sangat Tidak Puas\n2 ⭐⭐ Tidak Puas\n3 ⭐⭐⭐ Cukup\n4 ⭐⭐⭐⭐ Puas\n5 ⭐⭐⭐⭐⭐ Sangat Puas",
                'action' => null,
                'next_state' => 'survey_rating',
                'priority' => 20,
            ],
            [
                'nama' => 'Main - Customer care (judul)',
                'keyword' => 'customer care',
                'match_type' => 'exact',
                'state' => 'main_menu',
                'reply_text' => "Customer Care.\nMasukkan kode booking Anda untuk melihat detail pemesanan.\nContoh: RSV-20260611120000-123",
                'action' => null,
                'next_state' => 'customer_care',
                'priority' => 21,
            ],
        ];

        foreach ($rules as $rule) {
            ChatbotRule::firstOrCreate(
                ['nama' => $rule['nama']],
                array_merge($rule, ['is_active' => true])
            );
        }

        $this->command->info('Chatbot rules: added keyword judul menu (fallback ketik manual / list_reply title).');
    }
}
