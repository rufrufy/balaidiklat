<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Asrama Balai Diklat BKPP Kota Semarang</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Ubuntu+Mono:wght@400;700&family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#072C2C;--secondary:#FF5F03;--surface:#EDEADE;--surface-2:#F8F6EF;--text:#111827;--muted:#667085;--border:rgba(7,44,44,.16);--font-display:"Oswald",system-ui,sans-serif;--font-body:"Ubuntu",system-ui,sans-serif;--font-mono:"Ubuntu Mono",ui-monospace,monospace;--shadow:0 18px 50px rgba(7,44,44,.12)}*{box-sizing:border-box}body{margin:0;font-family:var(--font-body);color:var(--text);background:radial-gradient(circle at 92% 6%,rgba(255,95,3,.14),transparent 30%),linear-gradient(180deg,var(--surface-2),var(--surface));min-height:100vh}h1,h2,h3,h4,.display-font{font-family:var(--font-display);color:var(--primary)}a{text-decoration:none}.navbar-public{background:rgba(237,234,222,.9);backdrop-filter:blur(16px);border-bottom:1px solid var(--border)}.brand-mark{width:48px;height:48px;border-radius:14px;background:#fff;border:1px solid var(--border);display:grid;place-items:center;font-family:var(--font-display);font-weight:700;color:var(--primary)}.brand-title{color:var(--primary);font-weight:800;line-height:1.05}.brand-subtitle{color:var(--muted);font-size:.8rem}.nav-link{color:var(--primary);font-weight:700;border-radius:999px;padding:.62rem .9rem!important}.nav-link:hover{background:rgba(7,44,44,.08)}.btn-primary-enterprise{--bs-btn-bg:var(--primary);--bs-btn-border-color:var(--primary);--bs-btn-hover-bg:#0B3A3A;--bs-btn-hover-border-color:#0B3A3A;--bs-btn-color:#fff;--bs-btn-border-radius:999px;font-weight:800;padding:.85rem 1.2rem}.btn-secondary-enterprise{background:var(--secondary);border-color:var(--secondary);color:#fff;border-radius:999px;font-weight:800;padding:.85rem 1.2rem}.btn-secondary-enterprise:hover{background:#e54e00;border-color:#e54e00;color:#fff}.btn-ghost{border:1px solid var(--border);background:rgba(255,255,255,.65);color:var(--primary);border-radius:999px;font-weight:800;padding:.85rem 1.2rem}.eyebrow{font-family:var(--font-mono);text-transform:uppercase;letter-spacing:.13em;color:var(--secondary);font-weight:700;font-size:.86rem}.hero{padding:96px 0 72px}.hero h1{font-size:clamp(3.2rem,8vw,7.5rem);line-height:.88;letter-spacing:-.04em}.hero-copy{font-size:1.18rem;color:var(--muted);max-width:660px}.hero-panel{background:linear-gradient(145deg,var(--primary),#0D3F3F);border-radius:30px;padding:32px;color:#fff;box-shadow:var(--shadow)}.metric{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.16);border-radius:20px;padding:18px}.metric strong{display:block;font-family:var(--font-display);font-size:2.4rem;color:#fff}.metric span{color:rgba(255,255,255,.76)}.section-pad{padding:76px 0}.card-enterprise,.room-card{background:rgba(255,255,255,.82);border:1px solid var(--border);border-radius:24px;box-shadow:var(--shadow)}.badge-soft{display:inline-flex;border-radius:999px;padding:.35rem .72rem;font-weight:800;font-size:.82rem}.badge-primary-soft{background:rgba(7,44,44,.1);color:var(--primary)}.badge-success-soft{background:rgba(22,163,74,.13);color:#15803d}.room-photo,.room-visual{height:210px;object-fit:cover;border-radius:24px 24px 0 0;background:linear-gradient(135deg,var(--primary),#0f4a4a);display:grid;place-items:center}.empty-state{padding:28px;border-radius:20px;background:rgba(255,255,255,.7);border:1px dashed var(--border);color:var(--muted);text-align:center}.mono{font-family:var(--font-mono)}.footer{background:var(--primary);color:rgba(255,255,255,.8);padding:30px 0;margin-top:60px}.tracking-result{border-left:5px solid var(--secondary)}

        /* Carousel styles */
        .room-card .carousel-inner img{height:210px;object-fit:cover;border-radius:24px 24px 0 0}
        .room-card .carousel-control-prev,.room-card .carousel-control-next{width:32px;height:32px;background:rgba(0,0,0,.45);border-radius:50%;top:50%;transform:translateY(-50%);opacity:.8}
        .room-card .carousel-control-prev{left:10px}.room-card .carousel-control-next{right:10px}
        .room-card .carousel-control-prev-icon,.room-card .carousel-control-next-icon{width:14px;height:14px}
        .room-card .carousel-indicators{margin-bottom:8px}.room-card .carousel-indicators button{width:8px;height:8px;border-radius:50%}

        /* Availability check */
        #availabilityResults .room-avail-card{background:#fff;border:1px solid var(--border);border-radius:18px;padding:16px;transition:transform .2s,box-shadow .2s}
        #availabilityResults .room-avail-card:hover{transform:translateY(-3px);box-shadow:var(--shadow)}
        #availabilityResults .room-avail-card img{width:100%;height:140px;object-fit:cover;border-radius:12px}
        .availability-badge{display:inline-flex;align-items:center;gap:6px;background:rgba(22,163,74,.12);color:#15803d;padding:.3rem .7rem;border-radius:999px;font-weight:700;font-size:.8rem}
        .availability-badge::before{content:'';width:8px;height:8px;border-radius:50%;background:currentColor}

        .spinner-overlay{display:flex;align-items:center;justify-content:center;padding:40px}
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-public sticky-top">
    <div class="container py-2"><a class="navbar-brand d-flex align-items-center gap-3" href="{{ route('landing') }}"><span class="brand-mark">BKPP</span><span><strong class="brand-title d-block">Asrama Balai Diklat</strong><span class="brand-subtitle">BKPP Kota Semarang</span></span></a><button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button><div id="nav" class="collapse navbar-collapse"><div class="navbar-nav ms-auto align-items-lg-center"><a class="nav-link" href="#layanan">Layanan</a><a class="nav-link" href="#ketersediaan">Cek Kamar</a><a class="nav-link" href="#booking">Booking</a><a class="nav-link" href="#lacak">Lacak Booking</a><a class="nav-link" href="#kamar">Kamar</a><a class="btn btn-ghost ms-lg-2" href="{{ route('admin.dashboard') }}">Dashboard Admin</a></div></div></div>
</nav>
<main>
    <section class="hero"><div class="container"><div class="row g-5 align-items-center"><div class="col-lg-7"><div class="eyebrow">Reservasi asrama diklat</div><h1>Booking asrama lebih cepat via WhatsApp.</h1><p class="hero-copy">Landing publik untuk peserta diklat dan instansi. Data kamar dan status booking terhubung langsung ke dashboard admin.</p><div class="d-flex flex-column flex-sm-row gap-3 mt-4"><a class="btn btn-secondary-enterprise" href="#ketersediaan">Cek Ketersediaan</a><a class="btn btn-primary-enterprise" href="#lacak">Lacak Booking</a></div></div><div class="col-lg-5"><div class="hero-panel"><div class="eyebrow text-white-50">Operasional hari ini</div><h2 class="text-white display-5">Asrama BKPP siap menerima tamu pelatihan.</h2><div class="row g-3 mt-3"><div class="col-6"><div class="metric"><strong>{{ $kamars->count() }}</strong><span>Jenis kelas</span></div></div><div class="col-6"><div class="metric"><strong>{{ $kamars->sum('kuota_total') }}</strong><span>Total unit</span></div></div></div></div></div></div></div></section>
    <section id="layanan" class="section-pad pt-0"><div class="container"><div class="row g-4"><div class="col-md-4"><div class="card-enterprise p-4 h-100"><span class="badge-soft badge-primary-soft mb-3">01</span><h4>Isi kebutuhan</h4><p class="text-muted mb-0">Tentukan tanggal, instansi, kegiatan, dan jumlah peserta.</p></div></div><div class="col-md-4"><div class="card-enterprise p-4 h-100"><span class="badge-soft badge-primary-soft mb-3">02</span><h4>Kirim WhatsApp</h4><p class="text-muted mb-0">Sistem membuat format pesan otomatis ke WhatsApp pengelola asrama.</p></div></div><div class="col-md-4"><div class="card-enterprise p-4 h-100"><span class="badge-soft badge-primary-soft mb-3">03</span><h4>Lacak booking</h4><p class="text-muted mb-0">Gunakan kode reservasi dari admin untuk melihat status terbaru.</p></div></div></div></div></section>

    {{-- ═══ CEK KETERSEDIAAN KAMAR ═══ --}}
    <section id="ketersediaan" class="section-pad pt-0">
        <div class="container">
            <div class="card-enterprise p-4 p-md-5">
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="eyebrow">Cek ketersediaan</div>
                        <h2 class="display-5">Kamar tersedia?</h2>
                        <p class="text-muted">Pilih tanggal masuk dan keluar untuk melihat kamar/kelas yang tersedia.</p>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-bold">Tanggal masuk</label>
                                <input id="availCheckin" type="date" class="form-control" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold">Tanggal keluar</label>
                                <input id="availCheckout" type="date" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <button id="checkAvailBtn" class="btn btn-secondary-enterprise w-100">Cek Ketersediaan</button>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <div id="availabilityResults">
                            <div class="empty-state h-100 d-flex align-items-center justify-content-center" style="min-height:200px">
                                Hasil pengecekan ketersediaan kamar akan muncul di sini.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section id="booking" class="section-pad pt-0"><div class="container"><div class="card-enterprise p-4 p-md-5"><div class="row g-4 align-items-end"><div class="col-lg-8"><div class="eyebrow">Form WhatsApp</div><h2 class="display-5">Ajukan reservasi.</h2><div class="row g-3"><div class="col-md-6"><label class="form-label fw-bold">Tanggal masuk</label><input id="waCheckin" type="date" class="form-control"></div><div class="col-md-6"><label class="form-label fw-bold">Tanggal keluar</label><input id="waCheckout" type="date" class="form-control"></div><div class="col-md-6"><label class="form-label fw-bold">Instansi</label><input id="waAgency" class="form-control" placeholder="BKPP Kota Semarang"></div><div class="col-md-6"><label class="form-label fw-bold">Nama kegiatan</label><input id="waEvent" class="form-control" placeholder="Diklat Manajemen ASN"></div><div class="col-md-6"><label class="form-label fw-bold">Jumlah peserta</label><input id="waGuests" type="number" min="1" value="12" class="form-control"></div></div></div><div class="col-lg-4"><a id="waBookingButton" class="btn btn-secondary-enterprise w-100" href="#" target="_blank" rel="noopener">Booking via WhatsApp</a><p class="text-muted mt-3 mb-0 small">Format pesan otomatis sesuai kebutuhan reservasi.</p></div></div></div></div></section>
    <section id="lacak" class="section-pad pt-0"><div class="container"><div class="card-enterprise p-4 p-md-5"><div class="row g-4"><div class="col-lg-5"><div class="eyebrow">Lacak booking kamar</div><h2 class="display-5">Cek status reservasi.</h2><p class="text-muted">Masukkan kode booking dari admin. Nomor WhatsApp opsional untuk memverifikasi pencarian.</p><form method="POST" action="{{ route('booking.track') }}" class="row g-3">@csrf<div class="col-12"><label class="form-label fw-bold">Kode booking</label><input class="form-control" name="kode" value="{{ $trackingCode ?? '' }}" placeholder="RSV-202606110001" required></div><div class="col-12"><label class="form-label fw-bold">No WhatsApp</label><input class="form-control" name="phone_number" placeholder="628xxxx"></div><div class="col-12"><button class="btn btn-primary-enterprise" type="submit">Lacak Booking</button></div></form></div><div class="col-lg-7">@isset($trackingCode)<div class="card-enterprise p-4 tracking-result">@if($trackingResult)<div class="eyebrow">Hasil ditemukan</div><h3>{{ $trackingResult->kode }} - {{ $trackingResult->status }}</h3><p class="mb-1"><strong>Pemesan:</strong> {{ $trackingResult->nama_pemesan }}</p><p class="mb-1"><strong>Kegiatan:</strong> {{ $trackingResult->kegiatan }}</p><p class="mb-1"><strong>Tanggal:</strong> {{ optional($trackingResult->tanggal_masuk)->format('d M Y') ?: '-' }} - {{ optional($trackingResult->tanggal_keluar)->format('d M Y') ?: '-' }}</p><p class="mb-1"><strong>Jenis Kelas:</strong> {{ $trackingResult->jenis_kelas ? $trackingResult->jenis_kelas.' ('.($trackingResult->jumlah ?? 1).' unit)' : 'Belum dialokasikan' }}</p>@if($trackingResult->items->isNotEmpty())<div class="mb-2"><strong>Detail:</strong>@foreach($trackingResult->items as $item)<div class="small text-muted">{{ $item->jenis_kelas ?: '-' }} ({{ $item->jumlah ?? 1 }} unit) | {{ optional($item->tanggal_masuk)->format('d M Y') }} - {{ optional($item->tanggal_keluar)->format('d M Y') }}</div>@endforeach</div>@endif<p class="mb-1"><strong>Total billing:</strong> Rp{{ number_format($trackingResult->total_harga, 0, ',', '.') }}</p><p class="mb-1"><strong>Status payment:</strong> {{ $trackingResult->payment_status }}</p><p class="mb-0"><strong>Catatan:</strong> {{ $trackingResult->catatan ?: '-' }}</p>@else<div class="eyebrow">Tidak ditemukan</div><h3>Kode {{ $trackingCode }} belum terdaftar.</h3><p class="text-muted mb-0">Pastikan kode booking sesuai atau hubungi admin melalui WhatsApp.</p>@endif</div>@else<div class="empty-state h-100 d-flex align-items-center justify-content-center">Hasil pelacakan akan muncul di sini.</div>@endisset</div></div></div></div></section>

    {{-- ═══ DAFTAR KAMAR DENGAN CAROUSEL FOTO ═══ --}}
    <section id="kamar" class="section-pad pt-0">
        <div class="container">
            <div class="row g-4 align-items-end mb-4">
                <div class="col-lg-8">
                    <div class="eyebrow">Pilihan kamar</div>
                    <h2 class="display-5 mb-2">Informasi kamar asrama.</h2>
                    <p class="text-muted mb-0">Data diambil langsung dari tabel <span class="mono">kamars</span>.</p>
                </div>
            </div>
            @if ($kamars->isEmpty())
                <div class="empty-state">Belum ada data jenis kelas. Silakan login admin untuk menambahkan.</div>
            @else
                <div class="row g-4">
                    @foreach ($kamars as $kamar)
                        <div class="col-md-6 col-xl-3">
                            <article class="room-card h-100">
                                <div class="room-visual"><h4 class="text-white mb-0">{{ $kamar->jenis_kelas }}</h4></div>
                                <div class="p-4">
                                    <h4>{{ $kamar->jenis_kelas }}</h4>
                                    <p class="fw-bold mb-2">Kuota: {{ $kamar->kuota_total }} unit</p>
                                    <span class="badge-soft badge-success-soft">Tersedia untuk dipesan</span>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <section id="kontak" class="section-pad pt-0"><div class="container"><div class="card-enterprise p-4 p-md-5"><div class="row g-4 align-items-center"><div class="col-lg-8"><div class="eyebrow">Kontak</div><h2 class="display-5 mb-2">Siap booking asrama?</h2><p class="text-muted mb-0">Klik tombol WhatsApp. Format pesan otomatis membawa tanggal, instansi, kegiatan, dan jumlah peserta.</p></div><div class="col-lg-4 text-lg-end"><a id="footerWaButton" class="btn btn-secondary-enterprise" href="#" target="_blank" rel="noopener">Booking via WhatsApp</a></div></div></div></div></section>
</main>
<footer class="footer"><div class="container d-flex flex-column flex-md-row justify-content-between gap-3"><div><strong>Asrama Balai Diklat BKPP Kota Semarang</strong><div>Landing publik DB-backed.</div></div><div class="mono">Laravel 13 - Bootstrap 5.x</div></div></footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ASRAMA_WHATSAPP_NUMBER="6281200000000";function makeWhatsappText(){return["Halo Admin Asrama Balai Diklat BKPP Kota Semarang.","Saya ingin mengajukan reservasi asrama.","","Nama instansi: "+(document.getElementById("waAgency").value||"-"),"Nama kegiatan: "+(document.getElementById("waEvent").value||"-"),"Tanggal masuk: "+(document.getElementById("waCheckin").value||"-"),"Tanggal keluar: "+(document.getElementById("waCheckout").value||"-"),"Jumlah peserta: "+(document.getElementById("waGuests").value||"-"),"","Mohon info ketersediaan kamar dan prosedur selanjutnya. Terima kasih."].join("\n")}function updateWhatsappLinks(){const url=`https://wa.me/${ASRAMA_WHATSAPP_NUMBER}?text=${encodeURIComponent(makeWhatsappText())}`;document.getElementById("waBookingButton").href=url;document.getElementById("footerWaButton").href=url}document.querySelectorAll("#booking input").forEach(input=>input.addEventListener("input",updateWhatsappLinks));updateWhatsappLinks();

// ═══ Cek Ketersediaan AJAX ═══
document.getElementById('checkAvailBtn')?.addEventListener('click', async function() {
    const checkin = document.getElementById('availCheckin').value;
    const checkout = document.getElementById('availCheckout').value;
    const resultsDiv = document.getElementById('availabilityResults');

    if (!checkin || !checkout) {
        resultsDiv.innerHTML = '<div class="empty-state">Silakan pilih tanggal masuk dan tanggal keluar terlebih dahulu.</div>';
        return;
    }

    if (checkout <= checkin) {
        resultsDiv.innerHTML = '<div class="empty-state">Tanggal keluar harus setelah tanggal masuk.</div>';
        return;
    }

    resultsDiv.innerHTML = '<div class="spinner-overlay"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>';

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]').content;
        const response = await fetch('{{ route("cek.ketersediaan") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ tanggal_masuk: checkin, tanggal_keluar: checkout })
        });

        const data = await response.json();

        if (!response.ok) {
            resultsDiv.innerHTML = '<div class="empty-state">Terjadi kesalahan. Pastikan tanggal valid.</div>';
            return;
        }

        if (data.rooms.length === 0) {
            resultsDiv.innerHTML = `<div class="empty-state">
                <h4 style="color:var(--primary)">Tidak ada kamar tersedia</h4>
                <p class="mb-0">Maaf, semua kamar terisi pada ${data.tanggal_masuk} s/d ${data.tanggal_keluar}.<br>Silakan coba tanggal lain.</p>
            </div>`;
            return;
        }

        let html = `<div class="mb-3"><span class="availability-badge">${data.rooms.length} jenis tersedia</span> <span class="text-muted small ms-2">${data.tanggal_masuk} s/d ${data.tanggal_keluar}</span></div>`;
        html += '<div class="row g-3">';
        data.rooms.forEach(room => {
            const sisaBadge = room.sisa_kuota > 0
                ? `<span class="availability-badge">${room.sisa_kuota} unit tersisa</span>`
                : `<span class="badge bg-danger">Penuh</span>`;

            html += `<div class="col-md-6">
                <div class="room-avail-card">
                    <div style="width:100%;height:140px;border-radius:12px;background:linear-gradient(135deg,var(--primary),#0f4a4a);display:grid;place-items:center;color:#fff;font-weight:700;margin-bottom:8px">${room.jenis_kelas}</div>
                    <h4 style="font-size:1rem;margin-bottom:4px">${room.jenis_kelas}</h4>
                    <div class="text-muted small mb-1">Kuota total: ${room.kuota_total} unit</div>
                    <div class="fw-bold" style="color:var(--primary)">${sisaBadge}</div>
                </div>
            </div>`;
        });
        html += '</div>';
        resultsDiv.innerHTML = html;
    } catch (e) {
        resultsDiv.innerHTML = '<div class="empty-state">Gagal memuat data. Silakan coba lagi.</div>';
    }
});
</script>
</body>
</html>
