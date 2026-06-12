<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminKamarController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:kamars,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:kamar,ruang_kelas'],
            'harga_per_malam' => ['required', 'integer', 'min:0'],
            'fasilitas' => ['nullable', 'string'],
            'status' => ['required', 'in:available,limited,full,maintenance'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ], [
            'foto.max' => 'Ukuran foto maksimal 2MB.',
            'foto.image' => 'File harus berupa gambar (jpg, png, dll).',
        ]);

        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            $file = $request->file('foto');
            $data['foto_path'] = $file->storeAs(
                'kamar',
                Str::random(30).'.'.($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'),
                'public'
            );
        }

        Kamar::create($data);

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Kamar berhasil ditambahkan.');
    }

    public function update(Request $request, Kamar $kamar): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:kamars,kode,'.$kamar->id],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:kamar,ruang_kelas'],
            'harga_per_malam' => ['required', 'integer', 'min:0'],
            'fasilitas' => ['nullable', 'string'],
            'status' => ['required', 'in:available,limited,full,maintenance'],
            'foto' => ['nullable', 'image', 'max:2048'],
        ], [
            'foto.max' => 'Ukuran foto maksimal 2MB.',
            'foto.image' => 'File harus berupa gambar (jpg, png, dll).',
        ]);

        if ($request->hasFile('foto') && $request->file('foto')->isValid()) {
            if ($kamar->foto_path) {
                Storage::disk('public')->delete($kamar->foto_path);
            }

            $file = $request->file('foto');
            $data['foto_path'] = $file->storeAs(
                'kamar',
                Str::random(30).'.'.($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg'),
                'public'
            );
        }

        $kamar->update($data);

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Kamar berhasil diperbarui.');
    }

    public function destroy(Kamar $kamar): RedirectResponse
    {
        if ($kamar->foto_path) {
            Storage::disk('public')->delete($kamar->foto_path);
        }

        $kamar->delete();

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Kamar berhasil dihapus.');
    }

    public function duplicate(Request $request, Kamar $kamar): RedirectResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:50', 'unique:kamars,kode'],
        ]);

        $newKamar = $kamar->replicate(['kode']);
        $newKamar->kode = $data['kode'];
        $newKamar->nama = $kamar->nama.' Copy';
        $newKamar->save();

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Kamar berhasil diduplikat.');
    }
}
