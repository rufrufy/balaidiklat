<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Jenis Kelas</th><th>Kuota Total</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($kamars as $kamar)
                <tr>
                    <td class="fw-bold">{{ $kamar->jenis_kelas }}</td>
                    <td>{{ $kamar->kuota_total }} unit</td>
                    <td><button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#editRoom{{ $kamar->id }}">Edit</button><form method="POST" action="{{ route('admin.kamar.destroy', $kamar) }}" class="d-inline" onsubmit="return confirm('Hapus jenis kelas ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form></td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-muted text-center py-4">Belum ada data jenis kelas.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
