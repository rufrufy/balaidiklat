<?php

namespace App\Http\Controllers;

use App\Models\ChatbotRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminChatbotRuleController extends Controller
{
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

    private function validatedData(Request $request): array
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'keyword' => ['nullable', 'string', 'max:255'],
            'match_type' => ['required', Rule::in(['contains', 'exact', 'starts_with', 'any'])],
            'state' => ['nullable', 'string', 'max:100'],
            'reply_text' => ['nullable', 'string'],
            'action' => ['nullable', Rule::in(['main_menu', 'check_availability', 'list_kamar', 'pilih_kamar', 'simpan_reservasi', 'bayar_pilihan', 'bayar_qris', 'bayar_transfer', 'simpan_laporan', 'simpan_saran', 'cek_booking', 'selesai'])],
            'next_state' => ['nullable', 'string', 'max:100'],
            'priority' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['keyword'] = $data['keyword'] ?? '';

        return $data;
    }
}
