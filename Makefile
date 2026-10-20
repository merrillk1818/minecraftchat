.PHONY: setup first-run dev up down build restart logs logs-ai logs-app logs-queue \
        shell-laravel shell-python shell-db shell-opensearch \
        migrate key-generate ollama-pull admin-promote \
        docs-status docs-import docs-import-dir index-docs deploy

# ── First-time setup ──────────────────────────────────────────────────────────

setup: ## Copy env template, create required directories
	@[ -f .env ] && echo ".env already exists — skipping" || (cp .env.example .env && echo "Created .env — fill in passwords and keys before continuing")
	@mkdir -p data/docs
	@mkdir -p laravel/storage/app/uploads
	@mkdir -p laravel/storage/framework/cache/data
	@mkdir -p laravel/storage/framework/sessions
	@mkdir -p laravel/storage/framework/views
	@mkdir -p laravel/storage/logs
	@mkdir -p laravel/bootstrap/cache
	@echo ""
	@echo "Next steps:"
	@echo "  1. Edit .env with your passwords, APP_URL, and any API keys"
	@echo "  2. Run: make dev        (local HTTP dev)"
	@echo "       or: make up         (production, requires SSL certs in /etc/letsencrypt)"
	@echo "  3. Run: make first-run  (generate key, migrate DB)"

first-run: ## Generate APP_KEY, run migrations — run once after 'make up'
	docker compose exec laravel php artisan key:generate --force
	docker compose exec laravel php artisan migrate --force
	docker compose exec laravel php artisan storage:link 2>/dev/null || true
	docker compose exec laravel php artisan config:cache
	docker compose exec laravel php artisan route:cache
	@echo ""
	@echo "Setup complete. Create an admin account:"
	@echo "  1. Register at /register"
	@echo "  2. make admin-promote EMAIL=your@email.com"
	@echo ""
	@echo "Using local Ollama instead of/alongside Groq? It's off by default."
	@echo "  1. Set OLLAMA_ENABLED=true in .env"
	@echo "  2. docker compose --profile ollama up -d"
	@echo "  3. make ollama-pull"

# ── Start / stop ──────────────────────────────────────────────────────────────

dev: ## Start in local dev mode (HTTP on port 80, no SSL required)
	docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d

up: ## Start all services (production mode — requires SSL certs)
	docker compose up -d

down: ## Stop all services
	docker compose down

build: ## Rebuild all images from scratch
	docker compose build --no-cache

restart: ## Restart all services
	docker compose restart

# ── Logs ─────────────────────────────────────────────────────────────────────

logs: ## Tail all container logs
	docker compose logs -f --tail=100

logs-ai: ## Tail python-api logs
	docker compose logs -f python-api

logs-app: ## Tail laravel + nginx logs
	docker compose logs -f laravel nginx

logs-queue: ## Tail queue worker logs
	docker compose logs -f queue-worker

# ── Shells ────────────────────────────────────────────────────────────────────

ps: ## Show container status
	docker compose ps

shell-laravel: ## Open a shell in the Laravel container
	docker compose exec laravel sh

shell-python: ## Open a shell in the Python API container
	docker compose exec python-api bash

shell-db: ## Open a MariaDB prompt
	docker compose exec mariadb bash -c 'mariadb -u $$MYSQL_USER -p$$MYSQL_PASSWORD $$MYSQL_DATABASE'

shell-opensearch: ## Open a shell in the OpenSearch container
	docker compose exec opensearch bash

# ── Database / cache ──────────────────────────────────────────────────────────

migrate: ## Run pending Laravel migrations
	docker compose exec laravel php artisan migrate --force

key-generate: ## Generate a new Laravel APP_KEY
	docker compose exec laravel php artisan key:generate

# ── Ollama ────────────────────────────────────────────────────────────────────

ollama-pull: ## Pull the configured Ollama model (requires OLLAMA_ENABLED=true + the ollama profile running)
	docker compose exec ollama ollama pull $(shell grep OLLAMA_MODEL .env | cut -d= -f2 | tr -d ' ' || echo llama3.2)

# ── Admin ─────────────────────────────────────────────────────────────────────

# usage: make admin-promote EMAIL=your@email.com
admin-promote: ## Promote a user to admin role
	docker compose exec laravel php artisan admin:promote "$(EMAIL)"

# ── Document indexing ─────────────────────────────────────────────────────────

docs-status: ## Show document indexing status
	docker compose exec laravel php artisan docs:status

# usage: make docs-import FILE=/path/to/file.txt
docs-import: ## Import a single file into the knowledge base
	docker compose exec laravel php artisan docs:import "$(FILE)"

# usage: make docs-import-dir DIR=/path/to/directory
docs-import-dir: ## Import all documents from a directory
	docker compose exec laravel php artisan docs:import-dir "$(DIR)"

index-docs: ## Re-index all docs from the data/docs directory via Python API
	docker compose exec python-api python -c "from indexer import Indexer; i=Indexer(); print('Indexed:', i.index_directory())"

# ── Deploy (production server) ────────────────────────────────────────────────

deploy: ## Pull latest code and redeploy (run on the server)
	git fetch origin main
	git reset --hard origin/main
	docker compose build
	docker compose up -d
	docker compose exec -T laravel php artisan migrate --force
	docker compose exec -T laravel php artisan config:cache
	docker compose exec -T laravel php artisan route:cache
	docker compose restart nginx
