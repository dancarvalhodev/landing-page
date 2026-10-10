#!/bin/sh
set -eu

case "$(uname -m)" in
    x86_64) arch=x64 ;;
    aarch64) arch=arm64 ;;
    *) echo "arquitetura não suportada: $(uname -m)" >&2; exit 1 ;;
esac

url="https://github.com/tailwindlabs/tailwindcss/releases/download/v${TAILWIND_VERSION}/tailwindcss-linux-${arch}-musl"

wget -qO /usr/local/bin/tailwindcss "$url"
chmod +x /usr/local/bin/tailwindcss
