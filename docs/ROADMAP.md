# Roadmap

Delivery follows four phases. Each phase is built in small, reviewable steps; a step is ticked when it is merged with passing tests.

**Legend:** ✅ done · 🚧 in progress · ⬜ not started

## Phase 0: Foundation ✅

Platform, security and setup that every later module builds on.

| Step | Scope | Status |
|---|---|---|
| 0.1 | **Platform and authentication.** Laravel 13 + Angular 21 monolith, private BFF (`/bff`), cookie-session auth with CSRF (Fortify + Sanctum SPA mode), TOTP two-factor, password policy, problem+json errors, Redis for sessions, cache and queues | ✅ |
| 0.2 | **Properties and property context.** Row-level organisation tenancy, properties, roles assigned per property, `X-Property-Id` validation, default permission matrix, property settings screens, dashboard endpoint | ✅ |
| 0.3 | **Rooms and room types.** Room types with capacity, bed configuration and amenities; rooms with floors, sort order, bulk range creation and housekeeping/occupancy status fields; amenity catalogue; dashboard room counts | ✅ |
| 0.4 | **Users and roles administration.** Staff invitations with set-password links, activate/deactivate, Super Admin management, roles per property, custom roles with a permission matrix editor, 2FA enforceable by role | ✅ |
| 0.5 | **Generated BFF types.** Every BFF response is a typed DTO; TypeScript types are generated from them (`composer types`) and a backend test fails on drift | ✅ |
| 0.6 | **Audit logging base.** Who/what/when/where with before/after values for settings, users and roles; security events (sign-ins, failures, lockouts, 2FA, passwords, invitations, permission changes); append-only, hash-chained trail with `audit:verify`; basic read-only viewer | ✅ |
| 0.7 | **Quality gates.** Architecture tests (module boundaries, strict types, layering), Larastan level 8, Angular ESLint, GitHub Actions CI running Pint, Larastan, the test suite on MySQL + Redis, the BFF types drift check, lint, unit tests, build and dependency audits. Created/updated-by stamps and per-record history on settings screens | ✅ |
| 0.8 | **Containers and Kubernetes.** Pinned multi-stage image (non-root, read-only root filesystem), Nginx + PHP-FPM with SPA fallback and cache headers, Kustomize manifests for web (HPA, PDB, graceful drain), Horizon workers, scheduler and the release Job, optional Reverb component, staging and production overlays; Horizon queues per spec §10.3; SPA version-skew handling; migration classification check (expand/contract); CI image build, vulnerability scan, smoke test and manifest validation | ✅ |
| 0.9 | **Media storage.** Uploaded files on a private media disk (S3 in deployed environments, local disk in development) via spatie/laravel-medialibrary; room-type image gallery with validated uploads (type sniffed from content, size, dimensions, 20 per room type), WebP thumbnails at upload and full-size versions on the queue, signed expiring URLs only (originals and EXIF never served), drag-and-drop or keyboard reordering with a cover image, alt text, removal via the confirm modal, audit history entries, cover thumbnails on the room-type list | ✅ |

## Phase 1: MVP (core PMS) 🚧

One pilot property operating end to end (spec §16). Steps follow the order the modules depend on each other: inventory and rates before reservations, reservations before the calendar, front desk and billing.

| Step | Scope | Status |
|---|---|---|
| 1.1 | **Inventory.** Master allotment per room type per night (`room_type_inventory`, CHN-01) with the spec's check constraint, kept in step with active rooms and two years ahead; room-type blocks and holds with reason and automatic expiry (CAL-06); overbooking allowance by date range and weekday; row locking in ascending date order (§6.3, §7.4); `Idempotency-Key` enforcement for POSTs with inventory or money effects (§8.1); inventory screen | ✅ |
| 1.2 | **Rate plans, policies and taxes.** Taxes and fees per property: percentages (inclusive or exclusive) and fixed amounts per night, per stay or per guest night (§2.8); cancellation and no-show policies, deposit and payment policies with generated guest wording (RTE-07); rate plans with meal plan, policies, taxes, price limits and a base price plus extra-adult and extra-child supplements per room type (RTE-01); rate audit of every price change with reason (RTE-09); money as integer minor units via brick/money; price preview with full tax breakdown | ✅ |
| 1.3 | **Rates and restrictions grid.** Daily overrides by range, weekday and season with bulk edit (RTE-02); min/max stay, closed, CTA, CTD, advance purchase (RTE-03); rate audit (RTE-09) | ⬜ |
| 1.4 | **Availability search and quote.** Itemised nightly price with taxes, fees and policy text (RTE-10); the reservation stepper's options endpoint | ⬜ |
| 1.5 | **Guest profiles.** Profiles, search, stay history, preferences, consent; encrypted ID fields (§5.3) | ⬜ |
| 1.6 | **Reservations.** Create, modify, cancel with policy, no-show, tentative holds with expiry, overbooking-safe booking (RES-01 to RES-07) | ⬜ |
| 1.7 | **Calendar.** Tape chart with virtual scrolling, drag to move or resize, filters, availability row, quick actions (CAL-01 to CAL-06, CAL-08) | ⬜ |
| 1.8 | **Front desk.** Room assignment, check-in, check-out, room-level out of order (RES-07 to RES-09) | ⬜ |
| 1.9 | **Billing.** Charge codes with tax and GL mapping (BIL-02), folios and charges, one payment gateway, invoices with gap-free numbering (§5.8, §5.9) | ⬜ |
| 1.10 | **Night audit.** Scheduler pod plus worker fan-out, per property and business date, idempotent | ⬜ |
| 1.11 | **Housekeeping.** Board by status and assignment, mobile attendant view (§5.6) | ⬜ |
| 1.12 | **Work orders.** Basic maintenance with room blocking (§5.7) | ⬜ |
| 1.13 | **Reports and notifications.** Standard reports and dashboard on the read replica, notifications, full audit viewer with export and the financial audit log (AUD-02/03) | ⬜ |
| 1.14 | **Load test.** Web HPA tuned by load test (k6) | ⬜ |

## Phase 2: Distribution and automation ⬜

Channel manager, integration surface and webhooks, booking-engine endpoints, dynamic pricing and promotions, self check-in, POS charges, accounting export, payment links and reconciliation, groups and company accounts, preventive maintenance and supplies, scheduled reports, real-time updates, privacy (NDPA/GDPR) tooling.

## Phase 3: Expansion ⬜

Residential and commercial leasing, smart-lock integration, loyalty, CRM and accounting connectors, portfolio reporting, offline-capable PWA, forecasting.
