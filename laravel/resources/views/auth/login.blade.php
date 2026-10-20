<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Login — MinecraftChat AI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/css/app.css" />
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="auth-page">

<header>
  <span class="mc-icon">⛏</span>
  <h1>Minecraft Chat AI</h1>
  <a href="/" class="auth-nav-link pixel">BACK</a>
</header>

<div class="auth-container">
  <div class="auth-box">
    <h2 class="auth-title pixel">LOGIN</h2>

    @if($errors->any())
      <div class="auth-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="/login" class="auth-form">
      @csrf
      <label class="auth-label pixel">EMAIL</label>
      <input type="email" name="email" value="{{ old('email') }}"
             class="auth-input" placeholder="steve@minecraft.net" required autofocus />

      <label class="auth-label pixel">PASSWORD</label>
      <input type="password" name="password" class="auth-input" placeholder="••••••••" required />

      <label class="auth-check">
        <input type="checkbox" name="remember" /> <span class="pixel">REMEMBER ME</span>
      </label>

      <div class="cf-turnstile" data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="dark"></div>

      <button type="submit" class="mc-btn auth-btn">SIGN IN</button>
    </form>

    <p class="auth-switch pixel">No account? <a href="/register">REGISTER</a></p>
  </div>
</div>

</body>
</html>
