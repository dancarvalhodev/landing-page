---
title: Docker templates for PHP
summary: Ready-to-use PHP images for development and a full environment with Nginx, PHP, MySQL and Redis.
category: DevOps
technologies: [Docker, Docker Compose, PHP, Nginx, MySQL, Redis]
repository: https://github.com/dancarvalhodev/dockerfile-php-alpine
order: 2
translation_key: templates-docker-php
---

Two repositories I use as the starting point for PHP projects:

- [dockerfile-php-alpine](https://github.com/dancarvalhodev/dockerfile-php-alpine): PHP-FPM
  images on Alpine, from version 8.3 to 8.5, with Composer, Xdebug, Redis and the most used
  extensions. Versions up to 8.4 are published on Docker Hub, including an ARM variant.
- [docker-nginx](https://github.com/dancarvalhodev/docker-nginx): an environment with Nginx, PHP,
  MySQL and Redis on Docker Compose, configured through a `.env` file.

This website was born from these templates.
