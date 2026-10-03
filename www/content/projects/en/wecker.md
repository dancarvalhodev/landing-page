---
title: Wecker
summary: A web interface inspired by Portainer to control Docker containers visually.
category: Web
technologies: [PHP, Slim, Twig, PHP-DI, PostgreSQL, Redis, Docker]
repository: https://github.com/dancarvalhodev/wecker
order: 1
translation_key: wecker
---

A dashboard with login that lists the machine's containers and lets you start, stop, create and
read the logs of each one, talking to the Docker API. The back end is PHP with Slim 4, PHP-DI
and Twig, with PostgreSQL and Redis, and the whole environment runs on Docker Compose.

The project is still evolving: automated tests, container health status, a web console and
real-time logs are on the roadmap.
