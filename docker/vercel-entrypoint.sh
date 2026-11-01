#!/bin/sh
set -eu

mkdir -p "${VIEW_COMPILED_PATH:-/tmp/linkstack/views}" \
    /tmp/linkstack/env-backups \
    "${XDG_CONFIG_HOME:-/tmp/caddy/config}" \
    "${XDG_DATA_HOME:-/tmp/caddy/data}"

exec frankenphp run --config /etc/caddy/Caddyfile
