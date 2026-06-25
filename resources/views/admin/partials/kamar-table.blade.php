<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Jenis Kelas</th><th>Kuota</th><th>Harga/Malam</th><th>Fasilitas</th><th>Foto</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($kamars as $kamar)
                <tr>
                    <td class="fw-bold">{{ $kamar->jenis_kelas }}</td>
                    <td>{{ $kamar->kuota_total }} unit</td>
                    <td>Rp{{ number_format($kamar->harga_per_malam, 0, ',', '.') }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($kamar->fasilitas, 60) ?: '-' }}</td>
                    <td>{{ $kamar->fotos->count() }} foto</td>
                    <td><button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#editRoom{{ $kamar->id }}">Edit</button><form method="POST" action="{{ route('admin.kamar.destroy', $kamar) }}" class="d-inline" onsubmit="return confirm('Hapus jenis kelas ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-muted text-center py-4">Belum ada data jenis kelas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
