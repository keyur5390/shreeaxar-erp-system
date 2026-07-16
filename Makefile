.PHONY: up down build logs shell migrate seed fresh prod-up prod-down prod-build

up:
	docker compose --env-file .env.docker up --build -d

down:
	docker compose down

build:
	docker compose build

logs:
	docker compose logs -f

shell:
	docker compose exec app sh

artisan:
	docker compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

migrate:
	docker compose exec app php artisan migrate --force

seed:
	docker compose exec app php artisan db:seed --force

fresh:
	docker compose exec app php artisan migrate:fresh --seed --force

prod-build:
	docker compose -f docker-compose.prod.yml --env-file .env.docker build

prod-up:
	docker compose -f docker-compose.prod.yml --env-file .env.docker up --build -d

prod-down:
	docker compose -f docker-compose.prod.yml down

%:
	@:
