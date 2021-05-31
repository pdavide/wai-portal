#!/bin/sh
# kong-start.sh

set -e

echo "Running Kong migrations..."
kong migrations bootstrap

echo "Starting Kong..."
exec /docker-entrypoint.sh kong docker-start
