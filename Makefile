# ─────────────────────────────────────────────────────────────────────────────
# TOTPVault — Makefile shortcuts for Docker Compose
# ─────────────────────────────────────────────────────────────────────────────

.PHONY: up down logs rebuild shell db-shell status

## Start all services in the background
up:
	docker compose up -d --build

## Stop all services
down:
	docker compose down

## Follow application logs
logs:
	docker compose logs -f app

## Rebuild images and restart
rebuild:
	docker compose down
	docker compose build --no-cache
	docker compose up -d

## Open a bash shell inside the app container
shell:
	docker compose exec app bash

## Open a MySQL shell inside the database container
db-shell:
	docker compose exec db mariadb -u "$${DB_USER:-totpvault}" -p"$${DB_PASSWORD}" "$${DB_NAME:-totpvault}"

## Show container status
status:
	docker compose ps
