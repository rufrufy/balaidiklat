<?php

namespace App\Http\Controllers;

use App\Models\Kamar;
use App\Models\KamarFoto;
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
            'jenis_kelas' => ['required', 'string', 'max:255', 'unique:kamars,jenis_kelas'],
            'kuota_total' => ['required', 'integer', 'min:0'],
            'fasilitas' => ['nullable', 'string'],
            'harga_per_malam' => ['required', 'integer', 'min:0'],
            'foto' => ['nullable', 'array'],
            'foto.*' => ['image', 'max:2048'],
        ], [
            'foto.*.max' => 'Ukuran foto maksimal 2MB.',
            'foto.*.image' => 'File harus berupa gambar (jpg, png, dll).',
        ]);

        $kamar = Kamar::create($data);

        if ($request->hasFile('foto')) {
            foreach ($request->file('foto') as $index => $file) {
                if ($file->isValid()) {
                    KamarFoto::create([
                        'kamar_id' => $kamar->id,
                        'foto_path' => $this->storeFoto($file),
                        'urutan' => $index,
                    ]);
                }
            }
        }

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Jenis kelas berhasil ditambahkan.');
    }

    public function update(Request $request, Kamar $kamar): RedirectResponse
    {
        $data = $request->validate([
            'jenis_kelas' => ['required', 'string', 'max:255', 'unique:kamars,jenis_kelas,'.$kamar->id],
            'kuota_total' => ['required', 'integer', 'min:0'],
            'fasilitas' => ['nullable', 'string'],
            'harga_per_malam' => ['required', 'integer', 'min:0'],
            'foto' => ['nullable', 'array'],
            'foto.*' => ['image', 'max:2048'],
            'hapus_foto' => ['nullable', 'array'],
            'hapus_foto.*' => ['integer'],
        ], [
            'foto.*.max' => 'Ukuran foto maksimal 2MB.',
            'foto.*.image' => 'File harus berupa gambar (jpg, png, dll).',
        ]);

        if ($request->filled('hapus_foto')) {
            $fotosToDelete = KamarFoto::where('kamar_id', $kamar->id)
                ->whereIn('id', $request->input('hapus_foto'))
                ->get();

            foreach ($fotosToDelete as $foto) {
                Storage::disk('public')->delete($foto->foto_path);
                $foto->delete();
            }
        }

        if ($request->hasFile('foto')) {
            $maxUrutan = KamarFoto::where('kamar_id', $kamar->id)->max('urutan') ?? -1;

            foreach ($request->file('foto') as $index => $file) {
                if ($file->isValid()) {
                    KamarFoto::create([
                        'kamar_id' => $kamar->id,
                        'foto_path' => $this->storeFoto($file),
                        'urutan' => $maxUrutan + $index + 1,
                    ]);
                }
            }
        }

        $kamar->update($data);

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Jenis kelas berhasil diperbarui.');
    }

    public function destroy(Kamar $kamar): RedirectResponse
    {
        foreach ($kamar->fotos as $foto) {
            Storage::disk('public')->delete($foto->foto_path);
        }

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
            'fasilitas' => $kamar->fasilitas,
            'harga_per_malam' => $kamar->harga_per_malam,
        ]);

        return redirect()->route('admin.dashboard', ['section' => 'kamar'])->with('status', 'Jenis kelas berhasil diduplikat.');
    }

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
}
