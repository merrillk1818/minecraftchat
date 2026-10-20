@extends('notes.layout')

@section('content')

<div class="admin-section">
  <h2 class="admin-section-title pixel">EDIT NOTE</h2>

  <form method="POST" action="/my-notes/{{ $note->id }}" class="edit-form">
    @csrf

    <div class="form-row">
      <label class="pixel" style="font-size:0.42rem;color:var(--mc-text-dim)">TITLE</label>
      <input type="text" name="title" class="auth-input"
             value="{{ old('title', $note->title) }}" maxlength="255"
             placeholder="Note title" />
      @error('title') <div class="flash flash-err" style="margin-top:6px">{{ $message }}</div> @enderror
    </div>

    <div class="form-row">
      <label class="pixel" style="font-size:0.42rem;color:var(--mc-text-dim)">CONTENT</label>
      <textarea name="content" class="auth-input" rows="18"
                style="resize:vertical;min-height:220px;font-family:inherit;font-size:0.8rem;line-height:1.6">{{ old('content', $note->raw_text) }}</textarea>
      @error('content') <div class="flash flash-err" style="margin-top:6px">{{ $message }}</div> @enderror
    </div>

    <div class="form-actions">
      <button type="submit" class="mc-btn">SAVE &amp; INDEX</button>
      <a href="/my-notes" class="nav-btn pixel">CANCEL</a>
    </div>
  </form>
</div>

@endsection
