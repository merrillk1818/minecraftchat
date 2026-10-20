# MinecraftChat AI

A self-hosted AI chatbot that answers Minecraft questions. Uses Groq's cloud API for inference (fast, no GPU needed). Knowledge comes from crawled Minecraft wiki pages stored in a vector database.

Live at **https://minecraftchat.ai**

---

## What it does

- Users ask questions about Minecraft (crafting, survival, redstone, mobs, biomes, commands)
- The system searches a local vector knowledge base for relevant context
- An LLM (Groq) generates a grounded answer using that context
- Registered users get persistent chat session history and can save private notes that get indexed into their search context
- Admins can upload documents, manage the knowledge base, and manage users

---

## Services

Seven Docker containers by default, all on an internal bridge network, plus one optional one. Only ports 80 and 443 are exposed to the host.

| Service | Image | Purpose |
|---|---|---|
| `nginx` | nginx:1.27-alpine | Reverse proxy, SSL termination, rate limiting |
| `laravel` | php:8.4-fpm-alpine (custom) | Web app — routing, auth, queue dispatch |
| `queue-worker` | same as laravel | Background job processor (document indexing) |
| `python-api` | python:3.11-slim (custom) | FastAPI — RAG pipeline, embedding, Groq calls |
| `redis` | redis:7-alpine | Queue backend and Laravel cache |
| `opensearch` | opensearchproject/opensearch:2.13.0 | Vector search — stores document embeddings |
| `mariadb` | mariadb:11.3 | Relational data — users, sessions, documents |
| `ollama` *(optional)* | ollama/ollama | Local LLM runtime — off by default, not wired into chat yet, see below |

**Minimum RAM:** 4 GB (OpenSearch 512 MB + everything else) with the default Groq backend.
**With Ollama enabled:** add ~1-4 GB depending on model size.

### Optional: Ollama container

The chat pipeline (`python-api/chatbot.py`) calls Groq exclusively — there's no code path that
routes chat completions through Ollama. The `ollama` service exists as an opt-in container plus
an admin dashboard status card, for anyone who wants to self-host the runtime (e.g. to build their
own local-inference backend on top of it). It's off by default and costs nothing to leave off:

```bash
# In .env:
OLLAMA_ENABLED=true

docker compose --profile ollama up -d
make ollama-pull
```

With `OLLAMA_ENABLED=false` (default), the container never starts and the admin dashboard's
OLLAMA card just shows DISABLED instead of pinging a container that isn't there.

---

## Quick start — local development (no SSL required)

### Prerequisites

- Docker and Docker Compose v2
- Git
- 4 GB RAM free
- A free Groq API key (console.groq.com)

### Steps

```bash
# 1. Clone
git clone https://github.com/merrillk1818/minecraftchat.git
cd minecraftchat

# 2. Create and configure environment
make setup
# Edit .env — at minimum set secure DB passwords

# 3. Start (HTTP mode, no SSL needed)
make dev

# 4. First-run bootstrap (separate terminal, wait ~30s for containers to start)
make first-run

# 5. Open http://localhost
```

`make first-run` generates the app key and runs database migrations. Wait for it to complete before using the app.

### Create an admin account

```bash
# Register at http://localhost/register, then:
make admin-promote EMAIL=your@email.com
```

---

## Production setup

### Prerequisites (on server)

- Ubuntu 22.04+ or Debian 12+
- Docker and Docker Compose v2
- A domain pointed at the server
- Cloudflare account (for DNS + Turnstile CAPTCHA)

### 1. Server and firewall

```bash
ufw allow 22 80 443
ufw enable
```

### 2. SSL certificate (Let's Encrypt via Cloudflare DNS)

```bash
apt install certbot python3-certbot-dns-cloudflare
# Create /etc/letsencrypt/cloudflare.ini with your Cloudflare API token
certbot certonly --dns-cloudflare --dns-cloudflare-credentials /etc/letsencrypt/cloudflare.ini \
  -d yourdomain.com -d www.yourdomain.com
```

### 3. Clone and configure

```bash
git clone https://github.com/merrillk1818/minecraftchat.git
cd minecraftchat
make setup
```

Edit `.env`:

```bash
APP_URL=https://yourdomain.com
DB_ROOT_PASSWORD=<strong-password>
DB_PASSWORD=<strong-password>
INTERNAL_API_KEY=<output-of: openssl rand -hex 32>

# Cloudflare Turnstile (create two sites at dash.cloudflare.com → Turnstile)
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
TURNSTILE_SITE_KEY_CHAT=...
TURNSTILE_SECRET_KEY_CHAT=...
```

Edit `nginx/conf.d/default.conf` — replace `minecraftchat.ai` with your domain.

### 4. Start

```bash
make up
make first-run
make admin-promote EMAIL=your@email.com
```

### 5. Auto-deploy via GitHub Actions

Add two secrets to your GitHub repository:
- `SERVER_HOST` — server IP or hostname
- `DEPLOY_SSH_KEY` — private SSH key for the deploy user

Push to `main` triggers automatic deploy.

---

## Environment variables

All configuration lives in a single `.env` file in the project root. Copy `.env.example` to get started.

| Variable | Required | Description |
|---|---|---|
| `APP_ENV` | yes | `local` or `production` |
| `APP_KEY` | yes | Generated by `make first-run` |
| `APP_URL` | yes | Full URL including scheme — used in CSRF and links |
| `DB_ROOT_PASSWORD` | yes | MariaDB root password |
| `DB_DATABASE` | yes | Database name |
| `DB_USERNAME` | yes | Database user |
| `DB_PASSWORD` | yes | Database password |
| `OPENSEARCH_INDEX` | yes | OpenSearch index name (default: `minecraft_docs`) |
| `GROQ_API_KEY` | yes | Your Groq API key (console.groq.com) |
| `GROQ_MODEL` | yes | Groq model ID — verify it's still current in your Groq console |
| `OLLAMA_ENABLED` | optional | `true` to run the optional Ollama container and enable its dashboard status check (default: `false`) |
| `OLLAMA_URL` | optional | Ollama's internal URL (default: `http://ollama:11434`) |
| `OLLAMA_MODEL` | optional | Model for `make ollama-pull` to fetch (default: `llama3.2`) — only relevant if `OLLAMA_ENABLED=true` |
| `INTERNAL_API_KEY` | recommended | Shared secret between Laravel and Python API — leave blank to disable (dev only) |
| `TURNSTILE_SITE_KEY` | optional | Cloudflare Turnstile site key for login/register forms |
| `TURNSTILE_SECRET_KEY` | optional | Cloudflare Turnstile secret key for login/register forms |
| `TURNSTILE_SITE_KEY_CHAT` | optional | Cloudflare Turnstile site key for chat (invisible) |
| `TURNSTILE_SECRET_KEY_CHAT` | optional | Cloudflare Turnstile secret key for chat (invisible) |

Turnstile and `INTERNAL_API_KEY` can be left blank for local development — the app degrades gracefully.

---

## Useful commands

```bash
make setup          # First-time: copy .env, create directories
make dev            # Start in HTTP dev mode (no SSL)
make up             # Start in production mode (needs SSL certs)
make down           # Stop everything
make first-run      # Generate key, migrate DB (run once after first 'up')

make logs           # Tail all logs
make logs-ai        # Tail python-api only
make logs-queue     # Tail queue worker only

make shell-laravel  # Shell into Laravel container
make shell-python   # Shell into Python API container
make shell-db       # MariaDB CLI

make migrate        # Run pending DB migrations
make ollama-pull    # Pull/update the Ollama model (requires OLLAMA_ENABLED=true + the ollama profile running)

make docs-status                    # Show document indexing status
make docs-import FILE=file.txt      # Import one file
make docs-import-dir DIR=/path/dir  # Import all files in a directory
make admin-promote EMAIL=a@b.com    # Promote user to admin
```

---

## How it works

### Request flow

```
Browser → Cloudflare → Nginx → Laravel (PHP-FPM)
                                  │
                    (chat POST) → Python API (FastAPI)
                                  ├── OpenSearch (vector search)
                                  └── Groq (LLM inference)
```

### RAG pipeline (chat query)

1. User submits a question
2. Laravel validates, rate-limits, and forwards to Python API
3. Python API embeds the question using `all-MiniLM-L6-v2` (384-dim)
4. OpenSearch knn search finds the top-k most similar document chunks
5. Chunks are assembled into a context prompt
6. Groq runs the configured model and returns a completion
7. Laravel stores the exchange in MariaDB (for logged-in users) and returns the response

### Document indexing pipeline

```
Admin uploads file / artisan docs:import
  → Document saved to MariaDB (raw_text, status=pending)
  → IndexDocumentJob dispatched to Redis queue
  → Queue worker picks up job
  → Calls Python API /index/document
  → Python API chunks text, embeds each chunk, bulk-inserts to OpenSearch
  → MariaDB document updated (status=indexed, chunks_indexed=N)
```

### Security model

- Python API has no public route — Nginx does not proxy to it; it is reachable only within the Docker network
- All Laravel → Python API calls require an `X-Internal-Key` header (shared secret)
- Cloudflare Turnstile CAPTCHA on login, register, and chat
- App-level rate limiting (Redis-backed): 10 logins/5min, 5 registrations/10min, 10 chat messages/10min per IP (30/10min for logged-in users)
- Nginx rate limiting: 30 req/min general, 429 status on breach
- CSRF tokens on all POST routes
- HSTS, X-Frame-Options, X-Content-Type-Options headers

---

## Architecture and roadmap

See [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) and [`docs/ROADMAP.md`](docs/ROADMAP.md).
