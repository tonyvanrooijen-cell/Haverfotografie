#!/usr/bin/env bash
set -euo pipefail

cd -- "$(dirname -- "${BASH_SOURCE[0]}")"
: "${APP_IMAGE:?APP_IMAGE must point to the published GHCR image}"
export APP_IMAGE

docker compose -f docker-compose.prod.yml config --quiet
docker compose -f docker-compose.prod.yml pull web
docker compose -f docker-compose.prod.yml up -d --no-build --wait --wait-timeout 120 web
docker compose -f docker-compose.prod.yml ps
