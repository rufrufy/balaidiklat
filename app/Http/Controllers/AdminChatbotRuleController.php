<?php

namespace App\Http\Controllers;

use App\Models\ChatbotRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminChatbotRuleController extends Controller
{
    /**
     * Single source of truth for chatbot action options.
     * Label bersifat dinamis, value action wajib sync dengan ChatbotService.
     */
    public static function actionOptions(): array
    {
        return [
            '' => 'Balasan teks biasa',
            'main_menu' => 'Kirim menu utama',
            'check_availability' => 'Cek ketersediaan tanggal',
            'list_kamar' => 'Tampilkan daftar kamar (DB)',
            'pilih_jenis' => 'Pilih jenis kamar (DB)',
            'input_jumlah' => 'Input jumlah unit kamar',
            'input_jumlah_hari' => 'Input jumlah hari (non-kamar)',
            'input_tanggal_masuk' => 'Input tanggal masuk',
            'input_tanggal_keluar' => 'Input tanggal keluar',
            'input_nama' => 'Input nama pemesan',
            'input_no_hp' => 'Input nomor HP',
            'simpan_reservasi' => 'Simpan reservasi (DB)',
            'bayar_pilihan' => 'Tampilkan pilihan bayar',
            'bayar_qris' => 'Kirim QRIS e-Retribusi',
            'bayar_transfer' => 'Kirim info transfer bank',
            'cek_status' => 'Cek status pembayaran/reservasi',
            'input_nomor_kamar_gangguan' => 'Input nomor kamar (gangguan)',
            'simpan_laporan' => 'Simpan laporan gangguan (DB)',
            'simpan_saran' => 'Simpan saran (DB)',
            'input_rating_survey' => 'Input rating survey (1-5)',
            'simpan_survey' => 'Simpan survey kepuasan (DB)',
            'cek_booking' => 'Cek kode booking (DB)',
            'form_pemesanan_landing' => 'Form pemesanan di landing page',
            'konfirmasi_pesan_landing' => 'Konfirmasi pesan dari landing',
            'kembali_menu' => 'Kembali ke menu utama',
            'selesai' => 'Balasan + tombol Menu Utama',
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        ChatbotRule::create($this->validatedData($request));

        return redirect()->route('admin.dashboard', ['section' => 'rules'])->with('status', 'Aturan balasan berhasil ditambahkan.');
    }

    public function update(Request $request, ChatbotRule $rule): RedirectResponse
    {
        $rule->update($this->validatedData($request));

        return redirect()->route('admin.dashboard', ['section' => 'rules'])->with('status', 'Aturan balasan berhasil diperbarui.');
    }

    public function destroy(ChatbotRule $rule): RedirectResponse
    {
        $rule->delete();

        return redirect()->route('admin.dashboard', ['section' => 'rules'])->with('status', 'Aturan balasan berhasil dihapus.');
    }

    public function toggle(ChatbotRule $rule): RedirectResponse
    {
        $rule->update(['is_active' => ! $rule->is_active]);

        return redirect()->route('admin.dashboard', ['section' => 'rules'])->with('status', 'Status aturan berhasil diubah.');
    }

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'match_type' => ['required', Rule::in(['contains', 'exact', 'starts_with', 'any'])],
            'state' => ['nullable', 'string', 'max:100'],
            'reply_text' => ['nullable', 'string'],
            'action' => ['nullable', Rule::in(array_keys(self::actionOptions()))],
            'next_state' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'menu_label' => ['nullable', 'string', 'max:255'],
            'menu_description' => ['nullable', 'string', 'max:255'],
            'menu_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['keyword'] = $data['keyword'] ?? '';

        return $data;
    }
}
