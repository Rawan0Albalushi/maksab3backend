#!/bin/sh
set -eu

cd /var/www/html

PORT="${PORT:-8080}"
sed "s/__PORT__/${PORT}/" /etc/nginx/site.conf.template > /etc/nginx/sites-available/default

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwx storage bootstrap/cache

if [ ! -e public/storage ]; then
    php artisan storage:link || true
fi

php artisan package:discover --ansi || true

if [ -n "${DB_HOST:-}" ] && [ -n "${DB_DATABASE:-}" ] && [ -n "${DB_USERNAME:-}" ]; then
    umask 077
    cat > /tmp/mysql-client.cnf <<EOF
[client]
host=${DB_HOST}
port=${DB_PORT:-3306}
user=${DB_USERNAME}
password=${DB_PASSWORD:-}
EOF

    echo "Waiting for MySQL..."
    ready=0
    i=0
    while [ "$i" -lt 45 ]; do
        if mysqladmin --defaults-extra-file=/tmp/mysql-client.cnf ping --silent; then
            ready=1
            break
        fi
        i=$((i + 1))
        sleep 2
    done

    if [ "$ready" -eq 1 ]; then
        tables="$(mysql --defaults-extra-file=/tmp/mysql-client.cnf -N -e "SHOW TABLES LIKE 'admins';" "${DB_DATABASE}" || true)"
        if [ -z "$tables" ]; then
            echo "Importing initial schema..."
            mysql --defaults-extra-file=/tmp/mysql-client.cnf "${DB_DATABASE}" < installation/backup/database.sql
        fi

        if [ ! -d storage/app/public/product ] && [ -f installation/backup/public.zip ]; then
            echo "Extracting default public files..."
            unzip -q -o installation/backup/public.zip -d storage/app
            chown -R www-data:www-data storage/app
        fi

        php artisan migrate --force || echo "Migrations did not finish cleanly; continuing startup."
    else
        echo "MySQL was not reachable. Starting the web server anyway."
    fi

    rm -f /tmp/mysql-client.cnf
fi

php-fpm -D
exec nginx -g "daemon off;"
