# ---------------------------------------------------------------------------
# Atajos para el entorno dockerizado. `make` o `make help` lista todo.
# ---------------------------------------------------------------------------
SHELL := /bin/bash
.DEFAULT_GOAL := help

# Los archivos creados dentro del contenedor deben pertenecerte a ti, no a root.
export UID := $(shell id -u)
export GID := $(shell id -g)

DC  := docker compose
EXE := $(DC) exec app
# Sin TTY: para usar dentro de scripts / CI, donde no hay terminal interactiva.
RUN := $(DC) exec -T app

# Argumentos libres para `make artisan`, `make composer`, `make npm`.
# Evita que make interprete "migrate:status" como un target.
ARGS = $(filter-out $@,$(MAKECMDGOALS))
%:
	@:

.PHONY: help setup env build up down restart rebuild destroy \
        install key migrate fresh seed admin optimize test \
        sh sh-root logs logs-app ps db xdebug-on xdebug-off \
        artisan composer npm

## --------------------------------------------------------------------------
## Puesta en marcha
## --------------------------------------------------------------------------

help: ## Muestra esta ayuda
	@echo ""
	@echo "  Uso: make <target>"
	@echo ""
	@grep -hE '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	  | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'
	@echo ""

setup: ## Instalación completa desde cero (el único comando que necesitas)
	@$(MAKE) --no-print-directory env
	@$(MAKE) --no-print-directory up
	@$(MAKE) --no-print-directory install
	@$(MAKE) --no-print-directory key
	@$(MAKE) --no-print-directory migrate
	@$(MAKE) --no-print-directory seed
	@set -a; . ./.env; set +a; \
	 echo ""; \
	 echo "  ✔ Listo -> http://localhost:$$HTTP_PORT"; \
	 echo "    Admin:  $$(grep -E '^ADMIN_EMAIL=' laravel/.env | cut -d= -f2-)" \
	      "/ $$(grep -E '^ADMIN_PASSWORD=' laravel/.env | cut -d= -f2-)"; \
	 echo "    BD:     localhost:$$DB_PORT  usuario=$$DB_USERNAME  base=$$DB_DATABASE"; \
	 echo ""

env: ## Crea los .env (raíz y laravel) y sincroniza las credenciales entre ambos
	@[ -f .env ] || { cp .env.example .env; echo "  → creado .env (raíz)"; }
	@[ -f laravel/.env ] || { cp laravel/.env.example laravel/.env; echo "  → creado laravel/.env"; }
	@set -a; . ./.env; set +a; \
	 for pair in "APP_NAME=$$APP_NAME" \
	             "APP_URL=http://localhost:$$HTTP_PORT" \
	             "DB_DATABASE=$$DB_DATABASE" \
	             "DB_USERNAME=$$DB_USERNAME" \
	             "DB_PASSWORD=$$DB_PASSWORD"; do \
	   key=$${pair%%=*}; val=$${pair#*=}; \
	   if grep -qE "^$$key=" laravel/.env; then \
	     sed -i "s|^$$key=.*|$$key=$$val|" laravel/.env; \
	   else \
	     echo "$$key=$$val" >> laravel/.env; \
	   fi; \
	 done; \
	 echo "  → credenciales sincronizadas: .env → laravel/.env"

install: ## composer install dentro del contenedor php
	@$(RUN) composer install

key: ## Genera APP_KEY si aún no existe (no rota una llave ya generada)
	@grep -qE '^APP_KEY=.+' laravel/.env \
	  && echo "  → APP_KEY ya existe, se conserva" \
	  || $(RUN) php artisan key:generate

## --------------------------------------------------------------------------
## Contenedores
## --------------------------------------------------------------------------

up: ## Construye (si hace falta) y levanta los contenedores
	@$(DC) up -d --build

down: ## Detiene los contenedores (conserva la base de datos)
	@$(DC) down

restart: ## Reinicia los contenedores
	@$(DC) restart

build: ## Reconstruye la imagen de php
	@$(DC) build app

rebuild: ## Reconstruye la imagen de php desde cero, sin caché
	@$(DC) build --no-cache app
	@$(DC) up -d

destroy: ## Borra contenedores Y la base de datos. Sin vuelta atrás.
	@read -p "  Esto borra la base de datos. ¿Seguro? [s/N] " ok; \
	 [[ "$$ok" == "s" || "$$ok" == "S" ]] && $(DC) down -v || echo "  cancelado"

ps: ## Estado de los contenedores
	@$(DC) ps

logs: ## Logs de todos los contenedores (Ctrl-C para salir)
	@$(DC) logs -f

logs-app: ## Logs de Laravel en vivo
	@$(EXE) php artisan pail --timeout=0

## --------------------------------------------------------------------------
## Base de datos
## --------------------------------------------------------------------------

migrate: ## Corre las migraciones pendientes
	@$(RUN) php artisan migrate --force

fresh: ## Borra las tablas, migra de nuevo y corre los seeders
	@$(RUN) php artisan migrate:fresh --seed --force

seed: ## Corre los seeders (incluye el usuario administrador)
	@$(RUN) php artisan db:seed --force

admin: ## Crea o restablece solo el usuario administrador
	@$(RUN) php artisan db:seed --class=AdminUserSeeder --force

db: ## Abre un cliente mysql contra la base de datos
	@set -a; . ./.env; set +a; \
	 $(EXE) mysql -h db -u "$$DB_USERNAME" -p"$$DB_PASSWORD" "$$DB_DATABASE"

## --------------------------------------------------------------------------
## Día a día
## --------------------------------------------------------------------------

sh: ## Abre una shell dentro del contenedor php
	@$(EXE) bash

sh-root: ## Shell como root dentro del contenedor php (para instalar cosas)
	@$(DC) exec -u root app bash

artisan: ## Ejecuta artisan. Con flags, entrecomilla: make artisan "make:model Wish -m"
	@$(EXE) php artisan $(ARGS)

composer: ## Ejecuta composer. Ej: make composer require laravel/sanctum
	@$(EXE) composer $(ARGS)

npm: ## Ejecuta npm. Ej: make npm install
	@$(EXE) npm $(ARGS)

test: ## Corre la suite de tests
	@$(RUN) php artisan config:clear
	@$(RUN) php artisan test

optimize: ## Limpia todas las cachés de Laravel
	@$(RUN) php artisan optimize:clear

xdebug-on: ## Activa Xdebug (puerto 9003) y reinicia php
	@$(DC) exec -u root app sed -i 's/^xdebug.mode=.*/xdebug.mode=develop,debug/' \
	  /usr/local/etc/php/conf.d/90-xdebug.ini
	@$(DC) restart app
	@echo "  → Xdebug activo. Recuerda que se apaga al reconstruir la imagen."

xdebug-off: ## Desactiva Xdebug y reinicia php
	@$(DC) exec -u root app sed -i 's/^xdebug.mode=.*/xdebug.mode=off/' \
	  /usr/local/etc/php/conf.d/90-xdebug.ini
	@$(DC) restart app
	@echo "  → Xdebug apagado."
