<?php

namespace App\Http\Controllers;

use App\Models\LayananPengaduan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPengaduanController extends Controller
{
    public function update(Request $request, LayananPengaduan $pengaduan): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['baru', 'diproses', 'selesai'])],
        ]);

        $pengaduan->update($data);

        return redirect()->route('admin.dashboard', ['section' => 'pengaduan'])
            ->with('status', 'Status '.$pengaduan->jenisLabel().' diperbarui.');
    }

    public function destroy(LayananPengaduan $pengaduan): RedirectResponse
    {
        $label = $pengaduan->jenisLabel();
        $pengaduan->delete();

        return redirect()->route('admin.dashboard', ['section' => 'pengaduan'])
            ->with('status', $label.' berhasil dihapus.');
    }
}
