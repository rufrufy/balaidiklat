<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard Admin - Asrama BKPP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Oswald:wght@500;600;700&family=Ubuntu+Mono:wght@400;700&family=Ubuntu:wght@400;500;700&display=swap"
        rel="stylesheet">
    <style>
        :root {
            --primary: #072C2C;
            --secondary: #FF5F03;
            --surface: #EDEADE;
            --surface-2: #F8F6EF;
            --text: #111827;
            --muted: #667085;
            --border: rgba(7, 44, 44, .16);
            --font-display: "Oswald", system-ui, sans-serif;
            --font-body: "Ubuntu", system-ui, sans-serif;
            --font-mono: "Ubuntu Mono", ui-monospace, monospace;
            --shadow: 0 18px 50px rgba(7, 44, 44, .12)
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: var(--font-body);
            color: var(--text);
            background: var(--surface-2)
        }

        h1,
        h2,
        h3 {
            font-family: var(--font-display);
            color: var(--primary)
        }

        .admin-shell {
            display: grid;
            grid-template-columns: 292px 1fr;
            min-height: 100vh
        }

        .sidebar {
            background: var(--primary);
            color: #fff;
            padding: 24px;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow: auto
        }

        .brand-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding-bottom: 24px;
            border-bottom: 1px solid rgba(255, 255, 255, .16);
            margin-bottom: 22px
        }

        .brand-mark {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            background: #fff;
            color: var(--primary);
            display: grid;
            place-items: center;
            font-family: var(--font-display);
            font-weight: 800
        }

        .brand-title {
            font-weight: 800;
            color: #fff
        }

        .brand-subtitle {
            color: rgba(255, 255, 255, .66);
            font-size: .8rem
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, .8);
            border-radius: 14px;
            padding: .78rem 1rem;
            margin-bottom: 7px;
            font-weight: 700;
            border: 1px solid transparent;
            text-align: left;
            width: 100%;
            background: transparent;
            text-decoration: none
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background: rgba(255, 255, 255, .12);
            border-color: rgba(255, 255, 255, .16)
        }

        .sidebar-foot {
            margin-top: 20px;
            padding: 16px;
            border-radius: 18px;
            background: rgba(255, 255, 255, .1);
            border: 1px solid rgba(255, 255, 255, .16);
            color: rgba(255, 255, 255, .78)
        }

        .main {
            min-width: 0;
            padding: 28px
        }

        .topbar,
        .card-enterprise {
            background: rgba(255, 255, 255, .82);
            border: 1px solid var(--border);
            border-radius: 24px;
            box-shadow: var(--shadow)
        }

        .topbar {
            padding: 18px;
            margin-bottom: 24px
        }

        .eyebrow {
            font-family: var(--font-mono);
            text-transform: uppercase;
            letter-spacing: .13em;
            color: var(--secondary);
            font-weight: 700;
            font-size: .84rem
        }

        .btn-primary-enterprise {
            --bs-btn-bg: var(--primary);
            --bs-btn-border-color: var(--primary);
            --bs-btn-hover-bg: #0B3A3A;
            --bs-btn-hover-border-color: #0B3A3A;
            --bs-btn-color: #fff;
            border-radius: 999px;
            font-weight: 800
        }

        .btn-secondary-enterprise {
            background: var(--secondary);
            border-color: var(--secondary);
            color: #fff;
            border-radius: 999px;
            font-weight: 800
        }

        .btn-ghost {
            border: 1px solid var(--border);
            background: #fff;
            color: var(--primary);
            border-radius: 999px;
            font-weight: 800
        }

        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 18px;
            box-shadow: var(--shadow)
        }

        .stat-label {
            color: var(--muted);
            font-size: .9rem
        }

        .stat-value {
            font-family: var(--font-display);
            font-size: 2.2rem;
            color: var(--primary);
            line-height: 1
        }

        .page-section {
            display: none
        }

        .page-section.active {
            display: block
        }

        .badge-soft {
            display: inline-flex;
            border-radius: 999px;
            padding: .4rem .7rem;
            font-size: .78rem;
            font-weight: 800
        }

        .badge-primary-soft {
            background: rgba(7, 44, 44, .1);
            color: var(--primary)
        }

        .badge-success-soft {
            background: rgba(25, 135, 84, .12);
            color: #146c43
        }

        .mono {
            font-family: var(--font-mono)
        }

        .room-photo {
            height: 170px;
            object-fit: cover;
            border-radius: 18px;
            background: linear-gradient(135deg, #0c3b3b, #ff7a2a)
        }

        .empty-state {
            border: 1px dashed var(--border);
            border-radius: 18px;
            padding: 24px;
            color: var(--muted);
            background: #fff
        }

        .chat-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 18px
        }

        .chat-list {
            display: grid;
            gap: 10px;
            max-height: 560px;
            overflow: auto
        }

        .chat-item {
            width: 100%;
            text-align: left;
            border: 1px solid var(--border);
            background: #fff;
            border-radius: 16px;
            padding: 12px;
            color: var(--text)
        }

        .chat-item.active {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(255, 95, 3, .12)
        }

        .chat-body {
            height: 480px;
            overflow: auto;
            background: #f7f7f2;
            padding: 18px
        }

        .bubble {
            max-width: 78%;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 12px;
            margin-bottom: 10px
        }

        .bubble.outbound {
            margin-left: auto;
            background: #dcf8c6
        }

        .multi-items {
            display: none
        }

        .multi-items.active {
            display: block
        }

        .room-carousel .carousel-inner img {
            height: 170px;
            object-fit: cover;
            border-radius: 18px
        }

        .room-carousel .carousel-control-prev,
        .room-carousel .carousel-control-next {
            width: 28px;
            height: 28px;
            background: rgba(0, 0, 0, .5);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            opacity: .85
        }

        .room-carousel .carousel-control-prev {
            left: 8px
        }

        .room-carousel .carousel-control-next {
            right: 8px
        }

        .room-carousel .carousel-control-prev-icon,
        .room-carousel .carousel-control-next-icon {
            width: 12px;
            height: 12px
        }

        .room-carousel .carousel-indicators {
            margin-bottom: 6px
        }

        .room-carousel .carousel-indicators button {
            width: 7px;
            height: 7px;
            border-radius: 50%
        }

        .foto-preview {
            display: inline-block;
            position: relative;
            margin: 4px
        }

        .foto-preview img {
            width: 80px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid var(--border)
        }

        .foto-preview .form-check-input {
            position: absolute;
            top: 2px;
            right: 2px
        }

        .filter-tabs .btn {
            border-radius: 999px;
            font-weight: 700;
            font-size: .85rem;
            padding: .4rem .9rem
        }

        .filter-tabs .btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary)
        }

        .stars {
            color: #f59e0b;
            letter-spacing: 1px
        }

        @media(max-width:992px) {
            .admin-shell {
                grid-template-columns: 1fr
            }

            .sidebar {
                position: relative;
                height: auto
            }

            .chat-layout {
                grid-template-columns: 1fr
            }
        }
    </style>
</head>

<body>
    <div class="admin-shell">
        <aside class="sidebar">
            <div class="brand-row"><span class="brand-mark">BKPP</span>
                <div><strong class="brand-title d-block">Admin Asrama BKPP</strong><span
                        class="brand-subtitle">Dashboard pengelolaan</span></div>
            </div>
            <nav class="nav flex-column">
                <button class="nav-link active" data-section="dashboard">Dashboard</button>
                <button class="nav-link" data-section="kamar">Manajemen Kamar</button>
                <button class="nav-link" data-section="reservasi">Reservasi Kamar</button>
                <button class="nav-link" data-section="rules">Aturan Chatbot</button>
                <button class="nav-link" data-section="pengaduan">Laporan & Saran</button>
                <button class="nav-link" data-section="whatsapp">WhatsApp Chat</button>
                <a class="nav-link" href="{{ route('admin.rekap.bulanan') }}">Rekap Bulanan</a>
            </nav>
            <div class="sidebar-foot">
                <strong>Akun</strong>
                <button class="btn btn-sm btn-light mt-2 d-block w-100" data-section="password">Ganti Password</button>
                <a class="btn btn-sm btn-light mt-2 d-block w-100 text-center text-decoration-none" href="{{ route('landing') }}">Lihat Landing</a>
            </div>
        </aside>
        <main class="main">
            <div class="topbar d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <div class="eyebrow">Dashboard admin</div>
                    <h1 class="display-5 mb-0">Data asrama, reservasi, dan KirimChat.</h1>
                </div>
                <div class="d-flex gap-2 align-items-center"><span class="text-muted">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="btn btn-ghost"
                            type="submit">Logout</button></form>
                </div>
            </div>

            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            <section id="dashboard" class="page-section active">
                <div class="row g-3 mb-4">
                    <div class="col-md-2 col-6">
                        <div class="stat-card">
                            <div class="stat-label">Jenis Kelas</div>
                            <div class="stat-value">{{ $stats['kamar'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="stat-card">
                            <div class="stat-label">Reservasi</div>
                            <div class="stat-value">{{ $stats['reservasi'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-2 col-6">
                        <div class="stat-card">
                            <div class="stat-label">Aturan</div>
                            <div class="stat-value">{{ $stats['rules'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-card">
                            <div class="stat-label">WA Sessions</div>
                            <div class="stat-value">{{ $stats['sessions'] }}</div>
                        </div>
                    </div>
                    <div class="col-md-3 col-6">
                        <div class="stat-card">
                            <div class="stat-label">Pesan</div>
                            <div class="stat-value">{{ $stats['messages'] }}</div>
                        </div>
                    </div>
                </div>
                <div class="row g-4">
                    <div class="col-xl-6">
                        <div class="card-enterprise p-4">
                            <h2 class="h3 mb-3">Pemesanan baru</h2>@include('admin.partials.reservasi-table')
                        </div>
                    </div>
                    <div class="col-xl-6">
                        <div class="card-enterprise p-4">
                            <h2 class="h3 mb-3">Kamar</h2>@include('admin.partials.kamar-table')
                        </div>
                    </div>
                </div>
            </section>

            <section id="kamar" class="page-section">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <div class="eyebrow">Manajemen kamar</div>
                        <h2 class="display-5 mb-0">Kelola jenis kelas & ketersediaan.</h2>
                    </div>
                    <div class="d-flex gap-2"><button class="btn btn-secondary-enterprise" data-bs-toggle="modal"
                            data-bs-target="#roomModal">Tambah Jenis Kelas</button></div>
                </div>
                <div class="card-enterprise p-3 mb-4"><span class="text-muted small">Setiap jenis kelas memiliki stok
                        total unit. Ketersediaan (Tersedia) dihitung otomatis: stok total dikurangi unit yang sudah dipesan pada
                        tanggal tersebut.</span></div>
                <div class="row g-3 mb-4">
                    @forelse ($kamars as $kamar)
                        @php $fotos = $kamar->allFotoPaths(); @endphp
                        <div class="col-xl-4 col-md-6">
                            <div class="card-enterprise p-3 h-100">
                                @if ($fotos->count() > 1)
                                    <div id="adminCarousel{{ $kamar->id }}"
                                        class="carousel slide room-carousel mb-3" data-bs-ride="carousel">
                                        <div class="carousel-indicators">
                                            @foreach ($fotos as $i => $p)
                                                <button type="button"
                                                    data-bs-target="#adminCarousel{{ $kamar->id }}"
                                                    data-bs-slide-to="{{ $i }}"
                                                    @if ($i === 0) class="active" @endif></button>
                                            @endforeach
                                        </div>
                                        <div class="carousel-inner">
                                            @foreach ($fotos as $i => $p)
                                                <div
                                                    class="carousel-item @if ($i === 0) active @endif">
                                                    <img class="d-block w-100" src="{{ asset('storage/' . $p) }}"
                                                        alt="{{ $kamar->jenis_kelas }}"></div>
                                            @endforeach
                                        </div>
                                        <button class="carousel-control-prev" type="button"
                                            data-bs-target="#adminCarousel{{ $kamar->id }}"
                                            data-bs-slide="prev"><span
                                                class="carousel-control-prev-icon"></span></button>
                                        <button class="carousel-control-next" type="button"
                                            data-bs-target="#adminCarousel{{ $kamar->id }}"
                                            data-bs-slide="next"><span
                                                class="carousel-control-next-icon"></span></button>
                                    </div>
                                @elseif ($fotos->count() === 1)
                                    <img class="room-photo w-100 mb-3" src="{{ asset('storage/' . $fotos->first()) }}"
                                        alt="{{ $kamar->jenis_kelas }}">
                                @else
                                    <div class="room-photo w-100 mb-3"></div>
                                @endif
                                <div class="d-flex justify-content-between gap-2">
                                    <h3 class="h4 mb-1">{{ $kamar->jenis_kelas }}</h3><span
                                        class="badge-soft badge-primary-soft">ID: {{ $kamar->id }}</span>
                                </div>
                                <p class="mb-2">
                                    <span class="badge bg-success">Tersedia</span>
                                    <span class="small text-muted">Stok: {{ $kamar->stok_total ?? 1 }} unit</span>
                                </p>
                                <p class="text-muted mb-2">{{ $kamar->tipeLabel() }}</p>
                                @if ($kamar->fasilitas)
                                    <p class="mb-1 small">Fasilitas: {{ $kamar->fasilitas }}</p>
                                @endif
                                <p class="fw-bold mb-3">Rp{{ number_format($kamar->harga_per_malam, 0, ',', '.') }} /
                                    malam</p>
                                <div class="small text-muted mb-2">{{ $fotos->count() }} foto</div>
                                <button class="btn btn-sm btn-ghost mb-2" data-bs-toggle="modal"
                                    data-bs-target="#editRoom{{ $kamar->id }}">Edit</button>
                                <form method="POST" action="{{ route('admin.kamar.duplicate', $kamar) }}"
                                    class="d-flex gap-2 mb-2">@csrf<input class="form-control form-control-sm"
                                        name="jenis_kelas" placeholder="Nama jenis baru" required><button
                                        class="btn btn-sm btn-primary-enterprise" type="submit">Duplikat</button>
                                </form>
                                <form method="POST" action="{{ route('admin.kamar.destroy', $kamar) }}"
                                    onsubmit="return confirm('Hapus jenis kelas ini?')">@csrf @method('DELETE')<button
                                        class="btn btn-outline-danger btn-sm" type="submit">Hapus</button></form>
                            </div>
                        </div>
                    @empty
                        <div class="col-12">
                            <div class="empty-state">Belum ada jenis kelas. Tambahkan jenis kelas pertama lewat tombol
                                di atas.</div>
                        </div>
                    @endforelse
                </div>
                <div class="card-enterprise p-4">
                    <h3 class="mb-3">Tabel jenis kelas</h3>@include('admin.partials.kamar-table')
                </div>
            </section>

            <section id="reservasi" class="page-section">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <div class="eyebrow">Reservasi kamar</div>
                        <h2 class="display-5 mb-0">Multi-kamar, billing, dan status pembayaran.</h2>
                    </div><button class="btn btn-secondary-enterprise" data-bs-toggle="modal"
                        data-bs-target="#reservationModal">Tambah Reservasi</button>
                </div>
                <div class="card-enterprise p-4">@include('admin.partials.reservasi-table')</div>
            </section>

            <section id="rules" class="page-section">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <div class="eyebrow">Aturan chatbot</div>
                        <h2 class="display-5 mb-0">Atur keyword dan balasan otomatis webhook.</h2>
                    </div><button class="btn btn-secondary-enterprise" data-bs-toggle="modal"
                        data-bs-target="#ruleModal">Tambah Aturan</button>
                </div>

                <div class="card-enterprise p-4">
                    {{-- Toolbar: search + filter + stats --}}
                    <div class="d-flex flex-column flex-lg-row gap-3 mb-4 align-items-lg-center">
                        <div class="position-relative flex-grow-1" style="max-width: 360px;">
                            <input type="text" id="ruleSearch" class="form-control" placeholder="Cari nama / keyword / balasan...">
                        </div>
                        <div class="d-flex gap-2 flex-wrap" id="ruleFilters">
                            <button class="btn btn-sm btn-ghost active" data-rule-filter="all">Semua <span class="badge bg-secondary ms-1" id="ruleCountAll">{{ $rules->count() }}</span></button>
                            <button class="btn btn-sm btn-ghost" data-rule-filter="active">Aktif <span class="badge bg-success ms-1" id="ruleCountActive">{{ $rules->where('is_active', true)->count() }}</span></button>
                            <button class="btn btn-sm btn-ghost" data-rule-filter="inactive">Nonaktif <span class="badge bg-warning text-dark ms-1" id="ruleCountInactive">{{ $rules->where('is_active', false)->count() }}</span></button>
                        </div>
                    </div>

                    {{-- Card list --}}
                    <div id="ruleList" class="d-flex flex-column gap-3">
                        @forelse($rules as $rule)
                            <div class="rule-card" data-rule-name="{{ strtolower($rule->nama) }}" data-rule-keyword="{{ strtolower($rule->keyword) }}" data-rule-reply="{{ strtolower($rule->reply_text ?? '') }}" data-rule-active="{{ $rule->is_active ? '1' : '0' }}" data-rule-action="{{ $rule->action ?? '' }}"
                                style="border:1px solid var(--border);border-radius:14px;padding:16px;background:#fff;transition:box-shadow .2s,opacity .2s;">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div class="flex-grow-1" style="min-width: 220px;">
                                        <div class="d-flex align-items-center gap-2 mb-1">
                                            <span class="badge bg-primary">{{ $rule->priority }}</span>
                                            <h3 class="h6 mb-0">{{ $rule->nama }}</h3>
                                            @if($rule->is_active)
                                                <span class="badge-soft badge-success-soft">Aktif</span>
                                            @else
                                                <span class="badge-soft badge-warning-soft">Nonaktif</span>
                                            @endif
                                        </div>
                                        <div class="d-flex gap-2 flex-wrap small text-muted mb-2">
                                            <span class="mono">{{ $rule->match_type }}: {{ $rule->keyword ?: '(any)' }}</span>
                                            <span>•</span>
                                            <span class="mono">{{ $rule->state ?: '*' }} → {{ $rule->next_state ?: '-' }}</span>
                                            @if($rule->action)
                                                <span>•</span>
                                                <span class="badge-soft badge-primary-soft">{{ $rule->action }}</span>
                                            @endif
                                        </div>
                                        @if($rule->reply_text)
                                            <p class="mb-0 small">{{ Str::limit($rule->reply_text, 120) }}</p>
                                        @endif
                                    </div>
                                    <div class="d-flex flex-column gap-1 align-items-end">
                                        <form method="POST" action="{{ route('admin.chatbot-rules.toggle', $rule) }}" class="d-inline">@csrf
                                            <button type="submit" class="btn btn-sm {{ $rule->is_active ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                                {{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                            </button>
                                        </form>
                                        <div class="d-flex gap-1">
                                            <button class="btn btn-sm btn-ghost" data-bs-toggle="modal" data-bs-target="#editRule{{ $rule->id }}">Edit</button>
                                            <form method="POST" action="{{ route('admin.chatbot-rules.destroy', $rule) }}" class="d-inline" onsubmit="return confirm('Hapus aturan ini?')">@csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted py-5" id="ruleEmpty">
                                <p class="mb-0">Belum ada aturan chatbot. Klik "Tambah Aturan" untuk membuat.</p>
                            </div>
                        @endforelse
                        <div class="text-center text-muted py-4 d-none" id="ruleNoMatch">
                            <p class="mb-0">Tidak ada aturan yang cocok dengan filter/pencarian.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="pengaduan" class="page-section">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <div class="eyebrow">Layanan masyarakat</div>
                        <h2 class="display-5 mb-0">Laporan, Saran & Survey Kepuasan</h2>
                    </div>
                </div>
                <div class="filter-tabs d-flex gap-2 mb-3">
                    <button class="btn btn-ghost active" data-filter="semua">Semua</button>
                    <button class="btn btn-ghost" data-filter="gangguan">Gangguan</button>
                    <button class="btn btn-ghost" data-filter="saran">Saran</button>
                    <button class="btn btn-ghost" data-filter="survey">Survey</button>
                </div>
                <div class="card-enterprise p-4">
                    <div class="table-responsive">
                        <table class="table align-middle" id="pengaduanTable">
                            <thead>
                                <tr>
                                    <th>Tanggal</th>
                                    <th>Jenis</th>
                                    <th>Nama</th>
                                    <th>No WhatsApp</th>
                                    <th>No Kamar</th>
                                    <th>Isi</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($pengaduans as $pengaduan)
                                    <tr data-jenis="{{ $pengaduan->jenis }}">
                                        <td class="small">{{ $pengaduan->created_at->format('d M Y H:i') }}</td>
                                        <td>
                                            @if ($pengaduan->jenis === 'saran')
                                                <span class="badge bg-info">Saran</span>
                                            @elseif($pengaduan->jenis === 'survey')
                                            <span class="badge bg-primary">Survey</span>@else<span
                                                    class="badge bg-warning text-dark">Gangguan</span>
                                            @endif
                                        </td>
                                        <td>{{ $pengaduan->nama ?: '-' }}</td>
                                        <td class="mono small">{{ $pengaduan->phone_number ?: '-' }}</td>
                                        <td>{{ $pengaduan->nomor_kamar ?: '-' }}</td>
                                        <td class="small">{{ $pengaduan->isi }}</td>
                                        <td>
                                            @if ($pengaduan->rating)
                                                <span class="stars">{{ str_repeat('⭐', $pengaduan->rating) }}</span>
                                                <span
                                                class="small text-muted">({{ $pengaduan->rating }}/5)</span>@else<span
                                                    class="text-muted">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <form method="POST"
                                                action="{{ route('admin.pengaduan.update', $pengaduan) }}"
                                                class="d-inline">@csrf @method('PATCH')<select
                                                    class="form-select form-select-sm" name="status"
                                                    onchange="this.form.submit()">
                                                    @foreach (['baru' => 'Baru', 'diproses' => 'Diproses', 'selesai' => 'Selesai'] as $value => $label)
                                                        <option value="{{ $value }}"
                                                            @selected($pengaduan->status === $value)>{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <form method="POST"
                                                action="{{ route('admin.pengaduan.destroy', $pengaduan) }}"
                                                onsubmit="return confirm('Hapus data ini?')">@csrf
                                                @method('DELETE')<button class="btn btn-sm btn-outline-danger"
                                                    type="submit">Hapus</button></form>
                                        </td>
                                </tr>@empty<tr>
                                        <td colspan="9" class="text-center text-muted py-4">Belum ada laporan,
                                            saran, atau survey.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="whatsapp" class="page-section">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
                    <div>
                        <div class="eyebrow">WhatsApp Chat realtime</div>
                        <h2 class="display-5 mb-0">Klik nomor untuk membalas tanpa input nomor manual.</h2>
                    </div><button class="btn btn-ghost" id="copyWebhook">Salin Webhook URL</button>
                </div>
                <div class="chat-layout">
                    <div class="card-enterprise p-3">
                        <h3 class="h4 mb-3">Session chat</h3>
                        <div id="chatSessions" class="chat-list"></div>
                    </div>
                    <div class="card-enterprise">
                        <div class="p-3 border-bottom">
                            <h3 class="mb-1">Log pesan</h3>
                            <div id="selectedPhoneLabel" class="text-muted small">Pilih nomor di kiri untuk melihat
                                dan membalas chat.</div>
                        </div>
                        <div id="chatMessages" class="chat-body"></div>
                        <form id="sendChatForm" class="p-3 border-top"><input type="hidden" name="phone_number"
                                id="selectedPhoneInput">
                            <div class="row g-2">
                                <div class="col-md-10"><input class="form-control" name="message"
                                        placeholder="Tulis balasan manual" required disabled></div>
                                <div class="col-md-2"><button class="btn btn-primary-enterprise w-100" type="submit"
                                        disabled>Kirim</button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </section>

            <section id="password" class="page-section">
                <div class="mb-4">
                    <div class="eyebrow">Keamanan akun</div>
                    <h2 class="display-5 mb-0">Ganti password admin.</h2>
                </div>
                <div class="card-enterprise p-4" style="max-width: 520px;">
                    <form method="POST" action="{{ route('admin.password.update') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password saat ini</label>
                            <input type="password" class="form-control" name="current_password" required autocomplete="current-password">
                            @error('current_password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Password baru</label>
                            <input type="password" class="form-control" name="password" required minlength="6" autocomplete="new-password">
                            @error('password') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Konfirmasi password baru</label>
                            <input type="password" class="form-control" name="password_confirmation" required minlength="6" autocomplete="new-password">
                        </div>
                        <button type="submit" class="btn btn-primary-enterprise">Simpan Password Baru</button>
                    </form>
                </div>
            </section>
        </main>
    </div>

    @php
        $reservationStatusOptions = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'];
        $paymentStatusOptions = ['unpaid' => 'Belum dibayar', 'partial' => 'DP / sebagian', 'paid' => 'Lunas'];
        $ruleActionOptions = [
            '' => 'Balasan teks biasa',
            'main_menu' => 'Kirim menu utama',
            'check_availability' => 'Cek ketersediaan tanggal',
            'list_kamar' => 'Tampilkan daftar kamar (DB)',
            'pilih_kamar' => 'Detail kamar terpilih (DB)',
            'simpan_reservasi' => 'Simpan reservasi (DB)',
            'bayar_pilihan' => 'Tampilkan pilihan bayar',
            'bayar_qris' => 'Kirim QRIS e-Retribusi',
            'bayar_transfer' => 'Kirim info transfer bank',
            'input_nomor_kamar_gangguan' => 'Input nomor kamar (gangguan)',
            'simpan_laporan' => 'Simpan laporan gangguan (DB)',
            'simpan_saran' => 'Simpan saran (DB)',
            'input_rating_survey' => 'Input rating survey (1-5)',
            'simpan_survey' => 'Simpan survey kepuasan (DB)',
            'cek_booking' => 'Cek kode booking (DB)',
            'selesai' => 'Balasan + tombol Menu Utama',
        ];
    @endphp

    <div class="modal fade" id="roomModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" action="{{ route('admin.kamar.store') }}" enctype="multipart/form-data"
                class="modal-content">@csrf<div class="modal-header">
                    <h2 class="modal-title h4">Tambah Kamar</h2><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-bold">Jenis Kelas / Nama Kamar <span class="text-danger">*</span></label><input
                                class="form-control" name="jenis_kelas" required placeholder="Contoh: Kamar 1 / Ruang Kelas Kecil"></div>
                        <div class="col-md-6"><label class="form-label fw-bold">Harga/malam <span class="text-danger">*</span></label><input
                                type="number" min="0" class="form-control" name="harga_per_malam" required>
                        </div>
                        <div class="col-md-6"><label class="form-label fw-bold">Total Unit</label><input
                                type="number" min="1" class="form-control" name="kuota_total" value="1">
                        </div>
                        <div class="col-md-6"><label class="form-label fw-bold">Tersedia</label><input
                                type="number" min="1" class="form-control" name="stok_total" value="1">
                        </div>
                        <div class="col-12"><label class="form-label fw-bold">Keterangan / Fasilitas</label>
                            <textarea class="form-control" name="fasilitas" rows="2" placeholder="Contoh: AC, kamar mandi dalam, TV"></textarea>
                        </div>
                        <div class="col-12"><label class="form-label fw-bold">Foto kamar</label>
                            <div id="fotoContainerBaru" class="d-flex flex-column gap-2"><input type="file"
                                    accept="image/*" class="form-control" name="foto[]" multiple></div><button
                                type="button" class="btn btn-sm btn-outline-secondary mt-2"
                                onclick="addFotoInput('fotoContainerBaru')">+ Tambah Foto Lainnya</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-ghost"
                        data-bs-dismiss="modal">Batal</button><button class="btn btn-primary-enterprise"
                        type="submit">Simpan Kamar</button></div>
            </form>
        </div>
    </div>

    @foreach ($kamars as $kamar)
        <div class="modal fade" id="editRoom{{ $kamar->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form method="POST" action="{{ route('admin.kamar.update', $kamar) }}"
                    enctype="multipart/form-data" class="modal-content">@csrf @method('PATCH')<div
                        class="modal-header">
                        <h2 class="modal-title h4">Edit Kamar</h2><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label fw-bold">Jenis Kelas / Nama Kamar <span class="text-danger">*</span></label><input
                                    class="form-control" name="jenis_kelas" value="{{ $kamar->jenis_kelas }}" required></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Harga/malam <span class="text-danger">*</span></label><input
                                    type="number" min="0" class="form-control" name="harga_per_malam"
                                    value="{{ $kamar->harga_per_malam }}" required></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Total Unit</label><input
                                    type="number" min="1" class="form-control" name="kuota_total"
                                    value="{{ $kamar->kuota_total ?? 1 }}"></div>
                            <div class="col-md-6"><label class="form-label fw-bold">Tersedia</label><input
                                    type="number" min="1" class="form-control" name="stok_total"
                                    value="{{ $kamar->stok_total ?? 1 }}" required></div>
                            <div class="col-12"><label class="form-label fw-bold">Keterangan / Fasilitas</label>
                                <textarea class="form-control" name="fasilitas" rows="2">{{ $kamar->fasilitas }}</textarea>
                            </div>
                            @if ($kamar->fotos->isNotEmpty())
                                <div class="col-12"><label class="form-label fw-bold">Foto saat ini (centang untuk
                                        hapus)</label>
                                    <div class="d-flex flex-wrap">
                                        @foreach ($kamar->fotos as $foto)
                                            <label class="foto-preview"><img
                                                    src="{{ asset('storage/' . $foto->foto_path) }}"
                                                    alt="Foto"><input type="checkbox" class="form-check-input"
                                                    name="hapus_foto[]" value="{{ $foto->id }}"></label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            <div class="col-12">
                                <label class="form-label fw-bold">Tambah foto baru</label>
                                <div id="fotoContainerEdit{{ $kamar->id }}" class="d-flex flex-column gap-2">
                                    <input type="file" accept="image/*" class="form-control" name="foto[]"
                                        multiple></div><button type="button"
                                    class="btn btn-sm btn-outline-secondary mt-2"
                                    onclick="addFotoInput('fotoContainerEdit{{ $kamar->id }}')">+ Tambah Foto
                                    Lainnya</button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-ghost"
                            data-bs-dismiss="modal">Batal</button><button class="btn btn-primary-enterprise"
                            type="submit">Simpan Perubahan</button></div>
                </form>
            </div>
        </div>
    @endforeach

    <div class="modal fade" id="reservationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <form method="POST" action="{{ route('admin.reservasi.store') }}"
                class="modal-content reservation-form">@csrf<div class="modal-header">
                    <h2 class="modal-title h4">Tambah Reservasi</h2><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label fw-bold">Kode</label><input
                                class="form-control bg-light text-muted" value="Otomatis setelah disimpan" readonly>
                        </div>
                        <div class="col-md-4"><label class="form-label fw-bold">Nama pemesan</label><input
                                class="form-control" name="nama_pemesan" required></div>
                        <div class="col-md-4"><label class="form-label fw-bold">No WhatsApp</label><input
                                class="form-control" name="phone_number"></div>
                        <div class="col-12">
                            <div class="form-check"><input type="hidden" name="tipe_penyewa"
                                    value="perorangan"><input class="form-check-input penyewa-toggle" type="checkbox"
                                    name="tipe_penyewa" value="instansi" id="penyewaNew"><label
                                    class="form-check-label fw-bold" for="penyewaNew">Penyewa adalah instansi</label>
                            </div>
                        </div>
                        <div class="col-md-6 instansi-field"><label class="form-label fw-bold">Instansi</label><input
                                class="form-control" name="instansi"></div>
                        <div class="col-md-6 instansi-field"><label class="form-label fw-bold">Kegiatan</label><input
                                class="form-control" name="kegiatan"></div>
                        <div class="col-md-4 single-field"><label class="form-label fw-bold">Tanggal
                                masuk</label><input type="date" class="form-control" name="tanggal_masuk"></div>
                        <div class="col-md-4 single-field"><label class="form-label fw-bold">Tanggal
                                keluar</label><input type="date" class="form-control" name="tanggal_keluar"></div>
                        <div class="col-md-4 instansi-field"><label class="form-label fw-bold">Jumlah
                                peserta</label><input type="number" min="1" class="form-control"
                                name="jumlah_peserta" value="1"></div>
                        <div class="col-md-6 single-field"><label class="form-label fw-bold">Kamar
                                default</label><select class="form-select" name="kamar_id">
                                <option value="">Belum dialokasikan</option>
                                @foreach ($kamars as $kamar)
                                    <option value="{{ $kamar->id }}">{{ $kamar->jenis_kelas }}
                                        (Rp{{ number_format($kamar->harga_per_malam, 0, ',', '.') }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label fw-bold">Status reservasi</label><select
                                class="form-select" name="status">
                                @foreach ($reservationStatusOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3 d-flex align-items-end"><span class="text-muted small">Status pembayaran
                                awal: <strong>Belum dibayar</strong></span></div>
                        <div class="col-12">
                            <div class="form-check"><input class="form-check-input multi-toggle" type="checkbox"
                                    name="multiple_kamar" value="1" id="multiNew"><label
                                    class="form-check-label fw-bold" for="multiNew">Pesan beberapa kamar / beberapa
                                    tanggal</label></div>
                        </div>
                        <div class="col-12 multi-items"><label class="form-label fw-bold">Daftar kamar
                                tambahan</label>
                            <div class="multi-item-list">
                                <div class="row g-2 mb-2 multi-row">
                                    <div class="col-md-4"><select class="form-select" name="items[0][kamar_id]">
                                            <option value="">Pilih kamar</option>
                                            @foreach ($kamars as $kamar)
                                                <option value="{{ $kamar->id }}">{{ $kamar->jenis_kelas }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-3"><input type="date" class="form-control"
                                            name="items[0][tanggal_masuk]"></div>
                                    <div class="col-md-3"><input type="date" class="form-control"
                                            name="items[0][tanggal_keluar]"></div>
                                    <div class="col-md-2"><button
                                            class="btn btn-outline-danger w-100 remove-multi-row"
                                            type="button">Hapus</button></div>
                                </div>
                            </div><button class="btn btn-sm btn-ghost add-multi-row" type="button">Tambah Baris
                                Kamar</button>
                        </div>
                        <div class="col-12"><label class="form-label fw-bold">Catatan</label>
                            <textarea class="form-control" name="catatan" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-ghost"
                        data-bs-dismiss="modal">Batal</button><button class="btn btn-primary-enterprise"
                        type="submit">Simpan Reservasi</button></div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="ruleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form method="POST" action="{{ route('admin.chatbot-rules.store') }}" class="modal-content">@csrf<div
                    class="modal-header">
                    <h2 class="modal-title h4">Tambah Aturan Chatbot</h2><button type="button" class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-bold">Nama aturan</label><input
                                class="form-control" name="nama" required></div>
                        <div class="col-md-3"><label class="form-label fw-bold">Prioritas</label><input
                                type="number" min="1" class="form-control" name="priority" value="10"
                                required></div>
                        <div class="col-md-3"><label class="form-label fw-bold">Aktif</label><select
                                class="form-select" name="is_active">
                                <option value="1">Aktif</option>
                                <option value="0">Nonaktif</option>
                            </select></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Match</label><select
                                class="form-select" name="match_type">
                                <option value="exact">Sama persis</option>
                                <option value="contains">Mengandung</option>
                                <option value="starts_with">Diawali</option>
                                <option value="any">Apa saja (fallback)</option>
                            </select></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Keyword</label><input
                                class="form-control" name="keyword" required></div>
                        <div class="col-md-4"><label class="form-label fw-bold">State berlaku</label><input
                                class="form-control" name="state" placeholder="Kosong = semua"></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Next state</label><input
                                class="form-control" name="next_state" placeholder="Opsional"></div>
                        <div class="col-md-4"><label class="form-label fw-bold">Action khusus</label><select
                                class="form-select" name="action">
                                @foreach ($ruleActionOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label fw-bold">Balasan</label>
                            <textarea class="form-control" name="reply_text" rows="5" placeholder="Kosongkan jika memakai action khusus"></textarea>
                        </div>
                        <hr class="my-2">
                        <div class="col-12"><small class="text-muted fw-bold d-block mb-2">Menu WhatsApp (opsional — hanya untuk aturan dengan state=main_menu)</small></div>
                        <div class="col-md-4"><label class="form-label">Label menu</label><input class="form-control" name="menu_label" placeholder="Tampilan di list WA"></div>
                        <div class="col-md-4"><label class="form-label">Deskripsi menu</label><input class="form-control" name="menu_description" placeholder="Subtitle di list WA"></div>
                        <div class="col-md-4"><label class="form-label">Urutan menu</label><input type="number" min="0" class="form-control" name="menu_order" value="0"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-ghost"
                        data-bs-dismiss="modal">Batal</button><button class="btn btn-primary-enterprise"
                        type="submit">Simpan Aturan</button></div>
            </form>
        </div>
    </div>

    @foreach ($rules as $rule)
        <div class="modal fade" id="editRule{{ $rule->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <form method="POST" action="{{ route('admin.chatbot-rules.update', $rule) }}"
                    class="modal-content">@csrf @method('PATCH')<div class="modal-header">
                        <h2 class="modal-title h4">Edit Aturan Chatbot</h2><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label fw-bold">Nama aturan</label><input
                                    class="form-control" name="nama" value="{{ $rule->nama }}" required></div>
                            <div class="col-md-3"><label class="form-label fw-bold">Prioritas</label><input
                                    type="number" min="1" class="form-control" name="priority"
                                    value="{{ $rule->priority }}" required></div>
                            <div class="col-md-3"><label class="form-label fw-bold">Aktif</label><select
                                    class="form-select" name="is_active">
                                    <option value="1" @selected($rule->is_active)>Aktif</option>
                                    <option value="0" @selected(!$rule->is_active)>Nonaktif</option>
                                </select></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Match</label><select
                                    class="form-select" name="match_type">
                                    @foreach (['exact' => 'Sama persis', 'contains' => 'Mengandung', 'starts_with' => 'Diawali', 'any' => 'Apa saja (fallback)'] as $value => $label)
                                        <option value="{{ $value }}" @selected($rule->match_type === $value)>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4"><label class="form-label fw-bold">Keyword</label><input
                                    class="form-control" name="keyword" value="{{ $rule->keyword }}"></div>
                            <div class="col-md-4"><label class="form-label fw-bold">State berlaku</label><input
                                    class="form-control" name="state" value="{{ $rule->state }}"></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Next state</label><input
                                    class="form-control" name="next_state" value="{{ $rule->next_state }}"></div>
                            <div class="col-md-4"><label class="form-label fw-bold">Action khusus</label><select
                                    class="form-select" name="action">
                                    @foreach ($ruleActionOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($rule->action === ($value ?: null))>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12"><label class="form-label fw-bold">Balasan</label>
                                <textarea class="form-control" name="reply_text" rows="5" placeholder="Kosongkan jika memakai action khusus">{{ $rule->reply_text }}</textarea>
                            </div>
                            <hr class="my-2">
                            <div class="col-12"><small class="text-muted fw-bold d-block mb-2">Menu WhatsApp (opsional — hanya untuk aturan dengan state=main_menu)</small></div>
                            <div class="col-md-4"><label class="form-label">Label menu</label><input class="form-control" name="menu_label" value="{{ $rule->menu_label }}"></div>
                            <div class="col-md-4"><label class="form-label">Deskripsi menu</label><input class="form-control" name="menu_description" value="{{ $rule->menu_description }}"></div>
                            <div class="col-md-4"><label class="form-label">Urutan menu</label><input type="number" min="0" class="form-control" name="menu_order" value="{{ $rule->menu_order }}"></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-ghost"
                            data-bs-dismiss="modal">Batal</button><button class="btn btn-primary-enterprise"
                            type="submit">Simpan Perubahan</button></div>
                </form>
            </div>
        </div>
    @endforeach

    @foreach ($reservasis as $reservasi)
        @php $itemRows = $reservasi->items->isNotEmpty() ? $reservasi->items : collect([(object)['kamar_id' => $reservasi->kamar_id, 'tanggal_masuk' => $reservasi->tanggal_masuk, 'tanggal_keluar' => $reservasi->tanggal_keluar]]); @endphp
        <div class="modal fade" id="editReservation{{ $reservasi->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered">
                <form method="POST" action="{{ route('admin.reservasi.update', $reservasi) }}"
                    class="modal-content reservation-form">@csrf @method('PATCH')<div class="modal-header">
                        <h2 class="modal-title h4">Edit Reservasi</h2><button type="button" class="btn-close"
                            data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-4"><label class="form-label fw-bold">Kode</label><input
                                    class="form-control bg-light text-muted" value="{{ $reservasi->kode }}" readonly>
                            </div>
                            <div class="col-md-4"><label class="form-label fw-bold">Nama pemesan</label><input
                                    class="form-control" name="nama_pemesan" value="{{ $reservasi->nama_pemesan }}"
                                    required></div>
                            <div class="col-md-4"><label class="form-label fw-bold">No WhatsApp</label><input
                                    class="form-control" name="phone_number"
                                    value="{{ $reservasi->phone_number }}"></div>
                            <div class="col-12">
                                <div class="form-check"><input type="hidden" name="tipe_penyewa"
                                        value="perorangan"><input class="form-check-input penyewa-toggle"
                                        type="checkbox" name="tipe_penyewa" value="instansi"
                                        id="penyewa{{ $reservasi->id }}" @checked($reservasi->tipe_penyewa === 'instansi')><label
                                        class="form-check-label fw-bold" for="penyewa{{ $reservasi->id }}">Penyewa
                                        adalah instansi</label></div>
                            </div>
                            <div class="col-md-6 instansi-field"><label
                                    class="form-label fw-bold">Instansi</label><input class="form-control"
                                    name="instansi" value="{{ $reservasi->instansi }}"></div>
                            <div class="col-md-6 instansi-field"><label
                                    class="form-label fw-bold">Kegiatan</label><input class="form-control"
                                    name="kegiatan" value="{{ $reservasi->kegiatan }}"></div>
                            <div class="col-md-4 single-field"><label class="form-label fw-bold">Tanggal
                                    masuk</label><input type="date" class="form-control" name="tanggal_masuk"
                                    value="{{ optional($reservasi->tanggal_masuk)->format('Y-m-d') }}"></div>
                            <div class="col-md-4 single-field"><label class="form-label fw-bold">Tanggal
                                    keluar</label><input type="date" class="form-control" name="tanggal_keluar"
                                    value="{{ optional($reservasi->tanggal_keluar)->format('Y-m-d') }}"></div>
                            <div class="col-md-4 instansi-field"><label class="form-label fw-bold">Jumlah
                                    peserta</label><input type="number" min="1" class="form-control"
                                    name="jumlah_peserta" value="{{ $reservasi->jumlah_peserta }}"></div>
                            <div class="col-md-6 single-field"><label class="form-label fw-bold">Kamar
                                    default</label><select class="form-select" name="kamar_id">
                                    <option value="">Belum dialokasikan</option>
                                    @foreach ($kamars as $kamar)
                                        <option value="{{ $kamar->id }}">
                                            {{ $kamar->jenis_kelas }}
                                            (Rp{{ number_format($kamar->harga_per_malam, 0, ',', '.') }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label fw-bold">Status reservasi</label><select
                                    class="form-select" name="status">
                                    @foreach ($reservationStatusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($reservasi->status === $value)>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label fw-bold">Status payment</label><select
                                    class="form-select" name="payment_status">
                                    @foreach ($paymentStatusOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($reservasi->payment_status === $value)>
                                            {{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label fw-bold">Total billing</label><input
                                    class="form-control bg-light"
                                    value="Rp{{ number_format($reservasi->total_harga, 0, ',', '.') }}" readonly>
                            </div>
                            <div class="col-12">
                                <div class="form-check"><input class="form-check-input multi-toggle" type="checkbox"
                                        name="multiple_kamar" value="1" id="multi{{ $reservasi->id }}"
                                        @checked($reservasi->multiple_kamar)><label class="form-check-label fw-bold"
                                        for="multi{{ $reservasi->id }}">Pesan beberapa kamar / beberapa
                                        tanggal</label></div>
                            </div>
                            <div class="col-12 multi-items"><label class="form-label fw-bold">Daftar kamar</label>
                                <div class="multi-item-list">
                                    @foreach ($itemRows as $index => $item)
                                        <div class="row g-2 mb-2 multi-row">
                                            <div class="col-md-4"><select class="form-select"
                                                    name="items[{{ $index }}][kamar_id]">
                                                    <option value="">Pilih kamar</option>
                                                    @foreach ($kamars as $kamar)
                                                        <option value="{{ $kamar->id }}"
                                                            @selected(($item->jenis_kelas ?? null) === $kamar->jenis_kelas || (isset($item->kamar_id) && (string) $item->kamar_id === (string) $kamar->id))>{{ $kamar->jenis_kelas }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-md-3"><input type="date" class="form-control"
                                                    name="items[{{ $index }}][tanggal_masuk]"
                                                    value="{{ optional($item->tanggal_masuk)->format('Y-m-d') }}">
                                            </div>
                                            <div class="col-md-3"><input type="date" class="form-control"
                                                    name="items[{{ $index }}][tanggal_keluar]"
                                                    value="{{ optional($item->tanggal_keluar)->format('Y-m-d') }}">
                                            </div>
                                            <div class="col-md-2"><button
                                                    class="btn btn-outline-danger w-100 remove-multi-row"
                                                    type="button">Hapus</button></div>
                                        </div>
                                    @endforeach
                                </div>
                                <button class="btn btn-sm btn-ghost add-multi-row" type="button">Tambah Baris
                                    Kamar</button>
                            </div>
                            <div class="col-12"><label class="form-label fw-bold">Catatan</label>
                                <textarea class="form-control" name="catatan" rows="3">{{ $reservasi->catatan }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-ghost"
                            data-bs-dismiss="modal">Batal</button><button class="btn btn-primary-enterprise"
                            type="submit">Simpan Perubahan</button></div>
                </form>
            </div>
        </div>
    @endforeach

    <template id="multiRoomTemplate">
        <div class="row g-2 mb-2 multi-row">
            <div class="col-md-4"><select class="form-select" data-name="kamar_id">
                    <option value="">Pilih kamar</option>
                    @foreach ($kamars as $kamar)
                        <option value="{{ $kamar->id }}">{{ $kamar->jenis_kelas }}</option>
                    @endforeach
                </select></div>
            <div class="col-md-3"><input type="date" class="form-control" data-name="tanggal_masuk"></div>
            <div class="col-md-3"><input type="date" class="form-control" data-name="tanggal_keluar"></div>
            <div class="col-md-2"><button class="btn btn-outline-danger w-100 remove-multi-row"
                    type="button">Hapus</button></div>
        </div>
    </template>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const initialSection = new URLSearchParams(location.search).get('section') || 'dashboard';

        function activateSection(name) {
            document.querySelectorAll('[data-section]').forEach((item) => item.classList.toggle('active', item.dataset
                .section === name));
            document.querySelectorAll('.page-section').forEach((section) => section.classList.toggle('active', section
                .id === name));
        }
        document.querySelectorAll('[data-section]').forEach((button) => button.addEventListener('click', () => {
            activateSection(button.dataset.section);
            history.replaceState(null, '', `?section=${button.dataset.section}`);
        }));
        if (document.getElementById(initialSection)) {
            activateSection(initialSection)
        }
        document.getElementById('copyWebhook')?.addEventListener('click', () => navigator.clipboard.writeText(
            `${location.origin}/api/webhooks/kirimchat`));
        const csrf = document.querySelector('meta[name="csrf-token"]').content;

        function escapeHtml(value) {
            return String(value ?? '').replace(/[&<>'"]/g, (char) => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#039;',
                '"': '&quot;'
            } [char]));
        }

        function updateMultiForm(form) {
            const enabled = form.querySelector('.multi-toggle')?.checked;
            form.querySelector('.multi-items')?.classList.toggle('active', enabled);
            form.querySelectorAll('.single-field').forEach((field) => field.classList.toggle('d-none', enabled));
        }

        function updatePenyewaForm(form) {
            const isInstansi = form.querySelector('.penyewa-toggle')?.checked;
            form.querySelectorAll('.instansi-field').forEach((field) => field.classList.toggle('d-none', !isInstansi));
        }

        function reindexRows(list) {
            list.querySelectorAll('.multi-row').forEach((row, index) => row.querySelectorAll('[data-name]').forEach((
                input) => input.name = `items[${index}][${input.dataset.name}]`));
        }
        document.querySelectorAll('.reservation-form').forEach((form) => {
            updateMultiForm(form);
            updatePenyewaForm(form);
            form.querySelector('.multi-toggle')?.addEventListener('change', () => updateMultiForm(form));
            form.querySelector('.penyewa-toggle')?.addEventListener('change', () => updatePenyewaForm(form));
            form.addEventListener('click', (event) => {
                if (event.target.classList.contains('add-multi-row')) {
                    const list = form.querySelector('.multi-item-list');
                    const clone = document.getElementById('multiRoomTemplate').content.cloneNode(true);
                    list.appendChild(clone);
                    reindexRows(list)
                }
                if (event.target.classList.contains('remove-multi-row')) {
                    const list = form.querySelector('.multi-item-list');
                    if (list.querySelectorAll('.multi-row').length > 1) {
                        event.target.closest('.multi-row').remove();
                        reindexRows(list)
                    }
                }
            });
        });

        let selectedPhone = null;
        async function refreshChat() {
            const url = new URL('{{ url('/admin/whatsapp/messages') }}', window.location.origin);
            if (selectedPhone) {
                url.searchParams.set('phone_number', selectedPhone)
            }
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json'
                }
            });
            if (!response.ok) return;
            const data = await response.json();
            document.getElementById('chatSessions').innerHTML = data.sessions.length ? data.sessions.map((session) =>
                `<button type="button" class="chat-item ${session.phone_number===selectedPhone?'active':''}" data-phone="${escapeHtml(session.phone_number)}"><strong>${escapeHtml(session.phone_number)}</strong><div class="small text-muted">${escapeHtml(session.state)} - ${escapeHtml(session.last_message_at || '-')}</div></button>`
                ).join('') : '<div class="empty-state">Belum ada session.</div>';
            document.getElementById('chatMessages').innerHTML = data.messages.length ? data.messages.map((message) =>
                `<div class="bubble ${message.direction==='outbound'?'outbound':''}"><div class="small mono">${escapeHtml(message.phone_number)} - ${escapeHtml(message.direction)} - ${escapeHtml(message.created_at)}</div><div>${escapeHtml(message.message_text)}</div></div>`
                ).join('') : (selectedPhone ? '<div class="empty-state">Belum ada pesan untuk nomor ini.</div>' :
                '<div class="empty-state">Pilih nomor session untuk melihat pesan.</div>');
            const body = document.getElementById('chatMessages');
            body.scrollTop = body.scrollHeight;
        }
        document.getElementById('chatSessions')?.addEventListener('click', (event) => {
            const item = event.target.closest('[data-phone]');
            if (!item) return;
            selectedPhone = item.dataset.phone;
            document.getElementById('selectedPhoneInput').value = selectedPhone;
            document.getElementById('selectedPhoneLabel').textContent = `Membalas nomor ${selectedPhone}`;
            document.querySelector('#sendChatForm input[name="message"]').disabled = false;
            document.querySelector('#sendChatForm button[type="submit"]').disabled = false;
            refreshChat();
        });
        document.getElementById('sendChatForm')?.addEventListener('submit', async (event) => {
            event.preventDefault();
            if (!selectedPhone) return;
            const form = event.currentTarget;
            const response = await fetch('{{ url('/admin/whatsapp/send') }}', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrf,
                    Accept: 'application/json'
                },
                body: new FormData(form)
            });
            if (response.ok) {
                form.querySelector('input[name="message"]').value = '';
                refreshChat();
            }
        });
        refreshChat();
        setInterval(refreshChat, 5000);

        // ═══ Pengaduan filter tabs ═══
        document.querySelectorAll('.filter-tabs [data-filter]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.filter-tabs [data-filter]').forEach(b => b.classList.remove(
                    'active'));
                btn.classList.add('active');
                const filter = btn.dataset.filter;
                document.querySelectorAll('#pengaduanTable tbody tr[data-jenis]').forEach(row => {
                    row.style.display = (filter === 'semua' || row.dataset.jenis === filter) ? '' :
                        'none';
                });
            });
        });

        function addFotoInput(containerId) {
            const container = document.getElementById(containerId);
            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex gap-2 align-items-center';

            const input = document.createElement('input');
            input.type = 'file';
            input.accept = 'image/*';
            input.className = 'form-control';
            input.name = 'foto[]';
            input.multiple = true;

            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn btn-outline-danger';
            removeBtn.innerText = 'Hapus';
            removeBtn.onclick = () => wrapper.remove();

            wrapper.appendChild(input);
            wrapper.appendChild(removeBtn);
            container.appendChild(wrapper);
        }

        // ═══ Rules: filter + search ═══
        (function () {
            const filterBtns = document.querySelectorAll('#ruleFilters [data-rule-filter]');
            const searchInput = document.getElementById('ruleSearch');
            const cards = document.querySelectorAll('#ruleList .rule-card');
            const emptyMsg = document.getElementById('ruleEmpty');
            const noMatchMsg = document.getElementById('ruleNoMatch');
            let currentFilter = 'all';
            let currentSearch = '';

            function applyFilter() {
                let visible = 0;
                cards.forEach(card => {
                    const isActive = card.dataset.ruleActive === '1';
                    const filterMatch = currentFilter === 'all' ||
                        (currentFilter === 'active' && isActive) ||
                        (currentFilter === 'inactive' && !isActive);
                    const haystack = card.dataset.ruleName + ' ' + card.dataset.ruleKeyword + ' ' + card.dataset.ruleReply;
                    const searchMatch = currentSearch === '' || haystack.includes(currentSearch);
                    const show = filterMatch && searchMatch;
                    card.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                if (emptyMsg) emptyMsg.classList.toggle('d-none', cards.length > 0);
                if (noMatchMsg) noMatchMsg.classList.toggle('d-none', visible > 0 || cards.length === 0);
            }

            filterBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    filterBtns.forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                    currentFilter = btn.dataset.ruleFilter;
                    applyFilter();
                });
            });

            if (searchInput) {
                searchInput.addEventListener('input', () => {
                    currentSearch = searchInput.value.toLowerCase().trim();
                    applyFilter();
                });
            }
        })();
    </script>
</body>

</html>
