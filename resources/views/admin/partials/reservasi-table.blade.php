<div class="table-responsive">
    <table class="table align-middle">
        <thead><tr><th>Kode</th><th>Pemesan</th><th>Kegiatan</th><th>Kamar</th><th>Billing</th><th>Status</th><th>Aksi</th></tr></thead>
        <tbody>
            @forelse ($reservasis as $reservasi)
                <tr>
                    <td class="mono">{{ $reservasi->kode }}</td>
                    <td><strong>{{ $reservasi->nama_pemesan }}</strong><div class="small text-muted">{{ $reservasi->instansi ?: '-' }} / {{ $reservasi->phone_number ?: '-' }}</div></td>
                    <td>{{ $reservasi->kegiatan }}<div class="small text-muted">{{ $reservasi->jumlah_peserta }} peserta</div></td>
                    <td>
                        @if($reservasi->items->isNotEmpty())
                            @foreach($reservasi->items as $item)
                                <div class="small mb-1"><strong>{{ $item->kamar?->kode }}</strong> {{ optional($item->tanggal_masuk)->format('d M') }}-{{ optional($item->tanggal_keluar)->format('d M Y') }}</div>
                            @endforeach
                        @else
                            {{ $reservasi->kamar ? $reservasi->kamar->kode.' - '.$reservasi->kamar->nama : 'Belum dialokasikan' }}
                            <div class="small text-muted">{{ optional($reservasi->tanggal_masuk)->format('d M Y') ?: '-' }} - {{ optional($reservasi->tanggal_keluar)->format('d M Y') ?: '-' }}</div>
                        @endif
                    </td>
                    <td>Rp{{ number_format($reservasi->total_harga, 0, ',', '.') }}<div class="small text-muted">{{ $reservasi->payment_status }}</div></td>
                    <td><span class="badge-soft badge-primary-soft">{{ $reservasi->status }}</span></td>
                    <td>
                        <button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#editReservation{{ $reservasi->id }}">Edit</button>
                        <form method="POST" action="{{ route('admin.reservasi.destroy', $reservasi) }}" class="d-inline" onsubmit="return confirm('Hapus reservasi ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button></form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted text-center py-4">Belum ada data reservasi.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
