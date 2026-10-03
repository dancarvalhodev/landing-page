---
title: Ansible Lab
summary: Automação com Ansible que configura do zero meu servidor pessoal em Debian 13.
category: Infraestrutura
technologies: [Ansible, Debian, Nginx, AWS S3]
repository: https://github.com/dancarvalhodev/ansible-lab
order: 3
translation_key: ansible-lab
---

Um único comando prepara o servidor: Nginx com certificado, Syncthing e a integração com um
bucket S3. Dados sensíveis, como endereços, domínios e chaves da AWS, ficam criptografados
com Ansible Vault, então o repositório pode ser público sem expor nada.
