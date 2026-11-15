# PMS: Property Management System

A web-based property management system for hotels and other hospitality venues, designed to grow into residential and commercial portfolios. It brings front office, reservations, inventory and rates, housekeeping, maintenance, billing and reporting together in one application.

> **Status:** Phase 0 (Foundation) complete; Phase 1 (core PMS) in progress, starting with inventory. See the [roadmap](docs/ROADMAP.md) for what is built and what comes next.

## Architecture

PMS is a **Majestic Monolith**: one repository and one deployable application, scaled by running the same image in different roles rather than by splitting it into microservices. The stack follows the **PALM-B** pillars:

| Pillar | Technology | Role |
|---|---|---|
| **P**HP | PHP 8.3+ | Typed backend domains and background jobs |
| **A**ngular | Angular 21 (standalone components, signals) | Single-page staff workspace |
| **L**aravel | Laravel 13 | Queues, ORM, authentication, scheduling |
| **M**ySQL | MySQL 8 | Primary database |
| **B**uffer | Redis | Sessions, cache, queues and locks |

Key design choices:

- **Private Backend-for-Frontend.** The Angular app talks only to view-shaped JSON endpoints under `/bff`, served from the same origin. There is no public REST API for the SPA and no CORS.
- **Cookie sessions, no browser tokens.** Staff sign in with an HttpOnly session cookie and CSRF protection (Laravel Fortify + Sanctum SPA mode), with optional TOTP two-factor authentication.
- **Multi-tenant, multi-property.** Every business record belongs to an organisation. Staff hold a role per property, and the SPA sends the active property in an `X-Property-Id` header that is validated on every request.
- **Tamper-evident audit trail.** Data changes and security events are recorded with who, what, when and where. Entries are append-only (database triggers) and hash-chained; `php artisan audit:verify` detects tampering.
- **Generated API types.** Every BFF response is a typed DTO (spatie/laravel-data). `composer types` generates the matching TypeScript types into `resources/angular/src/app/core/bff/generated/bff.ts`, and a backend test fails if the committed file is out of date.
- **Consistent errors.** Failures are returned as RFC 9457 `application/problem+json` with stable error codes and field-level validation errors.
- **Modular domains.** Backend code lives in `app/Modules/*` (Identity, Property, Inventory, Reporting, …), with shared infrastructure in `app/Support`.
- **Stateless pods, private media.** Uploaded files (such as room-type images) live on a private media disk: S3-compatible storage in deployed environments, the local disk in development (`MEDIA_DISK`). Browsers only ever receive signed, expiring URLs to resized WebP versions; originals and their metadata are never served. Image versions are built on the queue.
- **One image, four roles.** The same container image runs as web pods (Nginx + PHP-FPM), Horizon queue workers, a single scheduler and, later, Reverb WebSockets. Migrations run once per release in a Kubernetes Job, and every schema change follows the expand/contract pattern so rollouts and rollbacks never break running pods. See [k8s/README.md](k8s/README.md).

UI styling uses Tailwind CSS with Angular CDK, following the NiceAdmin visual language.

## Requirements

- PHP 8.3+ with Composer
- Node.js 24 LTS (22.13+ also works) and npm 11.6.1 (`npm install -g npm@11.6.1`, the version pinned in `resources/angular/package.json`)
- MySQL 8 (8.4 LTS in CI)
- Redis (5+ for local development; 8 in CI and deployed environments)
- Docker, only to build or run the production image

On Windows, [Laragon](https://laragon.org) provides PHP, MySQL and Redis.

## Getting started

```bash
git clone <repository-url> pms && cd pms

composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* and REDIS_* in .env, make sure MySQL and Redis are running, then:
php artisan migrate --seed
php artisan inventory:extend   # fills two years of inventory for the demo room types

npm install
npm --prefix resources/angular ci
```

Start everything (Laravel API, queue worker and the Angular dev server):

```bash
npm run dev
```

Open **http://localhost:4200**. The Angular dev server proxies `/bff`, `/login`, `/sanctum` and the other server routes to Laravel on port 8000, so the browser sees a single origin.

> Use `localhost:4200`, not `127.0.0.1:8000`, during development: sessions are only accepted from the origins listed in `SANCTUM_STATEFUL_DOMAINS`.

> **Redis must be running** (sessions, cache and queues use it). If a page shows `Connection refused [tcp://127.0.0.1:6379]`, start Redis from Laragon (*Menu → Redis*, or tick it under *Preferences → Services* so **Start All** includes it). The queue worker in `npm run dev` also builds the full-size room-type images; until it has run, the gallery shows the thumbnail.

### Accounts

`php artisan migrate --seed` creates local demo data (never in production). Every demo user has the password `password`:

| Email | Access |
|---|---|
| `admin@example.com` | Super Admin, the whole demo organisation |
| `frontdesk@example.com` | Front Desk Agent at *Demo Grand Hotel* |

The demo property comes with three room types, eight amenities and 20 rooms on two floors.

To create a real organisation and its first administrator:

```bash
php artisan pms:create-super-admin
```

There is no public sign-up. Staff are invited from **Settings → Users**; the invitation e-mail contains a link to set a password. With the default `MAIL_MAILER=log`, e-mails are written to `storage/logs/laravel.log`, and they are sent by the queue worker that `npm run dev` starts.

## Scripts

| Command | What it does |
|---|---|
| `npm run dev` | Runs Laravel (`:8000`), the queue worker and Angular (`:4200`) together |
| `npm run build` | Builds the Angular app for production and publishes it into `public/` |
| `npm test` | Runs the Angular unit tests (Karma + Jasmine, headless Chrome) |
| `php artisan test` | Runs the backend test suite (PHPUnit) |
| `php artisan audit:verify` | Checks that the audit trail hash chains are intact |
| `php artisan inventory:extend` | Creates missing inventory rows up to the horizon (idempotent; also runs daily) |
| `composer types` | Regenerates the TypeScript types for the BFF contract after a DTO or enum changes |
| `composer check` | Pint (style), Larastan level 8 (static analysis) and the full backend test suite |
| `npm --prefix resources/angular run lint` | ESLint for the Angular app |
| `vendor/bin/pint` | Formats PHP code |
| `docker build -t pms:<sha> .` | Builds the production image (Angular compiled in a Node stage, PHP 8.3 FPM + Nginx runtime) |
| `php artisan horizon` | Runs the queue workers locally (Linux/macOS; on Windows use `php artisan queue:work`) |

After `npm run build`, `php artisan serve` serves the compiled SPA and the BFF from one origin, the same way Nginx does in production.

## Quality checks

Every pull request runs the [CI workflow](.github/workflows/ci.yml):

- **Backend:** Pint, Larastan (level 8), and the PHPUnit suite against MySQL 8 and Redis 7. The suite includes architecture tests (module boundaries, strict types, layering) and fails if the generated TypeScript types are out of date. Composer dependency audit.
- **Frontend:** ESLint, unit tests in headless Chrome, a production build and an npm dependency audit.
- **Container and manifests:** the image is built, scanned for HIGH/CRITICAL vulnerabilities (Trivy) and smoke-tested as a non-root, read-only container; every Kustomize overlay is rendered and validated (kubeconform) and may not deploy an untagged or `:latest` image.
- **Migrations:** every migration is labelled `@migration expand` or `@migration contract`, and expand migrations may not drop, rename, change columns or add NOT NULL columns without defaults (spec §4.3).

Run `composer check` and `npm --prefix resources/angular run lint` locally before pushing.

## Project structure

```
app/
  Modules/            Domain modules (Identity, Property, Inventory, Reporting, …)
  Support/            Shared infrastructure (tenancy, property context, problem+json)
  Modules/*/DTOs/     Typed BFF response shapes, the source of the generated TypeScript types
routes/
  bff/                Private BFF endpoints, one file per module
  web.php             SPA entry point and deep-link fallback
database/             Migrations, factories, seeders
resources/angular/    Angular workspace (core/, layout/, shared/, features/)
docs/                 Roadmap and project documentation
docker/               Nginx, PHP-FPM, Supervisor and PHP config, image entrypoint
k8s/                  Kustomize base, release Job, realtime component, staging/production overlays
tests/                Backend feature and architecture tests
.github/workflows/    CI pipeline
Dockerfile            Multi-stage production image
```

## Author

Developed by **Luper Aondohemba**: [luper.dev](https://luper.dev)
