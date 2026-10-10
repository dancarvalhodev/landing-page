FROM alpine:3

ARG TAILWIND_VERSION=4.3.3

RUN apk add --no-cache libstdc++ libgcc
COPY install.sh /tmp/install-tailwind.sh
RUN sh /tmp/install-tailwind.sh && rm /tmp/install-tailwind.sh

WORKDIR /app

ENTRYPOINT ["tailwindcss", "-i", "resources/css/app.css", "-o", "public/assets/css/app.css"]
CMD ["--watch=always"]
