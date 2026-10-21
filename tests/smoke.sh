#!/bin/sh
# Smoke test through the real front controller (public/index.php + symfony/runtime),
# which the PHPUnit kernel tests bypass. Catches PHP notices/deprecations that end up
# in the HTTP body (the Miniserver parses that body). Usage: tests/smoke.sh [prod|dev]
set -eu
ENV="${1:-prod}"
PORT="${SMOKE_PORT:-8087}"
cd "$(dirname "$0")/.."

APP_ENV="$ENV" php bin/console cache:clear --env="$ENV" >/dev/null
APP_ENV="$ENV" php -S 127.0.0.1:"$PORT" -t public public/index.php >/tmp/smoke-server.log 2>&1 &
PID=$!
trap 'kill $PID 2>/dev/null || true' EXIT
for _ in 1 2 3 4 5 6 7 8 9 10; do
    curl -s -o /dev/null "http://127.0.0.1:$PORT/health" && break
    sleep 0.5
done

BODY=$(curl -s "http://127.0.0.1:$PORT/health")
if [ "$BODY" != "healty" ]; then
    echo "FAIL ($ENV): /health body is not exactly 'healty':"
    echo "$BODY" | head -20
    exit 1
fi
echo "OK ($ENV): /health body clean"
