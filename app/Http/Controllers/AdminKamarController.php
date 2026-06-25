<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AdminKamarController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'jenis_kelas' => ['required', 'string', 'max:255', 'unique:kamars,jenis_kelas'],
            'kuota_total' => ['required', 'integer', 'min:0'],
        ]);

        Kamar::create($data);

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Jenis kelas berhasil ditambahkan.');
    }

    public function update(Request $request, Kamar $kamar): RedirectResponse
    {
        $data = $request->validate([
            'jenis_kelas' => ['required', 'string', 'max:255', 'unique:kamars,jenis_kelas,'.$kamar->id],
            'kuota_total' => ['required', 'integer', 'min:0'],
        ]);

        $kamar->update($data);

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Jenis kelas berhasil diperbarui.');
    }

    public function destroy(Kamar $kamar): RedirectResponse
    {
        $kamar->delete();

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Jenis kelas berhasil dihapus.');
    }

    public function duplicate(Request $request, Kamar $kamar): RedirectResponse
    {
        $data = $request->validate([
            'jenis_kelas' => ['required', 'string', 'max:255', 'unique:kamars,jenis_kelas'],
        ]);

        Kamar::create([
            'jenis_kelas' => $data['jenis_kelas'],
            'kuota_total' => $kamar->kuota_total,
        ]);

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Jenis kelas berhasil diduplikat.');
    }
}
