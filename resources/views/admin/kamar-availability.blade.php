<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cek Ketersediaan Kamar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body{background:#F8F6EF}.wrap{max-width:1100px;margin:32px auto}.card{border-radius:22px;border:1px solid rgba(7,44,44,.16)}.brand{color:#072C2C}</style>
</head>
<body>
<main class="wrap">
    <div class="d-flex justify-content-between align-items-center mb-4"><div><div class="text-warning text-uppercase fw-bold small">Manajemen kamar</div><h1 class="brand">Cek Available Kamar</h1></div><a class="btn btn-outline-secondary" href="{{ route('admin.dashboard', ['section' => 'kamar']) }}">Kembali</a></div>
    <div class="card p-4 mb-4"><form method="GET" class="row g-3"><div class="col-md-5"><label class="form-label fw-bold">Tanggal masuk</label><input type="date" class="form-control" name="tanggal_masuk" value="{{ $tanggalMasuk }}" required></div><div class="col-md-5"><label class="form-label fw-bold">Tanggal keluar</label><input type="date" class="form-control" name="tanggal_keluar" value="{{ $tanggalKeluar }}" required></div><div class="col-md-2 d-flex align-items-end"><button class="btn btn-success w-100">Cek</button></div></form></div>
    @if($tanggalMasuk && $tanggalKeluar)
        <div class="card p-4"><h2 class="h4 mb-3">Ketersediaan {{ \Illuminate\Support\Carbon::parse($tanggalMasuk)->format('d M Y') }} - {{ \Illuminate\Support\Carbon::parse($tanggalKeluar)->format('d M Y') }}</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Jenis Kelas</th><th>Kuota Total</th><th>Sisa Kuota</th></tr></thead><tbody>@forelse($kamars as $kamar)<tr><td class="fw-bold">{{ $kamar->jenis_kelas }}</td><td>{{ $kamar->kuota_total }} unit</td><td><span class="badge @if(($kamar->sisa_kuota ?? 0) > 0) bg-success @else bg-danger @endif">{{ $kamar->sisa_kuota ?? 0 }} unit</span></td></tr>@empty<tr><td colspan="3" class="text-center text-muted py-4">Tidak ada data jenis kelas.</td></tr>@endforelse</tbody></table></div></div>
    @endif
</main>
</body>
</html>
