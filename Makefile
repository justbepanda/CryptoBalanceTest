.PHONY: init up stop logs restart shell reseed test clear-docker

DCSERVICE=app

# Установка
init: prepare-env build install-deps migrate-f
	docker compose exec ${DCSERVICE} php artisan storage:link
	@echo "Project is ready at http://localhost:8000"

# Подготовка конфига (если его нет)
prepare-env:
	@test -f .env || cp .env.example .env
	@echo ".env file prepared"

# Сборка и запуск
build:
	docker compose up -d --build

# Установка зависимостей
install-deps:
	docker compose exec ${DCSERVICE} composer install
	docker compose exec ${DCSERVICE} php artisan key:generate

migrate-f:
	docker compose exec ${DCSERVICE} php artisan optimize:clear
	docker compose exec ${DCSERVICE} php artisan migrate:fresh --seed

up:
	docker compose up -d
	@echo "Project is ready at http://localhost:8000"

stop:
	docker compose stop

logs:
	docker compose logs -f

shell:
	docker compose exec ${DCSERVICE} bash

reseed:
	docker compose exec ${DCSERVICE} php artisan migrate:fresh --seed

restart:
	docker compose restart $(DCSERVICE)

test:
	docker compose exec ${DCSERVICE} php artisan test

clear-dc:
	docker system prune -f
