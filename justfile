set dotenv-load

# Ids do usuário do host, lidos pelo docker-compose.yml: php e tailwind rodam com eles
export HOST_UID := `id -u`
export HOST_GID := `id -g`

[private]
default:
    @just --list --unsorted

# Primeira vez: setup + up + composer install
[group('ambiente')]
install: setup up (composer "install --no-interaction")
    @echo "✔ Instalação concluída: http://localhost:${PORT_CADDY:-8080}"

# Cria o .env a partir do env-example
[group('ambiente')]
setup:
    #!/usr/bin/env bash
    set -euo pipefail
    [ -f .env ] || { cp env-example .env; echo "✔ .env criado"; }
    echo "✔ Setup concluído"

# Sobe os contêineres (com build)
[group('ambiente')]
up: _docker _env
    docker compose up -d --build
    @echo "🌐 http://localhost:${PORT_CADDY:-8080}"

# Derruba os contêineres
[group('ambiente')]
stop: _docker _env
    docker compose down

# Mostra o estado dos contêineres
[group('ambiente')]
status: _docker _env
    docker compose ps

# Acompanha os logs (padrão: todos os contêineres)
[group('ambiente')]
logs service="": _docker _env
    docker compose logs -f {{ service }}

# Abre um shell em um contêiner (padrão: php)
[group('ambiente')]
shell service="php": (_running service)
    docker compose exec {{ service }} sh

# Reinicia o Caddy para carregar o Caddyfile (a API de administração está desligada)
[group('ambiente')]
restart-caddy: (_running "caddy")
    docker compose restart caddy

# Roda o Composer no contêiner php
[group('php')]
composer *args: (_running "php")
    docker compose exec php composer {{ args }}

# Roda os testes (filtro opcional pelo nome do teste)
[group('php')]
test filter="": (_running "php")
    docker compose exec php vendor/bin/phpunit {{ if filter == "" { "" } else { "--filter " + quote(filter) } }}

# Análise estática (PHPStan)
[group('php')]
stan: (_running "php")
    docker compose exec php composer stan

# Confere o estilo do código (PHP-CS-Fixer)
[group('php')]
cs: (_running "php")
    docker compose exec php composer cs

# Corrige o estilo do código (PHP-CS-Fixer)
[group('php')]
cs-fix: (_running "php")
    docker compose exec php composer cs:fix

# As três conferências que o CI roda: estilo, análise e testes
[group('ci')]
check: cs stan test

# Valida os workflows do GitHub Actions
[group('ci')]
actionlint: _docker
    docker run --rm -v .:/repo:ro --workdir /repo rhysd/actionlint:latest

_docker:
    @command -v docker > /dev/null || { echo "✖ docker não encontrado no PATH." >&2; exit 1; }
    @docker compose version > /dev/null 2>&1 || { echo "✖ docker compose v2 não encontrado." >&2; exit 1; }
    @docker info > /dev/null 2>&1 || { echo "✖ O Docker não respondeu. Ele está rodando?" >&2; exit 1; }

_env:
    @[ -f .env ] || { echo "✖ .env não encontrado. Rode: just setup" >&2; exit 1; }

_running service: _docker _env
    @[ -n "$(docker compose ps --status running --quiet {{ service }})" ] || { echo "✖ O contêiner '{{ service }}' não está rodando. Rode: just up" >&2; exit 1; }
