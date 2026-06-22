<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class AddMenuAliasKeywordsSeeder extends Seeder
{
    public function run(): void
    {
        $menuUtama = null;

        $aliases = [
            ['nama' => 'Main - 2 (alias ke menu gabungan)', 'keyword' => '2', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => null, 'action' => 'list_kamar', 'next_state' => 'pilih_kamar', 'priority' => 11],
            ['nama' => 'Main - Informasi layanan (alias judul)', 'keyword' => 'informasi layanan', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => null, 'action' => 'list_kamar', 'next_state' => 'pilih_kamar', 'priority' => 16],
            ['nama' => 'Main - Pemesanan kamar (alias judul)', 'keyword' => 'pemesanan kamar', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => null, 'action' => 'list_kamar', 'next_state' => 'pilih_kamar', 'priority' => 17],
            ['nama' => 'Main - Pemesanan (alias judul)', 'keyword' => 'pemesanan', 'match_type' => 'exact', 'state' => 'main_menu', 'reply_text' => null, 'action' => 'list_kamar', 'next_state' => 'pilih_kamar', 'priority' => 18],
        ];

        foreach ($aliases as $rule) {
            ChatbotRule::firstOrCreate(
                ['nama' => $rule['nama']],
                array_merge($rule, ['is_active' => true])
            );
        }

        $this->command->info('Chatbot rules: added alias keywords (2, informasi layanan, pemesanan kamar, pemesanan) -> menu gabungan.');
    }
}
