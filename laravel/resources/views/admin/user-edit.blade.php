@extends('admin.layout')

@section('content')

<div class="admin-section">
  <h2 class="admin-section-title pixel">EDIT USER — {{ $user->name }}</h2>

  @if($errors->any())
    <div class="flash flash-err" style="margin-bottom:16px">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="/admin/users/{{ $user->id }}" class="edit-form">
    @csrf

    <div class="form-row">
      <label class="auth-label pixel">USERNAME</label>
      <input type="text" name="name" value="{{ old('name', $user->name) }}"
             class="auth-input" required maxlength="255" />
    </div>

    <div class="form-row">
      <label class="auth-label pixel">EMAIL</label>
      <input type="email" name="email" value="{{ old('email', $user->email) }}"
             class="auth-input" required maxlength="255" />
    </div>

    <div class="form-row">
      <label class="auth-label pixel">NEW PASSWORD <span style="color:var(--mc-stone)">(leave blank to keep current)</span></label>
      <input type="password" name="password" class="auth-input"
             placeholder="Min 8 characters" minlength="8" autocomplete="new-password" />
    </div>

    <div class="form-row">
      <label class="auth-label pixel">CONFIRM PASSWORD</label>
      <input type="password" name="password_confirmation" class="auth-input"
             placeholder="Repeat new password" autocomplete="new-password" />
    </div>

    <div class="form-actions">
      <button type="submit" class="mc-btn">SAVE CHANGES</button>
      <a href="/admin/users" class="action-btn pixel" style="text-decoration:none;padding:10px 14px;align-self:flex-end">CANCEL</a>
    </div>
  </form>
</div>

<div class="admin-section">
  <h2 class="admin-section-title pixel">ACCOUNT INFO</h2>
  <table class="admin-table">
    <tbody>
      <tr><td class="doc-source pixel" style="font-size:0.4rem;width:140px">ROLE</td>
          <td><span class="status-badge {{ $user->role === 'admin' ? 'status-indexed' : 'status-pending' }}">{{ strtoupper($user->role) }}</span></td></tr>
      <tr><td class="doc-source pixel" style="font-size:0.4rem">REGISTERED</td>
          <td class="doc-source">{{ $user->created_at->format('M j, Y g:i a') }}</td></tr>
      <tr><td class="doc-source pixel" style="font-size:0.4rem">LAST UPDATED</td>
          <td class="doc-source">{{ $user->updated_at->format('M j, Y g:i a') }}</td></tr>
    </tbody>
  </table>
</div>

@endsection
