<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>My Notes — MinecraftChat AI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/css/app.css" />
  <link rel="stylesheet" href="/css/admin.css" />
</head>
<body class="admin-body">

<header>
  <span class="mc-icon">⛏</span>
  <h1>Minecraft Chat AI</h1>
  <nav class="header-nav">
    <a href="/" class="nav-btn pixel">← BACK</a>
    <span class="nav-user pixel">{{ auth()->user()->name }}</span>
    <form method="POST" action="/logout" style="display:inline">
      @csrf
      <button type="submit" class="nav-btn pixel">LOGOUT</button>
    </form>
  </nav>
</header>

<div class="admin-main">

  @if(session('success'))
    <div class="flash flash-ok">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="flash flash-err">{{ session('error') }}</div>
  @endif

  @yield('content')

</div>

</body>
</html>
