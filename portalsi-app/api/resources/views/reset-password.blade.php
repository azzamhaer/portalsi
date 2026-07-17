<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Reset Kata Sandi — Portal SI</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#b44700">
  <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='7' fill='%23b44700'/%3E%3Cpath d='M16 5l9 4v6c0 5-3.8 9.4-9 11-5.2-1.6-9-6-9-11V9l9-4z' fill='%23fff0da'/%3E%3C/svg%3E">
  <style>
    :root {
      --primary: #b44700;
      --primary-strong: #8f3500;
      --primary-soft: #fff0da;
      --text: #24170d;
      --muted: #7a6a5b;
      --border: #e7d8c6;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
      color: var(--text);
      min-height: 100vh;
      display: grid;
      place-items: center;
      padding: 24px;
      background: radial-gradient(1200px 600px at 50% -10%, #fff6ea, #fbf4ec 60%, #f4ece2);
    }
    .card {
      background: #fff;
      width: 100%;
      max-width: 430px;
      padding: 34px 30px 30px;
      border-radius: 20px;
      box-shadow: 0 24px 60px rgba(90, 50, 10, 0.14);
      border: 1px solid #f0e6d8;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 11px;
      margin-bottom: 20px;
    }
    .brand .mark {
      width: 44px; height: 44px;
      display: grid; place-items: center;
      border-radius: 12px;
      background: var(--primary);
      color: var(--primary-soft);
    }
    .brand .mark svg { width: 24px; height: 24px; }
    .brand b { font-size: 15px; display: block; }
    .brand span { font-size: 12px; color: var(--muted); }
    h1 { font-size: 1.5rem; letter-spacing: -0.02em; margin-bottom: 6px; }
    .sub { color: var(--muted); font-size: 0.92rem; margin-bottom: 20px; }
    label { display: block; font-size: 0.78rem; font-weight: 700; margin: 0 0 6px; }
    .field {
      display: flex; align-items: center;
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 0 6px 0 12px;
      margin-bottom: 14px;
      background: #fff;
    }
    .field:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(180,71,0,0.12); }
    .field svg.lead { width: 18px; height: 18px; color: var(--muted); flex: 0 0 auto; }
    .field input {
      flex: 1; border: 0; outline: 0; background: transparent;
      padding: 12px 10px; font-size: 0.95rem; min-width: 0;
    }
    .field .toggle {
      width: 40px; height: 40px; display: grid; place-items: center;
      background: transparent; border: 0; cursor: pointer; color: var(--muted);
    }
    button.submit {
      width: 100%; min-height: 48px; margin-top: 4px;
      background: var(--primary); color: #fff;
      border: 0; border-radius: 12px;
      font-size: 0.95rem; font-weight: 750; cursor: pointer;
      transition: background 140ms ease;
    }
    button.submit:hover:enabled { background: var(--primary-strong); }
    button.submit:disabled { opacity: 0.55; cursor: not-allowed; }
    .message {
      display: none; text-align: left;
      color: #b3283a; background: #fdecee;
      border: 1px solid #f4c9ce; border-radius: 10px;
      padding: 9px 11px; font-size: 0.82rem; margin-bottom: 12px;
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <span class="mark"><svg viewBox="0 0 32 32"><path d="M16 4l10 4.5v6.5c0 5.6-4.2 10.5-10 12.2C10.2 25.5 6 20.6 6 15V8.5L16 4z" fill="currentColor"/></svg></span>
      <div><b>Portal SI</b><span>Pemulihan akun</span></div>
    </div>

    <h1>Buat kata sandi baru</h1>
    <p class="sub">Masukkan kata sandi baru untuk akun kamu.</p>

    <form method="POST" action="{{ url('/submit-reset-password') }}" id="resetForm">
      @csrf
      <input type="hidden" name="token" value="{{ $token }}">
      <input type="hidden" name="email" value="{{ $email }}">

      <div id="errorMessage" class="message">Konfirmasi kata sandi tidak cocok.</div>

      <label for="password">Kata sandi baru</label>
      <div class="field">
        <svg class="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        <input type="password" name="password" id="password" placeholder="Minimal 6 karakter" minlength="6" autocomplete="new-password" required>
        <button type="button" class="toggle" onclick="toggleReveal()" aria-label="Tampilkan/sembunyikan">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
        </button>
      </div>

      <label for="password_confirmation">Ulangi kata sandi</label>
      <div class="field">
        <svg class="lead" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="Ketik ulang kata sandi" minlength="6" autocomplete="new-password" required>
      </div>

      <button type="submit" class="submit" id="submitBtn" disabled>Reset kata sandi</button>
    </form>
  </div>

  <script>
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('password_confirmation');
    const errorMessage = document.getElementById('errorMessage');
    const submitBtn = document.getElementById('submitBtn');

    function validatePasswords() {
      const passVal = password.value;
      const confirmVal = confirmPassword.value;
      if (!passVal || !confirmVal) { submitBtn.disabled = true; errorMessage.style.display = 'none'; return; }
      if (passVal !== confirmVal) { submitBtn.disabled = true; errorMessage.style.display = 'block'; }
      else { submitBtn.disabled = false; errorMessage.style.display = 'none'; }
    }
    function toggleReveal() {
      const t = password.type === 'password' ? 'text' : 'password';
      password.type = t; confirmPassword.type = t;
    }
    password.addEventListener('input', validatePasswords);
    confirmPassword.addEventListener('input', validatePasswords);
  </script>
</body>
</html>
