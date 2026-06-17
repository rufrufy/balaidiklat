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
            'kode' => ['required', 'string', 'max:50', 'unique:kamars,kode'],
            'nama' => ['required', 'string', 'max:255'],
            'tipe' => ['required', 'in:kamar,ruang_kelas'],
            'harga_per_malam' => ['required', 'integer', 'min:0'],
            'fasilitas' => ['nullable', 'string'],
            'status' => ['required', 'in:available,limited,full,maintenance'],
            'foto' => ['nullable', 'array'],
            'foto.*' => ['image', 'max:2048'],
        ], [
            'foto.*.max' => 'Ukuran foto maksimal 2MB.',
            'foto.*.image' => 'File harus berupa gambar (jpg, png, dll).',
        ]);

        // Set first uploaded photo as legacy foto_path for backward compatibility
        if ($request->hasFile('foto') && isset($request->file('foto')[0]) && $request->file('foto')[0]->isValid()) {
            $data['foto_path'] = $this->storeFoto($request->file('foto')[0]);
        }

        $kamar = Kamar::create($data);

        // Store all photos in kamar_fotos table
        if ($request->hasFile('foto')) {
            foreach ($request->file('foto') as $index => $file) {
                if ($file->isValid()) {
                    $path = ($index === 0 && isset($data['foto_path']))
                        ? $data['foto_path']
                        : $this->storeFoto($file);

                    KamarFoto::create([
                        'kamar_id' => $kamar->id,
                        'foto_path' => $path,
                        'urutan' => $index,
                    ]);
                }
            }
        }

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
            'foto' => ['nullable', 'array'],
            'foto.*' => ['image', 'max:2048'],
            'hapus_foto' => ['nullable', 'array'],
            'hapus_foto.*' => ['integer'],
        ], [
            'foto.*.max' => 'Ukuran foto maksimal 2MB.',
            'foto.*.image' => 'File harus berupa gambar (jpg, png, dll).',
        ]);

        // Delete selected existing photos
        if ($request->filled('hapus_foto')) {
            $fotosToDelete = KamarFoto::where('kamar_id', $kamar->id)
                ->whereIn('id', $request->input('hapus_foto'))
                ->get();

            foreach ($fotosToDelete as $foto) {
                Storage::disk('public')->delete($foto->foto_path);
                $foto->delete();
            }
        }

        // Add new photos
        if ($request->hasFile('foto')) {
            $maxUrutan = KamarFoto::where('kamar_id', $kamar->id)->max('urutan') ?? -1;

            foreach ($request->file('foto') as $index => $file) {
                if ($file->isValid()) {
                    $path = $this->storeFoto($file);

                    KamarFoto::create([
                        'kamar_id' => $kamar->id,
                        'foto_path' => $path,
                        'urutan' => $maxUrutan + $index + 1,
                    ]);
                }
            }
        }

        // Update legacy foto_path to first photo
        $kamar->load('fotos');
        $firstFoto = $kamar->fotos->first();
        $data['foto_path'] = $firstFoto?->foto_path;

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
        // Delete all photos from storage
        foreach ($kamar->fotos as $foto) {
            Storage::disk('public')->delete($foto->foto_path);
        }

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
