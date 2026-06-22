<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class AddSapaanKeywordsSeeder extends Seeder
{
    public function run(): void
    {
        $sapaan = [
            ['nama' => 'Global - Hai', 'keyword' => 'hai'],
            ['nama' => 'Global - Hi', 'keyword' => 'hi'],
            ['nama' => 'Global - Assalamualaikum', 'keyword' => 'assalamualaikum'],
            ['nama' => 'Global - Pagi', 'keyword' => 'pagi'],
            ['nama' => 'Global - Siang', 'keyword' => 'siang'],
            ['nama' => 'Global - Sore', 'keyword' => 'sore'],
            ['nama' => 'Global - Malam', 'keyword' => 'malam'],
            ['nama' => 'Global - Selamat', 'keyword' => 'selamat'],
            ['nama' => 'Global - Halo2', 'keyword' => 'halo'],
            ['nama' => 'Global - Hello', 'keyword' => 'hello'],
            ['nama' => 'Global - Mulai', 'keyword' => 'mulai'],
            ['nama' => 'Global - Start', 'keyword' => 'start'],
        ];

        foreach ($sapaan as $s) {
            ChatbotRule::firstOrCreate(
                ['nama' => $s['nama']],
                [
                    'keyword' => $s['keyword'],
                    'match_type' => 'contains',
                    'state' => null,
                    'reply_text' => null,
                    'action' => 'main_menu',
                    'next_state' => 'main_menu',
                    'priority' => 2,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Chatbot rules: added sapaan keywords (hai, hi, halo, hello, assalamualaikum, pagi, siang, sore, malam, selamat, mulai, start).');
    }
}
