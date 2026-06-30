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
                <th>Dibuat</th>
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
                                @php
                                    $itemJenis = $item->jenis_kelas ?? ($item->kamar?->jenis_kelas ?? null);
                                @endphp
                                <div class="small mb-1"><strong>{{ $itemJenis ?: '-' }}</strong>
                                    ({{ $item->jumlah ?? $item->jumlah_unit ?? 1 }} unit)
                                    {{ optional($item->tanggal_masuk)->format('d M') }}-{{ optional($item->tanggal_keluar)->format('d M Y') }}
                                </div>
                            @endforeach
                        @else
                            @php
                                $reservasiJenis = $reservasi->kamar?->jenis_kelas ?? ($reservasi->jenis_kelas ?? null);
                            @endphp
                            {{ $reservasiJenis ?: 'Belum dialokasikan' }}
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
                        @php
                            $billing = $reservasi->retribusiBillings->last();
                        @endphp
                        @if ($billing)
                            @if ($billing->status === 'sent' && $billing->id_billing)
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <button class="btn btn-sm btn-success btn-bayar-qris"
                                        data-url="{{ route('admin.retribusi.fetch-qris', $billing) }}"
                                        data-link="{{ $billing->link_qris ?? '' }}">Bayar QRIS</button>
                                    @if ($reservasi->payment_status !== 'paid')
                                        <button class="btn btn-sm btn-outline-info btn-check-billing"
                                            data-url="{{ route('admin.retribusi.check', $billing) }}">Check Status</button>
                                    @endif
                                </div>
                            @elseif ($billing->status === 'draft' || $billing->status === 'failed')
                                <form method="POST" action="{{ route('admin.retribusi.send', $billing) }}" class="d-inline">@csrf
                                    <button class="btn btn-sm btn-primary" type="submit">Kirim e-Retribusi</button>
                                </form>
                                @if ($billing->status === 'failed')
                                    <span class="badge-soft badge-danger-soft small ms-1">Gagal</span>
                                @endif
                            @elseif ($billing->status === 'deleted')
                                <span class="badge-soft badge-secondary-soft small">Dihapus</span>
                            @endif
                        @else
                            <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal"
                                data-bs-target="#retribusiModal{{ $reservasi->id }}">+ Billing</button>
                        @endif
                    </td>
                    <td>
                        @php
                            $statusColor = match($reservasi->status) {
                                'approved' => 'success',
                                'rejected' => 'danger',
                                default => 'primary',
                            };
                            $statusLabel = match($reservasi->status) {
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                default => 'Pending',
                            };
                        @endphp
                        <div class="dropdown">
                            <button class="badge-soft badge-{{ $statusColor }}-soft dropdown-toggle border-0" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="font-size:.8rem; cursor:pointer;">
                                {{ $statusLabel }}
                            </button>
                            <ul class="dropdown-menu shadow-sm" style="border-radius:14px;border:1px solid var(--border);">
                                @foreach(['pending' => 'Pending', 'approved' => 'Approve', 'rejected' => 'Reject'] as $s => $l)
                                    @if($reservasi->status !== $s)
                                        <li>
                                            <form method="POST" action="{{ route('admin.reservasi.toggle-status', $reservasi) }}" class="d-block">
                                                @csrf
                                                <input type="hidden" name="status" value="{{ $s }}">
                                                <button type="submit" class="dropdown-item" style="font-size:.85rem;">
                                                    {{ $l }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                @endforeach
                            </ul>
                        </div>
                    </td>
                    <td>
                        <div class="small">{{ optional($reservasi->created_at)->format('d M Y') }}</div>
                        <div class="small text-muted">{{ optional($reservasi->created_at)->format('H:i') }}</div>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-ghost" data-bs-toggle="modal"
                            data-bs-target="#editReservation{{ $reservasi->id }}">Edit</button>
                        <form method="POST" action="{{ route('admin.reservasi.destroy', $reservasi) }}"
                            class="d-inline" onsubmit="return confirm('Hapus reservasi ini? Billing di e-Retribusi juga akan dihapus.')">@csrf
                            @method('DELETE')<button class="btn btn-sm btn-outline-danger" type="submit">Hapus</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-muted text-center py-4">Belum ada data reservasi.</td>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-bayar-qris').forEach(function (btn) {
        if (btn.dataset.initialized) return;
        btn.dataset.initialized = '1';
        btn.addEventListener('click', function () {
            var existingLink = this.dataset.link;
            if (existingLink) {
                window.open(existingLink, '_blank', 'noopener,noreferrer');
                return;
            }

            var url = this.dataset.url;
            var originalText = this.textContent;
            this.textContent = 'Memanggil API...';
            this.disabled = true;

            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success && data.link_qris) {
                        window.open(data.link_qris, '_blank', 'noopener,noreferrer');
                        btn.textContent = 'Bayar QRIS';
                        btn.disabled = false;
                    } else {
                        alert('Gagal mendapatkan link QRIS: ' + (data.message || 'Unknown error'));
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    alert('Terjadi kesalahan jaringan.');
                    btn.textContent = originalText;
                    btn.disabled = false;
                });
        });
    });

    document.querySelectorAll('.btn-check-billing').forEach(function (btn) {
        if (btn.dataset.initialized) return;
        btn.dataset.initialized = '1';
        btn.addEventListener('click', function () {
            var url = this.dataset.url;
            var originalText = this.textContent;
            this.textContent = 'Checking...';
            this.disabled = true;
            fetch(url, { headers: { 'Accept': 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        alert('Status billing diperbarui. Halaman akan dimuat ulang.');
                        location.reload();
                    } else {
                        alert('Gagal: ' + (data.message || 'Unknown error'));
                        btn.textContent = originalText;
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    alert('Terjadi kesalahan jaringan.');
                    btn.textContent = originalText;
                    btn.disabled = false;
                });
        });
    });
});
</script>
