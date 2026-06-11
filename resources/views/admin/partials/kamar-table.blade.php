<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Harga/Malam</th><th>Fasilitas</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($kamars as $kamar)
                <tr>
                    <td class="mono">{{ $kamar->kode }}</td>
                    <td>{{ $kamar->nama }}</td>
                    <td>{{ $kamar->tipeLabel() }}</td>
                    <td>Rp{{ number_format($kamar->harga_per_malam, 0, ',', '.') }}</td>
                    <td class="small">{{ \Illuminate\Support\Str::limit($kamar->fasilitas, 60) ?: '-' }}</td>
                    <td><span class="badge-soft badge-primary-soft">{{ $kamar->status }}</span></td>
                    <td><button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#editRoom{{ $kamar->id }}">Edit</button><form method="POST" action="{{ route('admin.kamar.destroy', $kamar) }}" class="d-inline" onsubmit="return confirm('Hapus kamar ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted text-center py-4">Belum ada data kamar.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
