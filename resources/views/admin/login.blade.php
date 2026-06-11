<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin - Asrama BKPP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Oswald:wght@600;700&family=Ubuntu:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root{--primary:#072C2C;--secondary:#FF5F03;--surface:#EDEADE;--border:rgba(7,44,44,.16);--font-display:"Oswald",system-ui,sans-serif;--font-body:"Ubuntu",system-ui,sans-serif}body{min-height:100vh;margin:0;font-family:var(--font-body);background:linear-gradient(135deg,var(--primary),#0f4747);display:grid;place-items:center;color:#111827}.login-card{width:min(440px,92vw);background:var(--surface);border:1px solid var(--border);border-radius:28px;padding:34px;box-shadow:0 28px 70px rgba(0,0,0,.26)}h1{font-family:var(--font-display);color:var(--primary)}.brand-mark{width:58px;height:58px;border-radius:18px;background:var(--primary);color:#fff;display:grid;place-items:center;font-family:var(--font-display);font-weight:800}.btn-primary-enterprise{--bs-btn-bg:var(--primary);--bs-btn-border-color:var(--primary);--bs-btn-hover-bg:#0B3A3A;--bs-btn-hover-border-color:#0B3A3A;--bs-btn-color:#fff;border-radius:999px;font-weight:800;padding:.85rem 1rem}.form-control{border-radius:16px;padding:.85rem 1rem}.hint{color:#667085;font-size:.95rem}.accent{color:var(--secondary)}
    </style>
</head>
<body>
    <main class="login-card">
        <div class="d-flex align-items-center gap-3 mb-4">
            <span class="brand-mark">BKPP</span>
            <div>
                <div class="text-uppercase fw-bold accent small">Admin Asrama</div>
                <h1 class="h2 mb-0">Masuk Dashboard</h1>
            </div>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.login.store') }}" class="d-grid gap-3">
            @csrf
            <div>
                <label class="form-label fw-bold" for="email">Username</label>
                <input id="email" class="form-control" name="email" value="{{ old('email', 'admin') }}" autocomplete="username" required autofocus>
            </div>
            <div>
                <label class="form-label fw-bold" for="password">Password</label>
                <input id="password" type="password" class="form-control" name="password" autocomplete="current-password" required>
            </div>
            <button class="btn btn-primary-enterprise" type="submit">Login</button>
        </form>
        <p class="hint mt-4 mb-0">Akun awal: <strong>admin</strong> / <strong>semarang1</strong></p>
    </main>
</body>
</html>
