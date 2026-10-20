@extends('notes.layout')

@section('content')

<div class="admin-section">
  <h2 class="admin-section-title pixel">MY NOTES</h2>

  <div class="doc-filters">
    <div class="filter-tabs">
      @foreach(['all' => 'ALL', 'indexed' => 'INDEXED', 'pending' => 'PENDING', 'failed' => 'FAILED'] as $val => $label)
        <a href="/my-notes?status={{ $val }}{{ $search ? '&search='.urlencode($search) : '' }}"
           class="filter-tab pixel {{ $status === $val ? 'active' : '' }}">
          {{ $label }}
          @if($val !== 'all' && isset($counts[$val]))
            <span class="filter-count">{{ $counts[$val] }}</span>
          @endif
        </a>
      @endforeach
    </div>
    <form method="GET" action="/my-notes" class="search-form">
      <input type="hidden" name="status" value="{{ $status }}" />
      <input type="text" name="search" value="{{ $search }}" class="auth-input search-input"
             placeholder="Search notes..." />
      <button class="mc-btn-small pixel">SEARCH</button>
    </form>
  </div>

  <table class="admin-table">
    <thead>
      <tr>
        <th class="pixel">TITLE</th>
        <th class="pixel">STATUS</th>
        <th class="pixel">CHUNKS</th>
        <th class="pixel">DATE</th>
        <th class="pixel">ACTIONS</th>
      </tr>
    </thead>
    <tbody>
      @forelse($notes as $note)
        <tr>
          <td class="doc-title" title="{{ $note->title }}">{{ Str::limit($note->title, 55) }}</td>
          <td><span class="status-badge status-{{ $note->status }}">{{ strtoupper($note->status) }}</span></td>
          <td>{{ $note->chunks_indexed ?? '—' }}</td>
          <td class="doc-date">{{ $note->created_at->format('M j, Y') }}</td>
          <td class="doc-actions">
            <a href="/my-notes/{{ $note->id }}/edit" class="action-btn pixel" title="Edit">✎</a>
            <form method="POST" action="/my-notes/{{ $note->id }}" style="display:inline"
                  onsubmit="return confirm('Delete \'{{ addslashes($note->title) }}\'?')">
              @csrf @method('DELETE')
              <button class="action-btn danger pixel" title="Delete">✕</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="5" class="empty-row pixel">No notes found.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="pagination-wrap">
    {{ $notes->links('admin.pagination') }}
  </div>
</div>

@endsection
