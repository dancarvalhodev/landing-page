---
title: Templates Docker para PHP
summary: Imagens PHP prontas para desenvolvimento e um ambiente completo com Nginx, PHP, MySQL e Redis.
category: DevOps
technologies: [Docker, Docker Compose, PHP, Nginx, MySQL, Redis]
repository: https://github.com/dancarvalhodev/dockerfile-php-alpine
order: 2
translation_key: templates-docker-php
---

Dois repositórios que uso como ponto de partida de projetos PHP:

- [dockerfile-php-alpine](https://github.com/dancarvalhodev/dockerfile-php-alpine): imagens
  PHP-FPM em Alpine, das versões 8.3 a 8.5, com Composer, Xdebug, Redis e as extensões mais usadas.
  As versões até a 8.4 estão publicadas no Docker Hub, incluindo uma variante para ARM.
- [docker-nginx](https://github.com/dancarvalhodev/docker-nginx): ambiente com Nginx, PHP,
  MySQL e Redis em Docker Compose, configurado por um arquivo `.env`.

Este site nasceu desses templates.
