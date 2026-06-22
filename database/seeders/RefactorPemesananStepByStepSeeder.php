<?php

namespace Database\Seeders;

use App\Models\ChatbotRule;
use Illuminate\Database\Seeder;

class RefactorPemesananStepByStepSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Disable rule lama untuk menu 1 & 2 (angka "2" + judul "pemesanan kamar")
        //    karena menu 1 & 2 sudah digabung jadi satu menu "Informasi Layanan & Pemesanan".
        ChatbotRule::whereIn('id', [70, 88])->update(['is_active' => false]);

        // 2. Update rule 69 (keyword "1") -> tetap aktif, next_state pilih_kamar (sudah benar).
        //    Pastikan tidak ada reply_text (sendKamarList menangani tampilan).
        ChatbotRule::find(69)?->update([
            'reply_text' => null,
            'action' => 'list_kamar',
            'next_state' => 'pilih_kamar',
        ]);

        // 3. Update rule 87 (judul) -> keyword baru "informasi layanan & pemesanan"
        ChatbotRule::find(87)?->update([
            'nama' => 'Main - Informasi layanan & pemesanan (judul)',
            'keyword' => 'informasi layanan & pemesanan',
            'match_type' => 'exact',
            'state' => 'main_menu',
            'reply_text' => null,
            'action' => 'list_kamar',
            'next_state' => 'pilih_kamar',
        ]);

        // 4. Disable rule 76 (pesan_isi_data lama, simpan_reservasi dari form multiline)
        //    karena flow sekarang step-by-step via state pesan_no_hp -> input_no_hp.
        ChatbotRule::find(76)?->update(['is_active' => false]);

        // 5. Tambah rule step-by-step pemesanan
        $stepRules = [
            [
                'nama' => 'Pesan - Step 1 Nama',
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'pesan_nama',
                'reply_text' => null,
                'action' => 'input_nama',
                'next_state' => null,
                'priority' => 30,
            ],
            [
                'nama' => 'Pesan - Step 2 Tanggal masuk',
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'pesan_tanggal_masuk',
                'reply_text' => null,
                'action' => 'input_tanggal_masuk',
                'next_state' => null,
                'priority' => 31,
            ],
            [
                'nama' => 'Pesan - Step 3 Tanggal keluar',
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'pesan_tanggal_keluar',
                'reply_text' => null,
                'action' => 'input_tanggal_keluar',
                'next_state' => null,
                'priority' => 32,
            ],
            [
                'nama' => 'Pesan - Step 4 No HP',
                'keyword' => '',
                'match_type' => 'any',
                'state' => 'pesan_no_hp',
                'reply_text' => null,
                'action' => 'input_no_hp',
                'next_state' => null,
                'priority' => 33,
            ],
        ];

        foreach ($stepRules as $rule) {
            ChatbotRule::firstOrCreate(
                ['nama' => $rule['nama']],
                array_merge($rule, ['is_active' => true])
            );
        }

        $this->command->info('Chatbot rules: menu digabung, flow pemesanan step-by-step (nama -> tgl masuk -> tgl keluar -> no hp).');
    }
}
