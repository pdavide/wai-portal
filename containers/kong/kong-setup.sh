#!/bin/sh
# kong-setup.sh

set -e

until $(curl --output /dev/null --silent --head --fail http://localhost:8001/services); do
    sleep 2
done

if ! curl -s http://localhost:8001/services | grep -q "nginx"; then
    curl --output /dev/null --silent -X POST http://localhost:8001/plugins/ --data "name=cors&config.origins=https://localhost"
    curl --output /dev/null --silent -X POST http://localhost:8001/plugins/ --data "name=oauth2&config.enable_client_credentials=true&config.global_credentials=true&config.accept_http_if_already_terminated=true"
    curl --output /dev/null --silent -X POST http://localhost:8001/services --data "name=portal&url=https://nginx/api"
    curl --output /dev/null --silent -X POST http://localhost:8001/services/portal/routes --data "paths[]=/@KONG_API_PORTAL_BASE_PATH@&name=portal"
    curl --output /dev/null --silent -X POST http://localhost:8001/services --data "name=matomo&url=http://express:7080"
    curl --output /dev/null --silent -X POST http://localhost:8001/services/matomo/routes --data "paths[]=/@KONG_API_MATOMO_BASE_PATH@&name=matomo"
fi

echo "Kong plugins and routes are ready"
