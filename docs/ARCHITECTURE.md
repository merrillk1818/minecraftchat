# MinecraftChat AI — Architecture

## System diagram

```
Browser
  │
  │ HTTPS (443)
  ▼
Cloudflare  (DNS proxy, DDoS protection, Full-strict SSL)
  │
  ▼
Nginx  (reverse proxy, rate limiting, HSTS)
  │
  └─ /*  →  Laravel FPM (php:8.4-fpm-alpine)
              │
              ├─ MariaDB        (users, sessions, documents)
              ├─ Redis           (queue backend, cache, rate-limit counters)
              └─ Python API      (internal Docker network only, X-Internal-Key required)
                    │
                    ├─ OpenSearch  (vector search, knn embeddings)
                    └─ Groq         (cloud LLM inference)

Queue Worker  (same Laravel image, separate container)
  ├─ Reads Redis queues: uploads, crawlers, default
  └─ Calls Python API /index/document  (same internal key)
```

The Python API has **no public route**. Nginx does not proxy to it. It is reachable only inside the Docker bridge network, and every request from Laravel must carry an `X-Internal-Key` header.

---

## Service inventory

| Service | Image | Internal port | Role |
|---|---|---|---|
| `nginx` | nginx:1.27-alpine | 80, 443 (host) | Reverse proxy, SSL, rate limiting |
| `laravel` | php:8.4-fpm-alpine (custom) | 9000 | Web app, auth, API routes, queue dispatch |
| `queue-worker` | same as laravel | — | Background indexing jobs |
| `python-api` | python:3.11-slim (custom) | 8000 | FastAPI — chat, embedding, indexing |
| `redis` | redis:7-alpine | 6379 | Queue backend, Laravel cache |
| `opensearch` | opensearchproject/opensearch:2.13.0 | 9200 | Vector search (knn, 384-dim) |
| `mariadb` | mariadb:11.3 | 3306 | Relational data, document source of truth |
| `ollama` *(optional)* | ollama/ollama | 11434 | Local LLM runtime — off by default (`profiles: [ollama]`), not wired into the chat pipeline; see README |

All services run on the `mcnet` Docker bridge. Only `nginx` exposes host ports.

---

## RAG pipeline

**Retrieval Augmented Generation** — the core query path:

1. User submits a question via the chat UI
2. Laravel validates input, checks Turnstile token, applies rate limit, saves message to MariaDB (auth'd users)
3. Laravel forwards to `POST http://python-api:8000/chat` with `X-Internal-Key` header
4. Python API embeds the question with `all-MiniLM-L6-v2` (384 dimensions)
5. OpenSearch knn search returns the top-K most semantically similar document chunks, filtered to `data_type=public`
6. Chunks are assembled into a context block injected into a prompt
7. Groq runs the configured model (`GROQ_MODEL`, `openai/gpt-oss-120b` by default) and returns a completion
8. Laravel stores the assistant reply in MariaDB (auth'd users) and returns JSON to the browser

The question and retrieved context are sent to Groq's API for inference — that's the one point
where data leaves the server. Everything else (embeddings, vector search, storage) stays local.

`GET /health` on the Python API verifies the configured `GROQ_MODEL` actually exists in Groq's
current model list (not just that `GROQ_API_KEY` is set) — Groq periodically retires models, which
previously surfaced to users as a generic "AI service error" with no indication of the real cause.
The admin dashboard's GROQ status card reads this check; failures are also logged with the real
exception/response body (`docker compose logs python-api` / `logs laravel`, `LOG_CHANNEL=stderr`)
instead of being swallowed silently.

---

## Document indexing pipeline

```
Source: admin file upload  OR  artisan docs:import
  ↓
Document record created in MariaDB (status=pending, raw_text stored)
  ↓
IndexDocumentJob dispatched → Redis 'uploads' queue
  ↓
Queue worker picks up job → POST /index/document (X-Internal-Key)
  ↓
Python API:
  chunks = split text into ~500-word overlapping windows
  for each chunk:
    embedding = all-MiniLM-L6-v2.encode(chunk)   # 384-dim float vector
    bulk-insert to OpenSearch with doc_id, data_type, source metadata
  ↓
MariaDB document updated: status=indexed, chunks_indexed=N
```

**Source of truth:** MariaDB holds the raw text. OpenSearch is a rebuildable cache — if the index is corrupted or needs schema changes, delete it and re-queue all documents.

---

## Data architecture

### OpenSearch index (`minecraft_docs`)

Single index for all content, scoped by metadata:

| Field | Type | Purpose |
|---|---|---|
| `content` | text | Chunk text (also used for BM25 keyword fallback) |
| `embedding` | knn_vector (384) | Semantic similarity search via HNSW |
| `data_type` | keyword | `"public"` (wiki/admin docs) or `"user"` (private notes) |
| `user_id` | integer | null = public; set for user-private notes |
| `doc_id` | integer | FK to MariaDB `documents.id` |
| `source` | keyword | Origin path or URL |
| `session_id` | keyword | Reserved — not currently used for filtering |

**Design decision — single index:** One index with metadata filtering was chosen over per-user indexes because it is simpler to operate, backup, and rebuild. A `delete_by_query` on `doc_id` cleanly removes any document's chunks.

**Search scoping:** Chat searches are currently filtered to `data_type=public` only. User identity is not passed to the Python API — search scope is enforced server-side, not via caller-supplied parameters.

### MariaDB tables

| Table | Purpose |
|---|---|
| `users` | Auth — name, email, hashed password, `role` (user/admin) |
| `chat_sessions` | One row per conversation — belongs to a user |
| `chat_messages` | All messages in a session — role (user/assistant), content |
| `documents` | Every indexed document — title, source_path, raw_text, status, chunks_indexed |

### Redis

Queue backend for async jobs. Named queues processed by the queue-worker container:

| Queue | Jobs |
|---|---|
| `uploads` | `IndexDocumentJob` (file uploads + note saves) |
| `crawlers` | Reserved for future crawl jobs |
| `default` | Fallback queue |

Laravel also uses Redis as the cache and rate-limit storage driver (`CACHE_DRIVER=redis`).

---

## Auth and rate limiting

### Authentication

Laravel session-based auth. Cloudflare Turnstile CAPTCHA on all auth and chat entry points:

- Login / Register: Managed challenge widget (visible)
- Chat send: Invisible challenge (fires on submit, resolves before request)

### App-level rate limits (Laravel, Redis-backed)

| Route | Limit | Key |
|---|---|---|
| `POST /login` | 10 / 5 min | IP |
| `POST /register` | 5 / 10 min | IP |
| `POST /api/chat` (guest) | 10 / 10 min | IP |
| `POST /api/chat` (auth) | 30 / 10 min | user ID |
| `POST /api/notes` | 20 / 10 min | user ID |

Nginx adds a second layer: 30 req/min per IP across all PHP routes.

### Internal API security

Laravel → Python API calls require `X-Internal-Key: <INTERNAL_API_KEY>`. The Python API middleware rejects any request to non-health endpoints that is missing or presents the wrong key. The Python API **never trusts a caller-supplied `user_id`** — user identity decisions stay in Laravel.

---

## Security decisions

| Area | Decision |
|---|---|
| Python API exposure | No public route — not proxied by Nginx; internal Docker network only |
| Internal auth | Shared `X-Internal-Key` header; enforced by Starlette middleware |
| user_id scoping | Not passed to Python API for chat; search always filters to public docs |
| OpenAPI docs | Disabled (`docs_url=None`) — schema never served |
| CAPTCHA | Cloudflare Turnstile on login, register, chat |
| Rate limiting | App-level (named limiters, Redis) + Nginx zones |
| CSRF | Laravel web middleware on all POST routes |
| TLS | TLSv1.2 + TLSv1.3; HSTS max-age 1 year; Cloudflare Full-strict |
| SSH | Ed25519 keys only; password auth off; root login off |
| Firewall | UFW: 22, 80, 443, 10000 only |
| Secrets | `.env` gitignored; live only on server and in GitHub Actions secrets |

---

## Deployment pipeline

```
git push origin main
  → GitHub Actions (appleboy/ssh-action)
  → SSH to deploy server
  → git fetch + reset --hard origin/main  (clean, conflict-free pull)
  → docker compose build                  (only changed layers rebuild)
  → docker compose up -d
  → php artisan migrate --force
  → php artisan config:cache + route:cache
  → docker compose restart nginx
```

Rollback: `git revert HEAD && git push`, or SSH + `git reset --hard <commit>` + `docker compose up -d`.

---

## File map

```
minecraftchat.ai/
├── README.md                             ← Setup guide and overview
├── docker-compose.yml                    ← Production stack (all 8 services)
├── docker-compose.dev.yml                ← Dev overrides (HTTP only, no SSL)
├── .env.example                          ← Config template (single file for all services)
├── Makefile                              ← All common commands
├── .github/workflows/deploy.yml          ← Auto-deploy on push to main
│
├── nginx/
│   ├── conf.d/default.conf               ← HTTPS vhost, SSL, proxy rules
│   ├── conf.d/rate-limit.conf            ← Nginx rate limit zone definitions
│   └── conf.d/dev/default.conf           ← HTTP-only config for local dev
│
├── laravel/
│   ├── Dockerfile                        ← php:8.4-fpm-alpine + extensions
│   ├── app/Http/Controllers/
│   │   ├── AuthController.php            ← Login, register, logout + Turnstile
│   │   ├── ChatController.php            ← /api/chat, /api/status, session CRUD
│   │   ├── NoteController.php            ← /api/notes — save + queue for indexing
│   │   └── Admin/
│   │       ├── DashboardController.php   ← Stats, queue depth, health
│   │       ├── DocumentController.php    ← Upload, reindex, delete documents
│   │       └── UserController.php        ← List users, set roles
│   ├── app/Jobs/IndexDocumentJob.php     ← Queued: chunks + indexes one document
│   ├── app/Models/                       ← User, Document, ChatSession, ChatMessage
│   ├── app/Rules/Turnstile.php           ← Cloudflare Turnstile validation rule
│   ├── app/Http/Middleware/AdminOnly.php ← Gate for /admin/* routes
│   ├── app/Providers/AppServiceProvider.php ← Named rate limiter definitions
│   ├── routes/web.php                    ← All routes with throttle middleware
│   ├── resources/views/
│   │   ├── welcome.blade.php             ← Main chat UI
│   │   ├── auth/{login,register}.blade.php
│   │   └── admin/{dashboard,documents,users}.blade.php
│   └── public/
│       ├── css/app.css                   ← Minecraft pixel theme
│       └── js/app.js                     ← Chat logic, session sidebar, Turnstile flow
│
├── python-api/
│   ├── Dockerfile                        ← python:3.11-slim
│   ├── main.py                           ← FastAPI app, InternalKeyMiddleware
│   ├── chatbot.py                        ← embed → knn search → Groq → response
│   ├── indexer.py                        ← chunk text → embed → bulk index to OpenSearch
│   ├── crawler.py                        ← Polite wiki crawler (minecraft.wiki)
│   ├── config.py                         ← All env-var config
│   └── requirements.txt
│
└── docs/
    ├── ARCHITECTURE.md                   ← This file
    └── ROADMAP.md                        ← Phase history and future work
```
