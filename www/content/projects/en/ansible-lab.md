---
title: Ansible Lab
summary: Ansible automation that sets up my personal Debian 13 server from scratch.
category: Infrastructure
technologies: [Ansible, Debian, Nginx, AWS S3]
repository: https://github.com/dancarvalhodev/ansible-lab
order: 3
translation_key: ansible-lab
---

A single command prepares the server: Nginx with a certificate, Syncthing and the integration
with an S3 bucket. Sensitive data, such as addresses, domains and AWS keys, is encrypted with
Ansible Vault, so the repository can be public without exposing anything.
