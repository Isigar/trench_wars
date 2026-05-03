# Trenchwars dev shortcuts. All commands run inside containers (D-021).
# Use: make up | make down | make logs | make shell | make artisan ARGS="migrate"

.PHONY: up down restart logs ps shell build pull \
        composer artisan pnpm npm node \
        pest pint phpstan typescript-transform \
        migrate fresh seed

up:
	docker compose up -d

down:
	docker compose down

restart: down up

logs:
	docker compose logs -f --tail=200

ps:
	docker compose ps

build:
	docker compose build

pull:
	docker compose pull

# ─── Web container (Laravel) ────────────────────────────────────────────
shell:
	docker compose exec web bash

composer:
	docker compose exec web composer $(ARGS)

artisan:
	docker compose exec web php artisan $(ARGS)

pnpm:
	docker compose exec web pnpm $(ARGS)

npm:
	docker compose exec web npm $(ARGS)

node:
	docker compose exec web node $(ARGS)

# ─── Quality gates ──────────────────────────────────────────────────────
pest:
	docker compose exec web ./vendor/bin/pest $(ARGS)

pint:
	docker compose exec web ./vendor/bin/pint $(ARGS)

phpstan:
	docker compose exec web ./vendor/bin/phpstan analyse $(ARGS)

# ─── DB shortcuts ───────────────────────────────────────────────────────
migrate:
	docker compose exec web php artisan migrate

fresh:
	docker compose exec web php artisan migrate:fresh --seed

seed:
	docker compose exec web php artisan db:seed

typescript-transform:
	docker compose exec web php artisan typescript:transform
