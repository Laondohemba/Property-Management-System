# Roadmap

Delivery follows four phases. Each phase is built in small, reviewable steps; a step is ticked when it is merged with passing tests.

**Legend:** ✅ done · 🚧 in progress · ⬜ not started

## Phase 0: Foundation 🚧

Platform, security and setup that every later module builds on.

| Step | Scope | Status |
|---|---|---|
| 0.1 | **Platform and authentication.** Laravel 13 + Angular 21 monolith, private BFF (`/bff`), cookie-session auth with CSRF (Fortify + Sanctum SPA mode), TOTP two-factor, password policy, problem+json errors, Redis for sessions, cache and queues | ✅ |
| 0.2 | **Properties and property context.** Row-level organisation tenancy, properties, roles assigned per property, `X-Property-Id` validation, default permission matrix, property settings screens, dashboard endpoint | ✅ |
| 0.3 | **Rooms and room types.** Room types with capacity, bed configuration and amenities; rooms with floors, sort order, bulk range creation and housekeeping/occupancy status fields; amenity catalogue; dashboard room counts | ✅ |
| 0.4 | **Users and roles administration.** Staff invitations with set-password links, activate/deactivate, Super Admin management, roles per property, custom roles with a permission matrix editor, 2FA enforceable by role | ✅ |
| 0.5 | **Generated BFF types.** Every BFF response is a typed DTO; TypeScript types are generated from them (`composer types`) and a backend test fails on drift | ✅ |
| 0.6 | **Audit logging base.** Who/what/when/where with before/after values for settings, users and roles; security events (sign-ins, failures, lockouts, 2FA, passwords, invitations, permission changes); append-only, hash-chained trail with `audit:verify`; basic read-only viewer | ✅ |
| 0.7 | **Quality gates.** Architecture tests for module boundaries, static analysis, linting, CI pipeline | ⬜ |
| 0.8 | **Containers and Kubernetes.** Multi-stage image, Nginx + PHP-FPM, web/worker/scheduler roles, Horizon, release job, zero-downtime rollout | ⬜ |
| 0.9 | **Media storage.** S3-compatible file storage and room-type images | ⬜ |

## Phase 1: MVP (core PMS) ⬜

Inventory and basic rates/restrictions, availability search, full audit viewer with export and the financial audit log (AUD-02/03), reservation lifecycle, tape-chart calendar, guest profiles, check-in/out, folios and charges, one payment gateway, invoices, night audit, housekeeping (with mobile view), basic work orders, standard reports and dashboard, notifications, audit viewer.

## Phase 2: Distribution and automation ⬜

Channel manager, integration surface and webhooks, booking-engine endpoints, dynamic pricing and promotions, self check-in, POS charges, accounting export, payment links and reconciliation, groups and company accounts, preventive maintenance and supplies, scheduled reports, real-time updates, privacy (NDPA/GDPR) tooling.

## Phase 3: Expansion ⬜

Residential and commercial leasing, smart-lock integration, loyalty, CRM and accounting connectors, portfolio reporting, offline-capable PWA, forecasting.
