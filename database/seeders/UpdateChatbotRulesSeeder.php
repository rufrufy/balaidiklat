<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class UpdateChatbotRulesSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Clear reply_text from main_menu rules (67, 68) — sendList handles it now
        ChatbotRule::whereIn('id', [67, 68])->update(['reply_text' => null]);

        // 2. Update rule 71 (menu 3 Gangguan) → ask room number first
        ChatbotRule::find(71)?->update([
            'next_state' => 'gangguan_nomor_kamar',
            'action' => null,
            'reply_text' => "Laporan Gangguan.\nMohon kirim nomor kamar yang mengalami gangguan.\nContoh: A-101",
        ]);

        // 3. Create rule: gangguan_nomor_kamar → input nomor kamar
        ChatbotRule::firstOrCreate(
            ['nama' => 'Gangguan - Input nomor kamar'],
            [
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'gangguan_nomor_kamar',
                'next_state' => null,
                'action' => 'input_nomor_kamar_gangguan',
                'reply_text' => null,
                'priority' => 59,
                'is_active' => true,
            ]
        );

        // 4. Update rule 81 (gangguan simpan) → state gangguan_isi
        ChatbotRule::find(81)?->update(['state' => 'gangguan_isi']);

        // 5. Update rule 73 (menu 5 Survey) → ask rating first
        ChatbotRule::find(73)?->update([
            'next_state' => 'survey_rating',
            'action' => null,
            'reply_text' => "Survey Kepuasan Layanan.\n\nMohon berikan rating antara 1 sampai 5:\n\n1 ⭐ Sangat Tidak Puas\n2 ⭐⭐ Tidak Puas\n3 ⭐⭐⭐ Cukup\n4 ⭐⭐⭐⭐ Puas\n5 ⭐⭐⭐⭐⭐ Sangat Puas",
        ]);

        // 6. Update rule 83 (survey terima) → handle rating input
        ChatbotRule::find(83)?->update([
            'nama' => 'Survey - Input rating',
            'state' => 'survey_rating',
            'next_state' => null,
            'action' => 'input_rating_survey',
            'reply_text' => null,
        ]);

        // 7. Create rule: survey_komentar → simpan_survey
        ChatbotRule::firstOrCreate(
            ['nama' => 'Survey - Simpan komentar'],
            [
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'survey_komentar',
                'next_state' => null,
                'action' => 'simpan_survey',
                'reply_text' => null,
                'priority' => 63,
                'is_active' => true,
            ]
        );

        $this->command->info('Chatbot rules updated for interactive menu, survey flow, and gangguan flow.');
    }
}
