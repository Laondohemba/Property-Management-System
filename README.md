# PMS: Property Management System

A web-based property management system for hotels and other hospitality venues, designed to grow into residential and commercial portfolios. It brings front office, reservations, inventory and rates, housekeeping, maintenance, billing and reporting together in one application.

> **Status:** early development (Phase 0: Foundation). See the [roadmap](docs/ROADMAP.md) for what is built and what comes next.

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
- **Consistent errors.** Failures are returned as RFC 9457 `application/problem+json` with stable error codes and field-level validation errors.
- **Modular domains.** Backend code lives in `app/Modules/*` (Identity, Property, Reporting, …), with shared infrastructure in `app/Support`.

UI styling uses Tailwind CSS with Angular CDK, following the NiceAdmin visual language.

## Requirements

- PHP 8.3+ with Composer
- Node.js 22.12+ and npm
- MySQL 8
- Redis (5+ for local development; 7+ for deployed environments)

On Windows, [Laragon](https://laragon.org) provides PHP, MySQL and Redis.

## Getting started

```bash
git clone <repository-url> pms && cd pms

composer install
cp .env.example .env
php artisan key:generate

# Configure DB_* and REDIS_* in .env, make sure MySQL and Redis are running, then:
php artisan migrate --seed

npm install
npm --prefix resources/angular ci
```

Start everything (Laravel API, queue worker and the Angular dev server):

```bash
npm run dev
```

Open **http://localhost:4200**. The Angular dev server proxies `/bff`, `/login`, `/sanctum` and the other server routes to Laravel on port 8000, so the browser sees a single origin.

> Use `localhost:4200`, not `127.0.0.1:8000`, during development: sessions are only accepted from the origins listed in `SANCTUM_STATEFUL_DOMAINS`.

### Accounts

`php artisan migrate --seed` creates local demo data (never in production). Every demo user has the password `password`:

| Email | Access |
|---|---|
| `admin@example.com` | Super Admin, the whole demo organisation |
| `frontdesk@example.com` | Front Desk Agent at *Demo Grand Hotel* |

To create a real organisation and its first administrator:

```bash
php artisan pms:create-super-admin
```

There is no public sign-up; further staff will be invited from inside the application.

## Scripts

| Command | What it does |
|---|---|
| `npm run dev` | Runs Laravel (`:8000`), the queue worker and Angular (`:4200`) together |
| `npm run build` | Builds the Angular app for production and publishes it into `public/` |
| `npm test` | Runs the Angular unit tests (Karma + Jasmine, headless Chrome) |
| `php artisan test` | Runs the backend test suite (PHPUnit) |
| `vendor/bin/pint` | Formats PHP code |

After `npm run build`, `php artisan serve` serves the compiled SPA and the BFF from one origin, the same way Nginx does in production.

## Project structure

```
app/
  Modules/            Domain modules (Identity, Property, Reporting, …)
  Support/            Shared infrastructure (tenancy, property context, problem+json)
routes/
  bff/                Private BFF endpoints, one file per module
  web.php             SPA entry point and deep-link fallback
database/             Migrations, factories, seeders
resources/angular/    Angular workspace (core/, layout/, shared/, features/)
docs/                 Roadmap and project documentation
tests/                Backend feature tests
```

## Author

Developed by **Luper Aondohemba**: [luper.dev](https://luper.dev)
