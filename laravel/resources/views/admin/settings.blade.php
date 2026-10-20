@extends('admin.layout')

@section('content')

<div class="admin-section">
  <h2 class="admin-section-title pixel">TEXT LIMITS</h2>

  <form method="POST" action="/admin/settings" class="edit-form">
    @csrf

    <div class="form-row">
      <label class="auth-label pixel">CHAT MESSAGE LIMIT <span style="color:var(--mc-stone)">(characters)</span></label>
      <input type="number" name="chat_max_length" value="{{ old('chat_max_length', $chatMaxLength) }}"
             class="auth-input" min="100" max="10000" required />
      <span class="doc-source" style="font-size:0.7rem;margin-top:4px">Controls how long a user's question can be. Lower = fewer tokens sent to the AI per request.</span>
    </div>

    <div class="form-row">
      <label class="auth-label pixel">NOTE LIMIT <span style="color:var(--mc-stone)">(characters)</span></label>
      <input type="number" name="note_max_length" value="{{ old('note_max_length', $noteMaxLength) }}"
             class="auth-input" min="500" max="100000" required />
      <span class="doc-source" style="font-size:0.7rem;margin-top:4px">Controls how long a saved note can be. Affects chunk size in the search index.</span>
    </div>

    <div class="form-actions">
      <button type="submit" class="mc-btn">SAVE</button>
    </div>
  </form>
</div>

@endsection
