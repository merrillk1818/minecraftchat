@extends('admin.layout')

@section('content')

{{-- Upload form --}}
<div class="admin-section">
  <h2 class="admin-section-title pixel">UPLOAD DOCUMENT</h2>
  <form method="POST" action="/admin/documents/upload" enctype="multipart/form-data" class="upload-form">
    @csrf
    <input type="text" name="title" class="auth-input upload-title"
           placeholder="Title (optional — defaults to filename)" maxlength="255" />
    <div class="upload-row">
      <label class="file-label">
        <input type="file" name="file" accept=".txt,.md,.html,.htm,.pdf" required class="file-input" />
        <span class="file-btn mc-btn-small pixel">CHOOSE FILE</span>
        <span class="file-name pixel" id="file-name-display">No file chosen</span>
      </label>
      <button type="submit" class="mc-btn">UPLOAD &amp; INDEX</button>
    </div>
    @error('file') <div class="flash flash-err">{{ $message }}</div> @enderror
  </form>
</div>

{{-- Filters --}}
<div class="admin-section">
  <div class="doc-filters">
    <div class="filter-tabs">
      @foreach(['all' => 'ALL', 'indexed' => 'INDEXED', 'pending' => 'PENDING', 'failed' => 'FAILED'] as $val => $label)
        <a href="/admin/documents?status={{ $val }}{{ $search ? '&search='.urlencode($search) : '' }}"
           class="filter-tab pixel {{ $status === $val ? 'active' : '' }}">
          {{ $label }}
          @if($val !== 'all' && isset($counts[$val]))
            <span class="filter-count">{{ $counts[$val] }}</span>
          @endif
        </a>
      @endforeach
    </div>
    <form method="GET" action="/admin/documents" class="search-form">
      <input type="hidden" name="status" value="{{ $status }}" />
      <input type="text" name="search" value="{{ $search }}" class="auth-input search-input"
             placeholder="Search titles..." />
      <button class="mc-btn-small pixel">SEARCH</button>
    </form>
  </div>

  <table class="admin-table">
    <thead>
      <tr>
        <th class="pixel">TITLE</th>
        <th class="pixel">SOURCE</th>
        <th class="pixel">STATUS</th>
        <th class="pixel">CHUNKS</th>
        <th class="pixel">DATE</th>
        <th class="pixel">ACTIONS</th>
      </tr>
    </thead>
    <tbody>
      @forelse($documents as $doc)
        <tr>
          <td class="doc-title" title="{{ $doc->title }}">{{ Str::limit($doc->title, 45) }}</td>
          <td class="doc-source">{{ $doc->source_path ? basename($doc->source_path) : '—' }}</td>
          <td><span class="status-badge status-{{ $doc->status }}">{{ strtoupper($doc->status) }}</span></td>
          <td>{{ $doc->chunks_indexed ?? '—' }}</td>
          <td class="doc-date">{{ $doc->created_at->format('M j, Y') }}</td>
          <td class="doc-actions">
            @if(in_array($doc->status, ['failed', 'indexed']))
              <form method="POST" action="/admin/documents/{{ $doc->id }}/reindex" style="display:inline">
                @csrf
                <button class="action-btn pixel" title="Re-index">↺</button>
              </form>
            @endif
            <form method="POST" action="/admin/documents/{{ $doc->id }}" style="display:inline"
                  onsubmit="return confirm('Delete \'{{ addslashes($doc->title) }}\'?')">
              @csrf @method('DELETE')
              <button class="action-btn danger pixel" title="Delete">✕</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td colspan="6" class="empty-row pixel">No documents found.</td></tr>
      @endforelse
    </tbody>
  </table>

  <div class="pagination-wrap">
    {{ $documents->links('admin.pagination') }}
  </div>
</div>

<script>
document.querySelector('.file-input')?.addEventListener('change', function() {
  document.getElementById('file-name-display').textContent = this.files[0]?.name || 'No file chosen';
});
</script>

@endsection
