FROM caddy:2-alpine AS dev

WORKDIR /app

FROM alpine:3 AS assets

# Mesma versão de bin/tailwind/Dockerfile; ao atualizar uma, atualize a outra.
ARG TAILWIND_VERSION=4.3.3

RUN apk add --no-cache libstdc++ libgcc
COPY bin/tailwind/install.sh /tmp/install-tailwind.sh
RUN sh /tmp/install-tailwind.sh && rm /tmp/install-tailwind.sh

WORKDIR /app

COPY www/ ./
RUN tailwindcss -i resources/css/app.css -o public/assets/css/app.css --minify

FROM dev AS prod

COPY config/caddy/Caddyfile /etc/caddy/Caddyfile
COPY www/public/ ./public/
COPY --from=assets /app/public/assets/css/app.css ./public/assets/css/app.css
