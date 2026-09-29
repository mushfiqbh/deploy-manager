<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About this project

This is a Laravel 12 + Blade + SQLite dashboard for managing multiple Laravel
site deployments. It replaces the previous standalone PHP/HTML tool that used
to live under `deploy/`.

### Features

- **SSO login** — a single admin user is auto-provisioned from the tenant SSO
  portal. The HMAC-signed payload is validated against `CLIENT_API_KEY`.
- **Dashboard** — add a domain + site type (**New** or **Existing**); path and
  branch are derived from `DEPLOY_SITES_DIR` and `DEPLOY_BRANCH` automatically.
  See site state (pending / running / ok / error), current deployed version,
  and last deploy time.
- **Inline pipeline** — every deploy runs an inline shell pipeline from each
  site's working directory via `Symfony\Component\Process` (no external
  `/usr/local/bin/up` dependency). Pipelines are configured in
  `config/deploy.php`:
  - `commands` — used for **existing sites** (already deployed at least
    once). Lightweight update: `git fetch origin && git reset --hard
    origin/{branch} && composer update && php artisan migrate --force &&
    npm install && npm run build && /etc/init.d/php-fpm-85 restart &&
    /etc/init.d/nginx restart && php artisan optimize:clear`.
  - `new_site_commands` — used for **new sites** (first deploy). Full
    provisioning: permissions, `composer install`, app key, storage
    symlink, migrate + seed, npm build, nginx + php-fpm rewrite rules,
    cache reset, etc.
- **First deploy vs update** — `DeployService` picks the right pipeline
  based on `sites.first_deployed`. The flag is preserved for the UI and
  flips to `true` automatically after the first successful deploy.
- **Staged "Deploy All"** — runs `demo.barnomala.com` synchronously first;
  once you confirm, every other site is dispatched as a queued
  `DeploySiteJob` spaced `DEPLOY_QUEUE_INTERVAL` seconds apart via
  `Bus::chain()`.
- **Version tracking** — records the git HEAD short-sha before and after each
  run so you can audit what was actually deployed.
- **Per-site history** — paginated list of past deploys with exit code and
  full log output.

### Configuration (`.env`)

```dotenv
APP_NAME=Deploy Manager
DB_CONNECTION=sqlite

CLIENT_API_KEY=replace-me-with-shared-secret
CLIENT_SSO_PORTAL=https://cloud.barnomala.com/sign-in-with-barnomala/

# Paths
DEPLOY_SITES_DIR=/www/wwwroot
DEPLOY_BRANCH=main

# "Deploy All" workflow
DEPLOY_DEMO_DOMAIN=demo.barnomala.com
DEPLOY_QUEUE_INTERVAL=30
```

### Routes

| Method | URL                          | Name                 |
|--------|------------------------------|----------------------|
| GET    | `/`                          | (redirect)           |
| GET    | `/login`                     | `login`              |
| POST   | `/logout`                    | `logout`             |
| GET    | `/dashboard`                 | `dashboard`          |
| GET    | `/sites`                     | `sites.index`        |
| POST   | `/sites`                     | `sites.store`        |
| GET    | `/sites/{site}`              | `sites.show`         |
| DELETE | `/sites/{site}`              | `sites.destroy`      |
| POST   | `/sites/{site}/deploy`       | `deploy.one`         |
| POST   | `/deploy-all`                | `deploy.all`         |
| GET    | `/deploy-all/confirm`        | `deploy.confirm`     |
| POST   | `/deploy-all/confirm`        | `deploy.all.confirm` |

### How deploys work

`App\Services\DeployService::deploySite($site)`:

1. Reads `version_before` via `git rev-parse --short HEAD`.
2. Resolves the branch (`site->branch` > `DEPLOY_BRANCH` > `main`) and
   substitutes `{branch}` into the configured commands.
3. Sets `site.state = running` and opens a `DeploymentLog` row with
   `kind = update`.
4. Spawns `bash -c "<cmd1> && <cmd2> && …"` from `site->path` via
   `Process` (no timeout). The default pipeline mirrors the old
   `/usr/local/bin/up` behaviour.
5. Captures combined stdout + stderr.
6. Reads `version_after`, marks the log + site `success` / `error`.
7. On the first successful deploy, flips `first_deployed = true` so the
   UI badge changes from `first` to `update` for subsequent runs.
8. Stores `exit_code`, `started_at`, `finished_at`, `user_id`.

### "Deploy All" flow

1. `POST /deploy-all` looks up `demo.barnomala.com` (configurable via
   `DEPLOY_DEMO_DOMAIN`) and runs `DeployService::deploySite()` for it
   synchronously.
2. The user is redirected to `GET /deploy-all/confirm` which lists every
   other site with its pending kind (`deploy.sh` or `up.sh`).
3. `POST /deploy-all/confirm` builds a `Bus::chain()` of `DeploySiteJob`s,
   each delayed by `DEPLOY_QUEUE_INTERVAL` seconds, and dispatches it.
4. The queue worker (`php artisan queue:work`) picks up the jobs in order,
   running them every 30 seconds (by default).

Make sure your queue worker is running on the server, e.g. via Supervisor.

### SSO flow

1. Unauthenticated visit to any protected route → 302 to `/login`.
2. `/login` without `?payload` / `?signature` redirects to the portal:
   `CLIENT_SSO_PORTAL?redirect_uri=<this app's /login URL>`.
3. The portal signs the user and redirects back with
   `?payload=<base64>&signature=<hmac-sha256>`.
4. `AuthController@login` verifies the HMAC with `CLIENT_API_KEY`, decodes
   `expires_at`, finds or creates the admin user, and calls `Auth::login()`.

The old `deploy/api.php`, `deploy/index.html`, `deploy/sites.conf`, and
`deploy/package.json` have been removed; their functionality is now provided
by Eloquent models, Blade views, and the `DeployService`.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
