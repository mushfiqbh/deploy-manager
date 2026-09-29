<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Deploy pipelines
    |--------------------------------------------------------------------------
    |
    | Two parallel pipelines of shell commands run from each site's working
    | directory. Lines are chained with `&&` so the deploy aborts on the
    | first failing step. The literal token `{branch}` is substituted with
    | the resolved branch (site branch > `DEPLOY_BRANCH` > `main`).
    |
    | - `commands` is used for "existing" sites (sites that have already been
    |   deployed at least once — `sites.first_deployed = true`). It's the
    |   lightweight update pipeline: pull the latest code, run migrations,
    |   rebuild assets, restart services.
    |
    | - `new_site_commands` is used for the very first deploy of a site
    |   (`sites.first_deployed = false`). It's the full provisioning pipeline:
    |   permissions, composer install, app key, storage link, migrate + seed,
    |   npm build, nginx / php-fpm rewrite configuration, etc.
    |
    | Both pipelines live in code (this file) — not in `.env` — so they
    | are version-controlled and easy to audit.
    */

    'commands' => [
        'git fetch origin',
        'git reset --hard origin/{branch}',
        'composer update',
        'php artisan migrate --force',
        'npm install',
        'npm run build',
        '/etc/init.d/php-fpm-85 restart',
        '/etc/init.d/nginx restart',
        'php artisan optimize:clear',
    ],

    'new_site_commands' => [
        // SQLite database bootstrap (matches the project's default DB_CONNECTION).
        'mkdir -p database',
        'touch database/database.sqlite',
        'chown -R www:www database',
        'find database -type d -exec chmod 775 {} \\;',
        'find database -type f -exec chmod 664 {} \\;',

        // Laravel writable dirs.
        'mkdir -p storage bootstrap/cache',
        'chown -R www:www storage bootstrap/cache',
        'find storage bootstrap/cache -type d -exec chmod 775 {} \\;',
        'find storage bootstrap/cache -type f -exec chmod 664 {} \\;',

        // Composer dependencies (production).
        'composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction',

        // .env + APP_KEY.
        '[ -f .env ] || cp .env.example .env',
        'php artisan key:generate --force',

        // Storage symlink.
        'php artisan storage:link || true',

        // Database.
        'php artisan migrate --force',
        'php artisan db:seed --force',

        // Node deps + build.
        '[ -f package-lock.json ] && npm ci || npm install',
        'npm run build',

        // Nginx vhost: point document root at {site}/public.
        'SITE=$(basename "$PWD"); '
            . 'NCONF="/www/server/panel/vhost/nginx/${SITE}.conf"; '
            . '[ -f "$NCONF" ] && sed -i '
            . '"s#^[[:space:]]*root[[:space:]].*;#    root /www/wwwroot/${SITE}/public;#" '
            . '"$NCONF" || (echo "Error: $NCONF not found" && exit 1)',

        // Nginx rewrite rule for Laravel pretty URLs.
        'SITE=$(basename "$PWD"); '
            . 'RCONF="/www/server/panel/vhost/rewrite/${SITE}.conf"; '
            . 'touch "$RCONF"; '
            . 'grep -qE "^[[:space:]]*location[[:space:]]+/[[:space:]]*\\{" "$RCONF" '
            . '|| cat >> "$RCONF" <<\'NCONF_EOF\'
location / {
    try_files $uri $uri/ /index.php$is_args$query_string;
}
NCONF_EOF',

        // Re-apply permissions to include the new database file/dirs.
        'chown -R www:www storage bootstrap/cache database',
        'find storage bootstrap/cache database -type d -exec chmod 775 {} \\;',
        'find storage bootstrap/cache database -type f -exec chmod 664 {} \\;',

        // Cache reset + warm.
        'php artisan optimize:clear',
        'php artisan optimize',

        // Test nginx config and reload services.
        'nginx -t 2>/dev/null || /www/server/nginx/sbin/nginx -t 2>/dev/null || echo "nginx binary not found, skipping"',
        '[ -x /etc/init.d/php-fpm-85 ] && /etc/init.d/php-fpm-85 restart || echo "php-fpm-85 not found"',
        '[ -x /etc/init.d/nginx ] && /etc/init.d/nginx restart || echo "nginx not found"',
    ],

    'branch' => env('DEPLOY_BRANCH', 'main'),

    'sites_dir' => env('DEPLOY_SITES_DIR', '/www/wwwroot'),

    'demo_domain' => env('DEPLOY_DEMO_DOMAIN', 'demo.barnomala.com'),

    'queue_interval' => (int) env('DEPLOY_QUEUE_INTERVAL', 30),
];
