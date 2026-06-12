<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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
            $data['foto_path'] = $this->storeFoto($request->file('foto'));
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

            $data['foto_path'] = $this->storeFoto($request->file('foto'));
        }

        $kamar->update($data);

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Kamar berhasil diperbarui.');
    }

    /**
     * Store an uploaded photo WITHOUT relying on UploadedFile::getRealPath(),
     * which returns false on some hosting setups (restricted open_basedir /
     * temp realpath quirks) and makes Storage::store() throw
     * "Path cannot be empty". We read the raw temp bytes via getPathname()
     * and write them with the filesystem driver, which works everywhere.
     */
    private function storeFoto(UploadedFile $file): string
    {
        $ext = $file->getClientOriginalExtension() ?: ($file->guessExtension() ?: 'jpg');
        $path = 'kamar/'.Str::random(30).'.'.$ext;

        $stream = fopen($file->getPathname(), 'r');
        Storage::disk('public')->put($path, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return $path;
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
