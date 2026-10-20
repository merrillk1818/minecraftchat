<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>MinecraftChat AI</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/css/app.css" />
  <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body class="{{ auth()->check() ? 'has-sidebar' : '' }}">

<header>
  <span class="mc-icon">⛏</span>
  <h1>Minecraft Chat AI</h1>
  <nav class="header-nav">
    @auth
      @if(auth()->user()->isSidebarOpen ?? true)
        <button id="sidebar-toggle" class="nav-btn pixel" title="Toggle sessions">☰</button>
      @endif
      <a href="/my-notes" class="nav-btn pixel">MY NOTES</a>
      <span class="nav-user pixel">{{ auth()->user()->name }}</span>
      <form method="POST" action="/logout" style="display:inline">
        @csrf
        <button type="submit" class="nav-btn pixel">LOGOUT</button>
      </form>
    @else
      <a href="/login"    class="nav-btn pixel">LOGIN</a>
      <a href="/register" class="nav-btn pixel">REGISTER</a>
    @endauth
  </nav>
</header>

<div id="app-body">
<div id="sidebar-scrim"></div>

  @auth
  <aside id="sidebar">
    <div class="sidebar-header">
      <span class="pixel">SESSIONS</span>
      <button id="new-chat-btn" class="mc-btn-small pixel" title="Start new chat">+ NEW</button>
    </div>
    <div id="session-list"></div>
  </aside>
  @endauth

  <div id="main-panel">
    <div id="chat-window">
      <div class="message bot">
        <div class="msg-label">ASSISTANT</div>
        <div class="bubble">Hello! I'm your Minecraft AI assistant. Ask me about crafting, survival, mobs, biomes, redstone, commands, or anything else Minecraft!</div>
      </div>
    </div>

    <div class="tab-strip">
      <button class="tab-btn active" data-panel="panel-ask">⚔ ASK</button>
      @auth
      <button class="tab-btn" data-panel="panel-note">📋 NOTE</button>
      @endauth
    </div>

    <div id="input-area">
      <div id="panel-ask" class="tab-panel active">
        <div class="input-wrapper">
          <textarea id="question" rows="1" placeholder="Type your question..." maxlength="{{ $chatMaxLength }}"></textarea>
          <div id="error-msg" class="field-msg"></div>
        </div>
        <button id="send-btn" class="mc-btn">SEND</button>
      </div>

      @auth
      <div id="panel-note" class="tab-panel">
        <div class="input-wrapper">
          <input type="text" id="note-title" class="note-title-input"
                 placeholder="Title (optional)" maxlength="255" />
          <textarea id="note-content" rows="3"
                    placeholder="Write a note — it will be indexed and searchable in your chats..."
                    maxlength="{{ $noteMaxLength }}"></textarea>
          <div id="note-msg" class="field-msg"></div>
        </div>
        <button id="save-note-btn" class="mc-btn">SAVE</button>
      </div>
      @endauth
    </div>
  </div>

</div>

<div id="chat-turnstile"
     class="cf-turnstile"
     data-sitekey="{{ config('services.turnstile.site_key_chat') }}"
     data-size="invisible"
     data-callback="onChatTurnstileVerify"
     data-execution="execute"
     style="display:none">
</div>

<script>
  window.APP = {
    isLoggedIn: {{ auth()->check() ? 'true' : 'false' }},
  };
</script>
<script src="/js/app.js"></script>
</body>
</html>
