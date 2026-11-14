# Roadmap

Delivery follows four phases. Each phase is built in small, reviewable steps; a step is ticked when it is merged with passing tests.

**Legend:** ✅ done · 🚧 in progress · ⬜ not started

## Phase 0: Foundation 🚧

Platform, security and setup that every later module builds on.

| Step | Scope | Status |
|---|---|---|
| 0.1 | **Platform and authentication.** Laravel 13 + Angular 21 monolith, private BFF (`/bff`), cookie-session auth with CSRF (Fortify + Sanctum SPA mode), TOTP two-factor, password policy, problem+json errors, Redis for sessions, cache and queues | ✅ |
| 0.2 | **Properties and property context.** Row-level organisation tenancy, properties, roles assigned per property, `X-Property-Id` validation, default permission matrix, property settings screens, dashboard endpoint | ✅ |
| 0.3 | **Rooms and room types.** Capacity, bed types, amenities, floors, sort order, active/inactive | ⬜ |
| 0.4 | **Users and roles administration.** Invite staff, activate/deactivate, assign roles per property, permission matrix editor, enforce 2FA by role | ⬜ |
| 0.5 | **Generated BFF types.** TypeScript types generated from backend resources, checked in CI | ⬜ |
| 0.6 | **Audit logging base.** Activity log for data changes and security events | ⬜ |
| 0.7 | **Quality gates.** Architecture tests for module boundaries, static analysis, linting, CI pipeline | ⬜ |
| 0.8 | **Containers and Kubernetes.** Multi-stage image, Nginx + PHP-FPM, web/worker/scheduler roles, Horizon, release job, zero-downtime rollout | ⬜ |

## Phase 1: MVP (core PMS) ⬜

Inventory and basic rates/restrictions, availability search, reservation lifecycle, tape-chart calendar, guest profiles, check-in/out, folios and charges, one payment gateway, invoices, night audit, housekeeping (with mobile view), basic work orders, standard reports and dashboard, notifications, audit viewer.

## Phase 2: Distribution and automation ⬜

Channel manager, integration surface and webhooks, booking-engine endpoints, dynamic pricing and promotions, self check-in, POS charges, accounting export, payment links and reconciliation, groups and company accounts, preventive maintenance and supplies, scheduled reports, real-time updates, privacy (NDPA/GDPR) tooling.

## Phase 3: Expansion ⬜

Residential and commercial leasing, smart-lock integration, loyalty, CRM and accounting connectors, portfolio reporting, offline-capable PWA, forecasting.
