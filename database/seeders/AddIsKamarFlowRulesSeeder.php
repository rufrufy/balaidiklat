<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class AddIsKamarFlowRulesSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            // Non-kamar: pesan_jumlah_hari → input_jumlah_hari (jumlah hari)
            [
                'nama' => 'NonKamar - Input jumlah hari',
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'pesan_jumlah_hari',
                'reply_text' => null,
                'action' => 'input_jumlah_hari',
                'next_state' => 'pesan_tanggal_masuk',
                'priority' => 41,
                'is_active' => true,
            ],
            // pesan_tanggal_masuk (shared by both flows) - already exists, no need to add
            // pesan_nama (shared) - already exists
            // pesan_no_hp (shared) - already exists
        ];

        foreach ($rules as $rule) {
            ChatbotRule::firstOrCreate(
                ['nama' => $rule['nama']],
                $rule
            );
        }

        $this->command->info('Chatbot rules: added is_kamar conditional flow (non-kamar: jumlah_hari).');
    }
}
