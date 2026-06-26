<div class="table-responsive">
    <table class="table align-middle">
        <thead>
            <tr>
                <th>Kode</th>
                <th>Pemesan</th>
                <th>Kegiatan</th>
                <th>Kamar</th>
                <th>Billing</th>
                <th>eRetribusi</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($reservasis as $reservasi)
                <tr>
                    <td class="mono">{{ $reservasi->kode }}</td>
                    <td><strong>{{ $reservasi->nama_pemesan }}</strong>
                        <div class="small text-muted">
                            {{ $reservasi->tipe_penyewa === 'instansi' ? ($reservasi->instansi ?: 'Instansi') : 'Perorangan' }}
                            / {{ $reservasi->phone_number ?: '-' }}</div>
                    </td>
                    <td>{{ $reservasi->tipe_penyewa === 'instansi' ? $reservasi->kegiatan : '-' }}<div
                            class="small text-muted">
                            {{ $reservasi->tipe_penyewa === 'instansi' ? $reservasi->jumlah_peserta . ' peserta' : 'Perorangan' }}
                        </div>
                    </td>
                    <td>
                        @if ($reservasi->items->isNotEmpty())
                            @foreach ($reservasi->items as $item)
                                <div class="small mb-1"><strong>{{ $item->jenis_kelas ?: $item->kamar?->jenis_kelas ?: '-' }}</strong>
                                    ({{ $item->jumlah ?? $item->jumlah_unit ?? 1 }} unit)
                                    {{ optional($item->tanggal_masuk)->format('d M') }}-{{ optional($item->tanggal_keluar)->format('d M Y') }}
                                </div>
                            @endforeach
                        @else
                            {{ $reservasi->kamar ? $reservasi->kamar->jenis_kelas : ($reservasi->jenis_kelas ?: 'Belum dialokasikan') }}
                            <div class="small text-muted">
                                {{ optional($reservasi->tanggal_masuk)->format('d M Y') ?: '-' }} -
                                {{ optional($reservasi->tanggal_keluar)->format('d M Y') ?: '-' }}</div>
                        @endif
                    </td>
                    <td>Rp{{ number_format($reservasi->total_harga, 0, ',', '.') }}<div class="small">
                            <form method="POST" action="{{ route('admin.reservasi.toggle-payment', $reservasi) }}"
                                class="d-inline">@csrf
                                <label class="form-check-label small">
                                    <input type="checkbox" class="form-check-input me-1" onchange="this.form.submit()"
                                        @checked($reservasi->payment_status === 'paid')>
                                    {{ $reservasi->payment_status === 'paid' ? 'Lunas' : 'Belum dibayar' }}
                                </label>
                            </form>
                            @if ($reservasi->bukti_pembayaran)
                                <div class="mt-1"><a href="{{ asset('storage/' . $reservasi->bukti_pembayaran) }}"
                                        target="_blank">Lihat bukti</a></div>
                            @endif
                        </div>
                    </td>
                    <td>
                        @forelse ($reservasi->retribusiBillings as $billing)
                            <div class="small mb-1">Rp{{ number_format($billing->kredit, 0, ',', '.') }} <span
                                    class="badge-soft badge-primary-soft">{{ $billing->status }}</span>
                                @if ($billing->status !== 'sent')
                                    <form method="POST" action="{{ route('admin.retribusi.send', $billing) }}"
                                        class="d-inline">@csrf<button class="btn btn-sm btn-link p-0"
                                            type="submit">Kirim</button></form>
                                @endif
                            </div>
                        @empty
                            <span class="small text-muted">-</span>
                        @endforelse
                        <button class="btn btn-sm btn-link p-0" data-bs-toggle="modal"
                            data-bs-target="#retribusiModal{{ $reservasi->id }}">+ Billing</button>
                    </td>
                    <td><span class="badge-soft badge-primary-soft">{{ $reservasi->status }}</span></td>
                    <td>
                        <button class="btn btn-sm btn-ghost" data-bs-toggle="modal"
                            data-bs-target="#editReservation{{ $reservasi->id }}">Edit</button>
                        <form method="POST" action="{{ route('admin.reservasi.destroy', $reservasi) }}"
                            class="d-inline" onsubmit="return confirm('Hapus reservasi ini?')">@csrf
                            @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-muted text-center py-4">Belum ada data reservasi.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@foreach ($reservasis as $reservasi)
    <div class="modal fade" id="retribusiModal{{ $reservasi->id }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" action="{{ route('admin.retribusi.store', $reservasi) }}" class="modal-content">@csrf
                <div class="modal-header">
                    <h2 class="modal-title h4">Billing eRetribusi - {{ $reservasi->kode }}</h2><button type="button"
                        class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Field statis sudah terisi default sesuai integrasi eRetribusi. Yang
                        dinamis: tanggal, keterangan, dan kredit.</p>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label fw-bold">No SKPD</label><input
                                class="form-control bg-light" name="noskpd" value="1111" readonly></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Periode</label><input
                                class="form-control bg-light" name="periode" value="2026" readonly></div>
                        <div class="col-md-4"><label class="form-label fw-bold">STS/SSRD</label><input
                                class="form-control bg-light" name="sts_ssrd" value="4 1 2" readonly></div>
                        <div class="col-md-6"><label class="form-label fw-bold">Nama penyetor</label><input
                                class="form-control bg-light" name="namapenyetor" value="BKPP" readonly></div>
                        <div class="col-md-6"><label class="form-label fw-bold">T. Nama</label><input
                                class="form-control bg-light" name="t_nama" value="BKPP" readonly></div>
                        <div class="col-md-4"><label class="form-label fw-bold">NPWRD</label><input
                                class="form-control bg-light" name="npwrd" value="123" readonly></div>
                        <div class="col-md-8"><label class="form-label fw-bold">Rekening</label><input
                                class="form-control bg-light" name="rekening"
                                value="76|4.1.02.02.01.0005|Retribusi Pemakaian Ruangan Balai Diklat" readonly></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Tanggal</label><input type="date"
                                class="form-control" name="tanggal" value="{{ now()->format('Y-m-d') }}" required>
                        </div>
                        <div class="col-md-8"><label class="form-label fw-bold">Keterangan</label><input
                                class="form-control" name="keterangan" value="Sewa Diklat" required></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Kredit</label><input type="number"
                                min="0" class="form-control" name="kredit"
                                value="{{ $reservasi->total_harga ?: 210000 }}" required></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-ghost"
                        data-bs-dismiss="modal">Batal</button><button class="btn btn-primary-enterprise"
                        type="submit">Simpan Billing</button></div>
            </form>
        </div>
    </div>
@endforeach
