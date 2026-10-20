# MinecraftChat AI — Roadmap

## Phase 1 — Core Chat UI ✅ Complete

Working public chatbot, Minecraft theme, live at https://minecraftchat.ai.

- Hetzner CPX41 server (4 vCPU, 16 GB RAM) provisioned and hardened
- Docker Compose stack: Nginx, Laravel, Python FastAPI, Ollama, OpenSearch, MariaDB, Redis
- Ollama running llama3.2 (CPU-only, ~4 GB RAM)
- OpenSearch index with knn_vector mapping (384-dim, all-MiniLM-L6-v2)
- RAG pipeline: embed → knn search → context → Ollama → response
- Minecraft-themed Blade UI (Press Start 2P font, grass/dirt header, thinking animation)
- CSRF-protected POST to /api/chat
- Status bar polling /api/status every 15 seconds
- Cloudflare DNS + Full-strict SSL + Let's Encrypt cert
- Nginx rate limiting (30 req/min general)
- GitHub Actions CI/CD: push to main → auto deploy
- Redis queue worker container
- fail2ban + UFW firewall

---

## Phase 1.5 — Seed the Knowledge Base ✅ Complete

Minecraft wiki content in OpenSearch; chatbot answers from real knowledge.

- `documents` table in MariaDB (source of truth for all indexed content)
- `IndexDocumentJob` — queued job that chunks, embeds, and indexes one document
- `docs:import` and `docs:import-dir` artisan commands — support .txt, .md, .html, .pdf
- `docs:status` artisan command — shows indexed/pending/failed counts
- smalot/pdfparser for PDF text extraction
- HTML cleaning (tag stripping, whitespace normalisation)
- Bulk import of 2,399 crawled files from minecraft.wiki and minecraft.net
- 8,024+ chunks indexed in OpenSearch
- Admin `docs:import-dir` successfully queued and processed all documents
- Status bar correctly shows doc count from MariaDB

---

## Phase 2 — User Accounts and Session History ✅ Complete

Registered users get persistent chat history and private note indexing.

- Laravel auth: registration, login, logout with Cloudflare Turnstile CAPTCHA
- `users` table with `role` column (user / admin)
- `chat_sessions` and `chat_messages` tables
- Session sidebar: list past conversations, click to resume, delete sessions
- Guest sessions: ephemeral (no history saved)
- Registered sessions: saved per user, resumable from sidebar
- Notes tab: users write notes that are saved to MariaDB and queued for indexing
- Indexed notes are scoped to `data_type=user` with `user_id` label (tagged, not yet surfaced in search)
- Mobile sidebar: slides in as fixed overlay with tap-outside scrim
- App-level rate limiting on login, register, chat, notes (Redis-backed named limiters)

---

## Phase 3 — Admin Panel and Content Management ✅ Complete

Admins have visibility and control over the knowledge base and users.

- `/admin/*` route group, gated by `admin` middleware (role check)
- `admin:promote {email}` artisan command — set a user's role to admin
- **Dashboard**: doc counts by status, user count, queue depth, service health
- **Documents**: paginated list with status filter and search; upload files (PDF/TXT/MD/HTML); reindex individual or all failed; delete (removes from OpenSearch and MariaDB)
- **Users**: list all users with session count; set role (user/admin); prevents self-demotion
- Shared admin layout with nav tabs and flash messages
- Minecraft-pixel-themed admin CSS

---

## Security hardening ✅ Complete (parallel with Phase 3)

- Python API removed from public Nginx routing — only reachable inside Docker network
- FastAPI OpenAPI/ReDoc docs disabled (`docs_url=None`)
- `X-Internal-Key` middleware on Python API — all non-health endpoints require shared secret
- `user_id` removed from Python API chat path — search always public-only; identity not caller-supplied
- `/user/{user_id}/data` DELETE endpoint removed (was unused and accepted arbitrary IDs from URL)
- Cloudflare Turnstile: Managed challenge on login/register, Invisible on chat
- Auth bug fixed: `cf-turnstile-response` excluded from `Auth::attempt()` credentials

---

## Future / Backlog

Not planned for a current phase, tracked here for reference.

### Performance
- **Streaming responses** — switch Ollama call to streaming; push tokens to the browser via SSE or chunked transfer. Removes the 30–120 second wait before any text appears.
- **GPU acceleration** — move Ollama to a machine with an NVIDIA GPU. llama3.2 goes from ~60s on 4 CPU cores to ~2s. Most impactful single change.
- **Faster model** — `llama3.2:1b` (1 billion params) infers ~4× faster than the default 3B model on CPU, at some quality cost.

### Search quality
- **User notes in search** — surfacing `data_type=user` chunks requires passing a trusted user token to the Python API (not just a raw user_id). Needs a signed session token or JWT shared between Laravel and Python.
- **Hybrid search** — combine knn vector score with BM25 keyword score for better recall on exact-match queries (item IDs, command syntax).
- **Citation links** — surface `source` field from top OpenSearch results as clickable links in the chat bubble.
- **Query rewriting** — before embedding, rephrase the user question to improve retrieval (e.g., expand "how do I make a sword" → "crafting recipe iron sword diamond sword").

### Content
- **Re-crawler** — schedule periodic re-crawl of minecraft.wiki to pick up new update content.
- **Version-aware docs** — tag chunks with Minecraft version; filter search by version preference.
- **Minecraft server integration** — let server admins upload their own data (rules, plugins, custom content) and scope it to their server's users.

### Infrastructure
- **Horizontal scaling** — move Python API to a dedicated GPU server; keep Laravel/DB/Redis on the web server.
- **OpenSearch cluster** — two-node setup for redundancy (single-node is fine until traffic grows).
- **Backup automation** — nightly MariaDB dump + OpenSearch snapshot to object storage.

### UX
- **Multi-language** — Minecraft is global; detect query language and respond in kind.
- **Mobile app** — the pixel UI works on mobile; a PWA wrapper could add home-screen install and offline mode.

---

## Decision log

Key architecture decisions, recorded to avoid re-litigation.

| Decision | Choice | Why |
|---|---|---|
| Hosting | Hetzner CPX41 (4 vCPU, 16 GB RAM) | More RAM per dollar than DigitalOcean; Ollama needs ~4 GB for llama3.2 |
| LLM | Ollama (local) not OpenAI API | Zero per-query cost; full data privacy; offline capable |
| Vector DB | OpenSearch (single index) | One index is simpler than per-user shards; fast metadata filtering |
| Relational DB | MariaDB as source of truth | OpenSearch is rebuildable cache; raw text lives in MariaDB |
| Queue backend | Redis | Network-accessible for future distributed workers; priority queue support |
| Redis client | predis/predis | phpredis PHP extension not available in Alpine image; predis is pure PHP |
| PHP version | 8.4 | symfony/error-handler v8+ requires PHP 8.4; Laravel 11 constraint |
| Deploy | GitHub Actions → SSH → git reset --hard | Clean deploy without merge conflicts; reversible via git revert |
| SSL | Let's Encrypt + Cloudflare DNS challenge | Wildcard-capable; no port 80 exposure needed for renewal |
| Python API routing | No public Nginx proxy | Prevents direct access to indexing, crawl, and delete endpoints |
| user_id in chat | Removed from Python API | Prevents caller-controlled data scoping; identity decisions stay in Laravel |
| Auth CAPTCHA | Cloudflare Turnstile | GDPR-friendly, no cookie banner needed; invisible option for chat UX |
| Rate limiting | Laravel named limiters (Redis) + Nginx | Laravel allows per-user limits and named limiters; Nginx adds IP-level burst protection |
