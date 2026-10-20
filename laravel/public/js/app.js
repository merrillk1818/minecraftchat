/* ============================================================
   MinecraftChat AI — App logic
   ============================================================ */

const chatWindow  = document.getElementById('chat-window');
const questionEl  = document.getElementById('question');
const sendBtn     = document.getElementById('send-btn');
const errorMsg    = document.getElementById('error-msg');
const csrfToken   = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

let currentSessionId    = null;
let pendingMessageText  = null;
let pendingThinking     = null;

// ── Helpers ───────────────────────────────────────────────────
function escapeHtml(str) {
  return str
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

function appendMessage(role, text, sources) {
  const wrapper = document.createElement('div');
  wrapper.className = `message ${role}`;

  const label = document.createElement('div');
  label.className = 'msg-label';
  label.textContent = role === 'user' ? 'YOU' : 'ASSISTANT';
  wrapper.appendChild(label);

  const bubble = document.createElement('div');
  bubble.className = 'bubble';
  bubble.textContent = text;
  wrapper.appendChild(bubble);

  if (sources && sources.length) {
    const src = document.createElement('div');
    src.className = 'sources';
    src.innerHTML = sources.map(s => `<span>${escapeHtml(s)}</span>`).join('');
    wrapper.appendChild(src);
  }

  chatWindow.appendChild(wrapper);
  chatWindow.scrollTop = chatWindow.scrollHeight;
  return wrapper;
}

function appendThinking() {
  const wrapper = document.createElement('div');
  wrapper.className = 'message bot';

  const label = document.createElement('div');
  label.className = 'msg-label';
  label.textContent = 'ASSISTANT';
  wrapper.appendChild(label);

  const thinking = document.createElement('div');
  thinking.className = 'thinking';
  thinking.innerHTML = `
    <div class="block"></div>
    <div class="block"></div>
    <div class="block"></div>
    <span class="think-label">Thinking...</span>`;
  wrapper.appendChild(thinking);

  chatWindow.appendChild(wrapper);
  chatWindow.scrollTop = chatWindow.scrollHeight;
  return wrapper;
}

function clearChat() {
  chatWindow.innerHTML = `
    <div class="message bot">
      <div class="msg-label">ASSISTANT</div>
      <div class="bubble">Hello! I'm your Minecraft AI assistant. Ask me about crafting, survival, mobs, biomes, redstone, commands, or anything else Minecraft!</div>
    </div>`;
}

// ── Auto-resize textarea ──────────────────────────────────────
questionEl.addEventListener('input', () => {
  questionEl.style.height = 'auto';
  questionEl.style.height = Math.min(questionEl.scrollHeight, 160) + 'px';
  errorMsg.textContent = '';
  questionEl.classList.remove('invalid');
});

questionEl.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
});

sendBtn.addEventListener('click', sendMessage);

// ── Send message ──────────────────────────────────────────────
function sendMessage() {
  const text = questionEl.value.trim();

  if (!text || text.length < 3) {
    errorMsg.textContent = text ? 'Question too short.' : 'Enter a question.';
    questionEl.classList.add('invalid');
    questionEl.focus();
    return;
  }

  errorMsg.textContent = '';
  questionEl.classList.remove('invalid');
  sendBtn.disabled = questionEl.disabled = true;

  pendingMessageText = text;
  questionEl.value = '';
  questionEl.style.height = 'auto';

  // Show immediately — don't wait for Turnstile
  appendMessage('user', text);
  pendingThinking = appendThinking();

  // Trigger invisible Turnstile — onChatTurnstileVerify fires when done
  if (typeof turnstile !== 'undefined') {
    turnstile.execute(document.getElementById('chat-turnstile'));
  } else {
    onChatTurnstileVerify(null);
  }
}

window.onChatTurnstileVerify = async function(token) {
  const text     = pendingMessageText;
  const thinking = pendingThinking;
  pendingMessageText = null;
  pendingThinking    = null;

  try {
    const body = { message: text };
    if (currentSessionId) body.session_id = currentSessionId;
    if (token) body['cf-turnstile-response'] = token;

    const res = await fetch('/api/chat', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify(body),
    });

    thinking.remove();

    if (!res.ok) {
      const err = await res.json().catch(() => ({}));
      appendMessage('bot', 'Error: ' + (err.error || 'Something went wrong. Try again.'));
      return;
    }

    const data = await res.json();
    appendMessage('bot', data.response || 'No response received.');

    if (data.session_id) {
      const isNew = !currentSessionId;
      currentSessionId = data.session_id;
      if (isNew) loadSessions();
    }

  } catch {
    thinking.remove();
    appendMessage('bot', 'Could not reach the server. Please try again.');
  } finally {
    sendBtn.disabled = questionEl.disabled = false;
    // Reset Turnstile so next message gets a fresh token
    if (typeof turnstile !== 'undefined') {
      turnstile.reset(document.getElementById('chat-turnstile'));
    }
    questionEl.focus();
  }
}

// ── Tab switching ─────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = btn.dataset.panel;
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(target)?.classList.add('active');
  });
});

// ── Notes ─────────────────────────────────────────────────────
const noteTitleEl   = document.getElementById('note-title');
const noteContentEl = document.getElementById('note-content');
const saveNoteBtn   = document.getElementById('save-note-btn');
const noteMsg       = document.getElementById('note-msg');

if (saveNoteBtn) {
  saveNoteBtn.addEventListener('click', saveNote);
  noteContentEl?.addEventListener('keydown', e => {
    if (e.key === 'Enter' && e.ctrlKey) saveNote();
  });
}

async function saveNote() {
  const content = noteContentEl.value.trim();
  if (content.length < 10) {
    noteMsg.textContent = 'Note too short (min 10 characters).';
    noteMsg.className   = 'field-msg error-msg';
    return;
  }

  noteMsg.textContent      = '';
  saveNoteBtn.disabled     = true;
  saveNoteBtn.textContent  = 'SAVING...';

  try {
    const res = await fetch('/api/notes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
      body: JSON.stringify({ title: noteTitleEl?.value.trim() || '', content }),
    });

    const data = await res.json();

    if (!res.ok) {
      noteMsg.textContent = data.message || 'Failed to save note.';
      noteMsg.className   = 'field-msg error-msg';
      return;
    }

    noteTitleEl.value   = '';
    noteContentEl.value = '';
    noteMsg.textContent = `"${data.title}" saved and queued for indexing.`;
    noteMsg.className   = 'field-msg ok-msg';
    setTimeout(() => { noteMsg.textContent = ''; }, 4000);

  } catch {
    noteMsg.textContent = 'Could not reach server.';
    noteMsg.className   = 'field-msg error-msg';
  } finally {
    saveNoteBtn.disabled    = false;
    saveNoteBtn.textContent = 'SAVE';
  }
}

// ── Session sidebar ───────────────────────────────────────────
const sessionList   = document.getElementById('session-list');
const newChatBtn    = document.getElementById('new-chat-btn');
const sidebar       = document.getElementById('sidebar');
const sidebarToggle = document.getElementById('sidebar-toggle');
const sidebarScrim  = document.getElementById('sidebar-scrim');

const isMobile = () => window.matchMedia('(max-width: 540px)').matches;

if (newChatBtn) {
  newChatBtn.addEventListener('click', () => {
    currentSessionId = null;
    clearChat();
    setActiveSession(null);
    if (isMobile()) document.body.classList.remove('sidebar-open');
    questionEl.focus();
  });
}

if (sidebarToggle) {
  sidebarToggle.addEventListener('click', () => {
    if (isMobile()) {
      document.body.classList.toggle('sidebar-open');
    } else {
      document.body.classList.toggle('sidebar-hidden');
    }
  });
}

if (sidebarScrim) {
  sidebarScrim.addEventListener('click', () => {
    document.body.classList.remove('sidebar-open');
  });
}

function setActiveSession(id) {
  document.querySelectorAll('.session-item').forEach(el => {
    el.classList.toggle('active', el.dataset.id == id);
  });
}

async function loadSessions() {
  if (!window.APP?.isLoggedIn || !sessionList) return;

  try {
    const res  = await fetch('/api/sessions', { headers: { 'X-CSRF-TOKEN': csrfToken } });
    if (!res.ok) return;
    const sessions = await res.json();

    sessionList.innerHTML = sessions.length
      ? sessions.map(s => `
          <div class="session-item ${s.id == currentSessionId ? 'active' : ''}" data-id="${s.id}">
            <span class="session-title">${escapeHtml(s.title)}</span>
            <button class="session-delete" data-id="${s.id}" title="Delete">✕</button>
          </div>`).join('')
      : '<div class="session-empty pixel">No sessions yet</div>';

    sessionList.querySelectorAll('.session-item').forEach(el => {
      el.addEventListener('click', e => {
        if (e.target.classList.contains('session-delete')) return;
        loadSession(parseInt(el.dataset.id));
      });
    });

    sessionList.querySelectorAll('.session-delete').forEach(btn => {
      btn.addEventListener('click', e => {
        e.stopPropagation();
        deleteSession(parseInt(btn.dataset.id));
      });
    });

  } catch {}
}

async function loadSession(id) {
  try {
    const res = await fetch(`/api/sessions/${id}/messages`, {
      headers: { 'X-CSRF-TOKEN': csrfToken },
    });
    if (!res.ok) return;
    const data = await res.json();

    chatWindow.innerHTML = '';
    data.messages.forEach(m => appendMessage(m.role === 'user' ? 'user' : 'bot', m.content));
    currentSessionId = id;
    setActiveSession(id);
    chatWindow.scrollTop = chatWindow.scrollHeight;
  } catch {}
}

async function deleteSession(id) {
  try {
    await fetch(`/api/sessions/${id}`, {
      method: 'DELETE',
      headers: { 'X-CSRF-TOKEN': csrfToken },
    });
    if (currentSessionId === id) {
      currentSessionId = null;
      clearChat();
    }
    loadSessions();
  } catch {}
}

if (window.APP?.isLoggedIn) loadSessions();

// ── Status bar ────────────────────────────────────────────────
function setDot(id, state) {
  const el = document.getElementById(id);
  if (el) el.className = 'dot ' + state;
}

async function checkStatus() {
  const ready = document.getElementById('status-ready');
  if (!ready) return;
  try {
    const res  = await fetch('/api/status');
    const data = await res.json();
    const svcs = data.services || {};

    setDot('dot-api',  svcs.api?.ok  ? 'ok' : 'fail');
    setDot('dot-groq', svcs.groq?.ok ? 'ok' : 'fail');

    const docs = svcs.opensearch?.docs ?? 0;
    if (svcs.api?.ok && svcs.groq?.ok) {
      ready.textContent = docs > 0 ? 'READY' : 'READY — NO DOCS INDEXED';
      ready.className   = 'ok pixel';
    } else {
      ready.textContent = 'SERVICE OFFLINE';
      ready.className   = 'fail pixel';
    }
  } catch {
    setDot('dot-api',  'fail');
    setDot('dot-groq', 'fail');
    ready.textContent = 'UNREACHABLE';
    ready.className   = 'fail pixel';
  }
}

checkStatus();
setInterval(checkStatus, 15000);
