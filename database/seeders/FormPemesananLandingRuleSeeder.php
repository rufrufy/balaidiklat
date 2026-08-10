<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class FormPemesananLandingRuleSeeder extends Seeder
{
    public function run(): void
    {
        ChatbotRule::updateOrCreate(
            ['nama' => 'Landing - Form Pemesanan'],
            [
                'keyword' => 'form_pemesanan_landing',
                'match_type' => 'starts_with',
                'state' => null,
                'reply_text' => null,
                'action' => 'form_pemesanan_landing',
                'next_state' => null,
                'priority' => 5,
                'is_active' => true,
            ]
        );

        ChatbotRule::updateOrCreate(
            ['nama' => 'Landing - Konfirmasi Pesan Sekarang'],
            [
                'keyword' => 'pesan_sekarang',
                'match_type' => 'exact',
                'state' => 'pesan_konfirmasi_landing',
                'reply_text' => null,
                'action' => 'konfirmasi_pesan_landing',
                'next_state' => null,
                'priority' => 6,
                'is_active' => true,
            ]
        );
    }
}
