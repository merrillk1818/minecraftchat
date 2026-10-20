@extends('admin.layout')

@section('content')

<div class="admin-section">
  <h2 class="admin-section-title pixel">USERS ({{ $users->total() }})</h2>

  {{-- Search --}}
  <form method="GET" action="/admin/users" class="search-form" style="margin-bottom:16px">
    <input type="text" name="search" value="{{ $search }}"
           class="auth-input search-input" placeholder="Search name or email..." />
    <button class="mc-btn-small pixel">SEARCH</button>
    @if($search)
      <a href="/admin/users" class="action-btn pixel" style="text-decoration:none;padding:6px 10px">CLEAR</a>
    @endif
  </form>

  <table class="admin-table">
    <thead>
      <tr>
        <th class="pixel">NAME</th>
        <th class="pixel">EMAIL</th>
        <th class="pixel">ROLE</th>
        <th class="pixel">SESSIONS</th>
        <th class="pixel">REGISTERED</th>
        <th class="pixel">ACTIONS</th>
      </tr>
    </thead>
    <tbody>
      @forelse($users as $user)
        <tr>
          <td>{{ $user->name }}</td>
          <td class="doc-source">{{ $user->email }}</td>
          <td>
            <span class="status-badge {{ $user->role === 'admin' ? 'status-indexed' : 'status-pending' }}">
              {{ strtoupper($user->role) }}
            </span>
          </td>
          <td>{{ $user->chat_sessions_count }}</td>
          <td class="doc-date">{{ $user->created_at->format('M j, Y') }}</td>
          <td class="doc-actions">
            {{-- Edit --}}
            <a href="/admin/users/{{ $user->id }}/edit" class="action-btn pixel" title="Edit">✎ EDIT</a>

            @if($user->id !== auth()->id())
              {{-- Promote / Demote --}}
              <form method="POST" action="/admin/users/{{ $user->id }}/role" style="display:inline">
                @csrf
                <input type="hidden" name="role" value="{{ $user->role === 'admin' ? 'user' : 'admin' }}" />
                <button class="action-btn pixel" title="{{ $user->role === 'admin' ? 'Demote to user' : 'Promote to admin' }}">
                  {{ $user->role === 'admin' ? '↓ USER' : '↑ ADMIN' }}
                </button>
              </form>

              {{-- Delete --}}
              <form method="POST" action="/admin/users/{{ $user->id }}" style="display:inline"
                    onsubmit="return confirm('Delete user \'{{ addslashes($user->name) }}\'? This removes their chat history and private notes.')">
                @csrf @method('DELETE')
                <button class="action-btn danger pixel" title="Delete user">✕</button>
              </form>
            @else
              <span class="doc-source pixel" style="font-size:0.4rem">YOU</span>
            @endif
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty-row pixel">No users found.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="pagination-wrap">
    {{ $users->links('admin.pagination') }}
  </div>
</div>

@endsection
