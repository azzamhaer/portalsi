<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Gagal — Portal SI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#b44700">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%23b44700'/%3E%3Cpath d='M16 5l9 4v6c0 5-3.8 9.4-9 11-5.2-1.6-9-6-9-11V9l9-4z' fill='%23fff0da'/%3E%3C/svg%3E">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: #24170d;
      min-height: 100vh; display: grid; place-items: center; padding: 24px;
      background: radial-gradient(1200px 600px at 50% -10%, #fff6ea, #fbf4ec 60%, #f4ece2);
    }
    .card {
      background: #fff; width: 100%; max-width: 430px; padding: 40px 30px; text-align: center;
      border-radius: 20px; box-shadow: 0 24px 60px rgba(90,50,10,0.14); border: 1px solid #f0e6d8;
    }
    .badge { width: 66px; height: 66px; margin: 0 auto 16px; display: grid; place-items: center;
      border-radius: 20px; background: #fdecee; color: #b3283a; }
    .badge svg { width: 32px; height: 32px; }
    h1 { font-size: 1.5rem; letter-spacing: -0.02em; margin-bottom: 8px; }
    p { color: #7a6a5b; font-size: 0.95rem; line-height: 1.55; margin-bottom: 22px; }
    .actions { display: grid; gap: 10px; justify-items: center; }
    a.cta { display: inline-grid; place-items: center; min-width: 220px; min-height: 48px; padding: 0 20px;
      background: #b44700; color: #fff; border-radius: 12px; font-weight: 750; text-decoration: none; }
    a.cta:hover { background: #8f3500; }
    a.ghost { color: #7a6a5b; font-size: 0.9rem; font-weight: 600; text-decoration: none; }
  </style>
</head>
<body>
  <div class="card">
    <div class="badge"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.3 3.9l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.7-3l-8-14a2 2 0 0 0-3.4 0z"/></svg></div>
    <h1>Tautan tidak berlaku</h1>
    <p>{{ $error ?? 'Tautan reset ini tidak valid atau sudah kedaluwarsa. Silakan minta tautan baru untuk melanjutkan.' }}</p>
    <div class="actions">
      <a class="cta" href="https://app.portalsi.com/forgot-password">Minta tautan baru</a>
      <a class="ghost" href="https://app.portalsi.com/login">Kembali ke masuk</a>
    </div>
  </div>
</body>
</html>
