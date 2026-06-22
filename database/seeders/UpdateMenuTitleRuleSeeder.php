<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class UpdateMenuTitleRuleSeeder extends Seeder
{
    public function run(): void
    {
        ChatbotRule::where('nama', 'Main - Informasi layanan & pemesanan (judul)')
            ->update([
                'nama' => 'Main - Info layanan & pesan (judul)',
                'keyword' => 'info layanan & pesan',
            ]);

        $this->command->info('Chatbot rules: updated judul rule to match new short title "Info Layanan & Pesan".');
    }
}
