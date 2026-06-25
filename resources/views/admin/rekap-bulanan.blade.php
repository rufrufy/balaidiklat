<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekap Bulanan Reservasi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#F8F6EF;color:#111827}.sheet{max-width:1120px;margin:32px auto;background:#fff;border:1px solid rgba(7,44,44,.16);border-radius:20px;padding:28px}.brand{color:#072C2C}.stat{border:1px solid #ddd;border-radius:16px;padding:16px}.print-only{display:none}@media print{.no-print{display:none!important}.print-only{display:block}.sheet{margin:0;border:0;border-radius:0;max-width:none}body{background:#fff}}</style>
</head>
<body>
<main class="sheet">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
        <div><div class="text-uppercase text-warning fw-bold small">Rekap bulanan</div><h1 class="brand">Reservasi Asrama BKPP</h1><p class="mb-0">Periode {{ \Illuminate\Support\Carbon::parse($bulan.'-01')->translatedFormat('F Y') }}</p></div>
        <div class="no-print d-flex gap-2"><a class="btn btn-outline-secondary" href="{{ route('admin.dashboard', ['section' => 'reservasi']) }}">Kembali</a><button class="btn btn-success" onclick="window.print()">Export PDF</button></div>
    </div>
    <form class="no-print row g-2 mb-4" method="GET" action="{{ route('admin.rekap.bulanan') }}"><div class="col-md-4"><input type="month" class="form-control" name="bulan" value="{{ $bulan }}"></div><div class="col-md-2"><button class="btn btn-primary w-100">Tampilkan</button></div></form>
    <div class="row g-3 mb-4"><div class="col-md-3"><div class="stat"><div>Total reservasi</div><h2>{{ $stats['total'] }}</h2></div></div><div class="col-md-3"><div class="stat"><div>Approved</div><h2>{{ $stats['approved'] }}</h2></div></div><div class="col-md-3"><div class="stat"><div>Pending</div><h2>{{ $stats['pending'] }}</h2></div></div><div class="col-md-3"><div class="stat"><div>Total peserta</div><h2>{{ $stats['peserta'] }}</h2></div></div></div>
    <div class="row g-3 mb-4"><div class="col-md-6"><div class="stat" style="background:#072C2C;color:#fff;border:0"><div>Pendapatan bulan ini (sudah dibayar)</div><h2 class="mb-0">Rp{{ number_format($stats['pendapatan'], 0, ',', '.') }}</h2><div class="small">{{ $stats['paid_count'] }} reservasi lunas</div></div></div></div>
    <div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Kode</th><th>Pemesan</th><th>Instansi</th><th>Kegiatan</th><th>Tanggal/Kamar</th><th>Peserta</th><th>Billing</th><th>Status</th></tr></thead><tbody>@forelse($reservasis as $reservasi)<tr><td>{{ $reservasi->kode }}</td><td>{{ $reservasi->nama_pemesan }}<div class="small text-muted">{{ $reservasi->phone_number }}</div></td><td>{{ $reservasi->instansi ?: '-' }}</td><td>{{ $reservasi->kegiatan }}</td><td>@if($reservasi->items->isNotEmpty())@foreach($reservasi->items as $item)<div>{{ $item->jenis_kelas ?: '-' }} ({{ $item->jumlah ?? 1 }} unit)<div class="small text-muted">{{ optional($item->tanggal_masuk)->format('d M Y') }} - {{ optional($item->tanggal_keluar)->format('d M Y') }}</div></div>@endforeach @else {{ optional($reservasi->tanggal_masuk)->format('d M Y') ?: '-' }} - {{ optional($reservasi->tanggal_keluar)->format('d M Y') ?: '-' }}<div class="small text-muted">{{ $reservasi->jenis_kelas ? $reservasi->jenis_kelas.' ('.($reservasi->jumlah ?? 1).' unit)' : '-' }}</div>@endif</td><td>{{ $reservasi->jumlah_peserta }}</td><td>Rp{{ number_format($reservasi->total_harga, 0, ',', '.') }}<div class="small text-muted">{{ $reservasi->payment_status }}</div></td><td>{{ $reservasi->status }}</td></tr>@empty<tr><td colspan="8" class="text-center text-muted py-4">Tidak ada reservasi pada bulan ini.</td></tr>@endforelse</tbody></table></div>
</main>
</body>
</html>
