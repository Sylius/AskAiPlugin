#!/bin/sh
set -eu

cd /app

console() { php vendor/bin/console --no-debug "$@"; }

echo "Waiting for the database..."
until console dbal:run-sql 'SELECT 1' >/dev/null 2>&1; do
    sleep 2
done

if [ "${1:-}" = "init" ]; then
    console doctrine:database:create --if-not-exists -n
    console doctrine:migrations:migrate -n --allow-no-migration

    admins="$(console dbal:run-sql 'SELECT COUNT(*) AS admins FROM sylius_admin_user' | grep -Eo '[0-9]+' | tail -1)"
    if [ "${admins:-0}" = "0" ]; then
        # The fixtures need the Alice bundles, registered in the dev and test environments only.
        echo "Loading fixtures..."
        APP_ENV=dev console sylius:fixtures:load -n
    fi

    # The assistant calls the Admin API as the chatting administrator, who therefore needs ROLE_API_ACCESS.
    console dbal:run-sql "UPDATE sylius_admin_user SET roles = JSON_ARRAY_APPEND(roles, '\$', 'ROLE_API_ACCESS') WHERE NOT JSON_CONTAINS(roles, '\"ROLE_API_ACCESS\"')" >/dev/null

    console cache:clear

    shift
fi

exec "$@"
