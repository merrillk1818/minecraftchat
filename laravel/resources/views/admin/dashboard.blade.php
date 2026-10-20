@extends('admin.layout')

@section('content')

<div class="admin-section">
  <h2 class="admin-section-title pixel">SCRAPE URL</h2>
  <form method="POST" action="/admin/scrape" class="upload-form">
    @csrf
    <div class="upload-row">
      <input type="url" name="url" class="auth-input"
             placeholder="https://minecraft.wiki/w/Crafting" required
             style="flex:1" />
      <button type="submit" class="mc-btn">SCRAPE &amp; INDEX</button>
    </div>
    @error('url') <div class="flash flash-err">{{ $message }}</div> @enderror
  </form>
</div>

<div class="admin-section">
  <h2 class="admin-section-title pixel">SYSTEM STATUS</h2>
  <div class="stat-grid">
    <div class="stat-card {{ $apiOk ? 'ok' : 'fail' }}">
      <div class="stat-dot {{ $apiOk ? 'ok' : 'fail' }}"></div>
      <div class="stat-label pixel">PYTHON API</div>
      <div class="stat-value pixel">{{ $apiOk ? 'ONLINE' : 'OFFLINE' }}</div>
    </div>
    @if($ollamaEnabled)
      <div class="stat-card {{ $ollamaOk ? 'ok' : 'fail' }}">
        <div class="stat-dot {{ $ollamaOk ? 'ok' : 'fail' }}"></div>
        <div class="stat-label pixel">OLLAMA</div>
        <div class="stat-value pixel">{{ $ollamaOk ? 'ONLINE' : 'OFFLINE' }}</div>
      </div>
    @else
      <div class="stat-card warn">
        <div class="stat-dot warn"></div>
        <div class="stat-label pixel">OLLAMA</div>
        <div class="stat-value pixel">DISABLED</div>
        <div class="stat-sub">set OLLAMA_ENABLED=true to use</div>
      </div>
    @endif
    <div class="stat-card {{ $groqOk ? 'ok' : 'fail' }}">
      <div class="stat-dot {{ $groqOk ? 'ok' : 'fail' }}"></div>
      <div class="stat-label pixel">GROQ</div>
      <div class="stat-value pixel">{{ $groqOk ? 'ONLINE' : 'OFFLINE' }}</div>
      @if(!$groqOk && $groqDetail)
        <div class="stat-sub">{{ \Illuminate\Support\Str::limit($groqDetail, 70) }}</div>
      @endif
    </div>
    <div class="stat-card">
      <div class="stat-label pixel">QUEUE — UPLOADS</div>
      <div class="stat-value pixel">{{ $queueStats['uploads'] }}</div>
    </div>
    <div class="stat-card">
      <div class="stat-label pixel">QUEUE — CRAWLERS</div>
      <div class="stat-value pixel">{{ $queueStats['crawlers'] }}</div>
    </div>
  </div>
</div>

<div class="admin-section">
  <h2 class="admin-section-title pixel">KNOWLEDGE BASE</h2>
  <div class="stat-grid">
    <div class="stat-card ok">
      <div class="stat-label pixel">INDEXED</div>
      <div class="stat-value pixel big">{{ number_format($docStats['indexed']) }}</div>
      <div class="stat-sub">{{ number_format($docStats['chunks']) }} chunks</div>
    </div>
    <div class="stat-card warn">
      <div class="stat-label pixel">PENDING</div>
      <div class="stat-value pixel big">{{ number_format($docStats['pending'] + $docStats['indexing']) }}</div>
    </div>
    <div class="stat-card {{ $docStats['failed'] > 0 ? 'fail' : '' }}">
      <div class="stat-label pixel">FAILED</div>
      <div class="stat-value pixel big">{{ number_format($docStats['failed']) }}</div>
      @if($docStats['failed'] > 0)
        <form method="POST" action="/admin/documents/reindex-failed" class="mt-8">
          @csrf
          <button class="mc-btn-small pixel">RE-QUEUE ALL</button>
        </form>
      @endif
    </div>
    <div class="stat-card">
      <div class="stat-label pixel">USERS</div>
      <div class="stat-value pixel big">{{ $userStats['total'] }}</div>
      <div class="stat-sub">{{ $userStats['admin'] }} admin</div>
    </div>
  </div>
</div>

@endsection
