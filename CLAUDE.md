# MinecraftChat AI — Claude Code Context

Auto-read at session start. See `docs/ARCHITECTURE.md` and `docs/ROADMAP.md` for full detail.

---

## What this project is

A self-hosted Minecraft game AI chatbot. Users ask questions about Minecraft and get answers
powered by an LLM backed by a vector search knowledge base (OpenSearch) built from crawled
Minecraft wiki content. Fully self-hostable Docker stack — bring your own server.

---

## Repository and deploy

| Item | Value |
|---|---|
| GitHub | https://github.com/merrillk1818/minecraftchat |
| Branch | `main` |
| Deploy | Push to `main` → GitHub Actions → SSH → `git reset --hard` → `docker compose build + up` → migrate → cache |
| Secrets | `SERVER_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY` in GitHub repo settings |

---

## Docker stack (8 services)

| Service | Image | Role |
|---|---|---|
| `nginx` | nginx:1.27-alpine | Reverse proxy, SSL, rate limiting |
| `laravel` | php:8.4-fpm-alpine (custom) | Web app, auth, API, queue dispatch |
| `queue-worker` | same as laravel | Background indexing jobs |
| `python-api` | python:3.11-slim (custom) | FastAPI: chat, embedding, indexing |
| `redis` | redis:7-alpine | Queue backend, cache, rate-limit counters |
| `opensearch` | opensearchproject/opensearch:2.13.0 | Vector search (knn 384-dim) |
| `mariadb` | mariadb:11.3 | Relational data, source of truth |
| `ollama` *(optional)* | ollama/ollama | Local LLM runtime — off by default (`profiles: [ollama]`), **not called anywhere in the chat path** |

Only nginx exposes host ports (80, 443). All others are Docker-internal. Groq is the only LLM
backend actually wired into `chatbot.py` — Ollama is an opt-in extra container plus an admin
dashboard status card, nothing more, unless someone adds code to actually call it.

---

## Configuration — single `.env` file

Root `.env` is the only config file. `docker-compose.yml` passes all vars to every container that needs them — including Turnstile keys and the internal API key. There is no separate `laravel/.env` required in production.

Key vars in root `.env`:

```
APP_ENV, APP_KEY, APP_URL
DB_ROOT_PASSWORD, DB_DATABASE, DB_USERNAME, DB_PASSWORD
OPENSEARCH_INDEX
GROQ_API_KEY, GROQ_MODEL   # the only LLM backend actually called by chatbot.py
OLLAMA_ENABLED, OLLAMA_URL, OLLAMA_MODEL   # optional, off by default — see Docker stack above
INTERNAL_API_KEY          # shared secret: Laravel → Python API (X-Internal-Key header)
TURNSTILE_SITE_KEY        # Cloudflare Turnstile — login/register (Managed)
TURNSTILE_SECRET_KEY
TURNSTILE_SITE_KEY_CHAT   # Cloudflare Turnstile — chat send (Invisible)
TURNSTILE_SECRET_KEY_CHAT
```

---

## Nginx routing

```
HTTPS → your-domain
  /*          → Laravel FPM (laravel:9000)
  (no /api/ai/ route — Python API is NOT publicly proxied)

HTTP → 301 → HTTPS
www.* → 301 → non-www
```

---

## Key routes (web.php)

| Method | Path | Auth | Throttle | Handler |
|---|---|---|---|---|
| GET | / | — | — | welcome view |
| POST | /login | guest | 10/5min per IP | AuthController::login |
| POST | /register | guest | 5/10min per IP | AuthController::register |
| POST | /logout | auth | — | AuthController::logout |
| GET | /api/status | — | — | ChatController::status |
| POST | /api/chat | — | 10/10min IP / 30/10min user | ChatController::chat |
| GET | /api/sessions | auth | — | ChatController::sessions |
| DELETE | /api/sessions/{id} | auth | — | ChatController::deleteSession |
| POST | /api/notes | auth | 20/10min per user | NoteController::store |
| GET/POST | /admin/* | auth+admin | — | Admin controllers |

---

## Python API endpoints (internal only)

All require `X-Internal-Key` header. No public route exists.

| Method | Path | Purpose |
|---|---|---|
| GET | /health | Liveness check (no key required) |
| POST | /chat | RAG query → LLM response |
| POST | /index/document | Chunk + embed + index one document |
| DELETE | /index/document/{doc_id} | Delete all chunks for a document |
| POST | /index | Index a directory of files |

**user_id is NOT accepted** on `/chat`. Search always filters to `data_type=public`. User identity decisions stay in Laravel.

---

## Security notes

- Python API: no public Nginx route, `X-Internal-Key` middleware, OpenAPI docs disabled
- Turnstile CAPTCHA: Managed on login/register, Invisible on chat
- Rate limiting: Laravel named limiters (Redis) per-route; Nginx 30/min general
- CSRF: Laravel web middleware on all POST routes
- SSH: key-only, root disabled, fail2ban recommended (3 strikes = 24h ban)
- Firewall: only ports you actually need open (22, 80, 443, plus anything else your setup requires)
- Secrets: never committed; root `.env` is gitignored

---

## Useful commands

```bash
# All from the project root on your own server
make logs           # tail all containers
make logs-ai        # python-api only
make logs-queue     # queue worker only
make ps             # container status

make shell-laravel  # sh into laravel container
make shell-python   # bash into python-api
make shell-db       # mariadb CLI

make migrate        # run pending migrations
make docs-status    # indexing status
make docs-import FILE=path/to/file.txt
make docs-import-dir DIR=/path/to/dir
make admin-promote EMAIL=user@email.com

# Ollama (optional — requires OLLAMA_ENABLED=true + the ollama profile running)
docker compose --profile ollama up -d
make ollama-pull
docker compose exec ollama ollama list
```

---

## Current state

- Phase 1: Core chat UI live
- Phase 1.5: Document import and indexing pipeline
- Phase 2: User auth, session history, notes, mobile sidebar
- Phase 3: Admin panel — document management, user management, dashboard
- Phase 4: Migrated primary inference from local Ollama to Groq; Ollama now optional/opt-in,
  not wired into chat; Groq model health actually verified (not just "is a key set"), with
  failures logged instead of swallowed
- Security: Python API fully internal, Turnstile, rate limiting, internal API key

See `docs/ROADMAP.md` Future/Backlog for next work (streaming responses, hybrid search, user notes in search context).

This repo is a sanitized public template — no production server details, credentials, or
deployment targets are included. Point `.env`, `nginx/conf.d/default.conf`, and the GitHub
Actions deploy secrets at your own server to run it.
