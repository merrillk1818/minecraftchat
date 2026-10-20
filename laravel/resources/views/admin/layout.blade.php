<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Admin — MinecraftChat AI</title>
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
  <span class="admin-badge pixel">ADMIN</span>
  <nav class="header-nav">
    <a href="/" class="nav-btn pixel">← SITE</a>
    <span class="nav-user pixel">{{ auth()->user()->name }}</span>
    <form method="POST" action="/logout" style="display:inline">
      @csrf
      <button type="submit" class="nav-btn pixel">LOGOUT</button>
    </form>
  </nav>
</header>

<div id="status-bar">
  <div class="svc"><div class="dot checking" id="dot-api"></div><span>API</span></div>
  <div class="svc"><div class="dot checking" id="dot-groq"></div><span>GROQ</span></div>
  <span id="status-ready" class="checking pixel">Checking...</span>
</div>

<nav class="admin-nav">
  <a href="/admin"           class="admin-nav-btn pixel {{ request()->is('admin') ? 'active' : '' }}">⚡ DASHBOARD</a>
  <a href="/admin/documents" class="admin-nav-btn pixel {{ request()->is('admin/documents*') ? 'active' : '' }}">📄 DOCUMENTS</a>
  <a href="/admin/users"     class="admin-nav-btn pixel {{ request()->is('admin/users*') ? 'active' : '' }}">👤 USERS</a>
  <a href="/admin/settings"  class="admin-nav-btn pixel {{ request()->is('admin/settings*') ? 'active' : '' }}">⚙ SETTINGS</a>
</nav>

<div class="admin-main">

  @if(session('success'))
    <div class="flash flash-ok">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="flash flash-err">{{ session('error') }}</div>
  @endif

  @yield('content')

</div>

<script>
(function () {
  function setDot(id, state) {
    var el = document.getElementById(id);
    if (el) el.className = 'dot ' + state;
  }
  async function checkStatus() {
    var ready = document.getElementById('status-ready');
    try {
      var res  = await fetch('/api/status');
      var data = await res.json();
      var svcs = data.services || {};
      setDot('dot-api',  svcs.api?.ok  ? 'ok' : 'fail');
      setDot('dot-groq', svcs.groq?.ok ? 'ok' : 'fail');
      var docs = svcs.opensearch?.docs ?? 0;
      if (svcs.api?.ok && svcs.groq?.ok) {
        ready.textContent = docs > 0 ? 'READY' : 'READY — NO DOCS INDEXED';
        ready.className   = 'ok pixel';
      } else {
        ready.textContent = 'SERVICE OFFLINE';
        ready.className   = 'fail pixel';
      }
    } catch (e) {
      setDot('dot-api',    'fail');
      setDot('dot-ollama', 'fail');
      ready.textContent = 'UNREACHABLE';
      ready.className   = 'fail pixel';
    }
  }
  checkStatus();
  setInterval(checkStatus, 15000);
})();
</script>
</body>
</html>
