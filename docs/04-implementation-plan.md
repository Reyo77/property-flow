# PropertyFlow — Final Implementation Plan

> This is the plan we build from. It replaces section 8 of [02-technical-plan.md](02-technical-plan.md).
> **Scope rule:** no external paid services for now (no payment gateway, email provider, SMS, push, AI, cloud storage).
> Everything runs locally on **PHP 8.4 + Laravel + MySQL** (Laravel Herd).

---

## 1. Scope

### ✅ In scope (built now)
All core modules: community setup, residents, roles, communication, documents, maintenance, amenities, front desk & security, finance (manual payments), governance, REST API, PWA, public community sites, super admin, production hardening.

### ⏸️ Out of scope (added later, when you give API keys)
Stripe payments, email provider, SMS, push notifications, AI assistant, cloud file storage, accounting sync, SaaS subscription billing.

### How we stay ready for them
Each external service sits behind an **interface** with a **local driver** now, so adding the real one later is a config change, not a rewrite.

| Need | Interface | Local driver now | Real driver later |
|---|---|---|---|
| Resident payments | `PaymentGateway` | `ManualPaymentGateway` (cash, cheque, bank transfer recorded by manager) | Stripe |
| Email | Laravel Mail | `log` mailer / Herd's mail viewer | Postmark / SES / SMTP |
| SMS | `SmsSender` | `LogSmsSender` (writes to log) | Twilio |
| Push | Notification channel | In-app (database) + live (Reverb, self-hosted) | FCM / Web Push |
| AI | `AiAssistant` | `NullAiAssistant` (feature hidden) | Claude API |
| Files | Laravel Storage | `local` / `public` disk | S3 |
| Accounting export | `AccountingExporter` | CSV / Excel download | QuickBooks |

All notifications are sent through Laravel Notifications with `database` + `mail` channels → residents see them in-app now, emails appear in the log until a provider is added.

---

## 2. External APIs — what you'll need to give me (later)

**None are needed to build Phases 0–11.** When we get to the integrations phase, these are the keys:

| Service | Used for | Keys needed | Priority |
|---|---|---|---|
| **Email provider** (Postmark, AWS SES, Mailgun or any SMTP) | Invites, password reset, notices, receipts | SMTP host/user/password **or** API token + verified sender domain | 🔴 Needed before real users |
| **Stripe** | Online dues/rent payments, autopay | `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | 🔴 Needed for online payments |
| **Anthropic (Claude API)** | AI assistant, drafting, board reports | `ANTHROPIC_API_KEY` | 🟡 Optional |
| **Twilio** | SMS for packages/visitors | Account SID, Auth Token, phone number | 🟡 Optional |
| **Firebase Cloud Messaging** | Push to mobile apps | Service account JSON | 🟡 Only with native apps |
| **AWS S3** (or DigitalOcean Spaces / Cloudflare R2) | File storage in production | Access key, secret, bucket, region | 🟡 Production (local disk works on one server) |
| **Sentry** | Error tracking in production | DSN | 🟢 Nice to have |
| **QuickBooks Online** | Accounting sync | OAuth client ID/secret | 🟢 Later |

Composer/NPM packages (Livewire, Pest, Sanctum, Spatie, DomPDF, Excel, Reverb, etc.) are free, open-source and run locally — **no keys needed**.

---

## 3. Stack (final)

| Layer | Choice |
|---|---|
| Backend | PHP 8.4, Laravel (latest stable) |
| Database | MySQL 8 (tests: MySQL too, so behaviour matches) |
| Web UI | Livewire starter kit (Livewire + Flux free components + Alpine + Tailwind) |
| API | REST `/api/v1`, Laravel Sanctum tokens, Scribe docs |
| Permissions | spatie/laravel-permission (teams = company; community access via assignments) |
| Audit | spatie/laravel-activitylog |
| Files | Local disk (`storage/app`), spatie/laravel-medialibrary |
| PDF / Excel | barryvdh/laravel-dompdf, maatwebsite/excel |
| Queue | `database` driver locally (Redis optional later) |
| Real-time | Laravel Reverb (self-hosted WebSockets) |
| Tests | Pest, Pest browser tests, Larastan, Pint, arch tests |
| CI | GitHub Actions |

---

## 4. Definition of Done (every task)

A task is done only when **all** are true:
1. Feature works in the browser (manual check with `DemoSeeder` data)
2. **Feature tests:** happy path + validation errors + unauthorised role gets 403
3. **Tenant isolation test:** Company B user cannot see/edit Company A record (404)
4. Factory + seeder exist for new models
5. `php artisan test`, `vendor/bin/phpstan`, `vendor/bin/pint --test` all pass
6. API endpoint (if any) has an API test
7. Audit log entry for important changes

**Coverage gates:** ≥ 80% overall · ≥ 95% on tenancy, auth, finance, voting.

---

## 5. Phases

Each phase ends with a working, tested, demo-able app.

---

### Phase 0 — Foundation
**Goal:** empty app with all tooling and CI green.

Tasks
- [x] `laravel new` with Livewire starter kit + Pest into this folder
- [x] MySQL databases: `property_flow`, `property_flow_testing`
- [x] Install: Sanctum, spatie/permission, spatie/activitylog, Larastan, Pint config
- [x] `phpunit.xml` → MySQL testing DB; Pest parallel
- [x] Arch tests: no `dd/dump/ray`, `env()` only in config, Laravel/PHP/security presets, model and Livewire base classes, tenant models use `BelongsToCompany`
- [x] `Model::shouldBeStrict()` in non-prod (catches N+1, missing attributes)
- [x] Base layout: sidebar, top bar, community switcher placeholder, dark mode
- [x] GitHub Actions: pint → phpstan → pest (with coverage)
- [x] `composer test` script running everything

Tests
- Smoke: home page 200, login page renders
- Arch test suite

✅ **Done when:** CI is green and the app runs at `http://property-flow.test`.

---

### Phase 1 — Tenancy, auth & community structure *(basic app)*
**Goal:** a company can sign up and model its properties.

Tasks
- [x] `companies` table; user belongs to a company
- [x] `BelongsToCompany` trait: global scope + auto-fill `company_id`
- [x] Company sign-up (creates company + Company Admin)
- [x] Auth: login, logout, 2FA (starter kit) — password reset and email verification turned off until Phase 12 (no email service)
- [x] Communities CRUD (type: condo / HOA / co-op / rental; address, timezone, currency)
- [x] Buildings CRUD
- [x] Units CRUD (number, floor, size, unit factor %, parking, locker)
- [x] Unit CSV/Excel import with validation report
- [x] Community switcher (session stores current community)
- [x] Activity log on all models

Tests
- Tenancy: scope applied on every tenant model (arch/dataset test over all models)
- Cross-company access → 404 for every route
- CRUD feature tests (Livewire component tests)
- Import: valid file, bad rows reported, duplicate unit numbers rejected
- Unit factors must total 100% per community (warning rule)

✅ **Done when:** a company signs up, creates a community with buildings and 100 imported units.

---

### Phase 2 — People, roles & dashboard
**Goal:** the right people can log in and see the right things.

Tasks
- [x] Roles: Company Admin, Property Manager, Board Member, Staff, Vendor (+ custom roles). Owner / Tenant / Occupant are residency types on a unit, not roles, so a board member can also be an owner and residents without logins still appear
- [x] Community access: team members see only assigned communities unless their role has "Access every community"
- [x] Nobody can grant a role or permission they do not hold (prevents privilege escalation)
- [x] Deactivate / reactivate team members (blocked at login and signed out of open sessions)
- [x] Permissions seeded per module; custom roles per company
- [x] Invitations (signed link shown to the manager to copy and share — no email yet) → accept → set password
- [x] Company admins can set a new password for a team member (replaces self-service reset until email exists; audited)
- [x] Residents: link user ↔ unit (owner/tenant/occupant, move-in/out, primary contact)
- [x] Vehicles, pets, emergency contacts
- [x] Unit profile page (residents, vehicles, history)
- [x] Resident directory with search & filters
- [x] Manager dashboard (units, occupancy, residents count)
- [x] Resident home page (my unit, my info)
- [x] Policies for every model

Tests
- Policy matrix: every role × every action (dataset-driven)
- Invitation: expired link, used link, wrong company
- Admin password reset: only company admins, only for their own company's users, logged in the audit trail
- Resident sees only their unit(s); tenant after move-out loses access

✅ **Done when:** manager invites an owner, owner logs in and sees only their unit.

---

### Phase 3 — Communication & documents
**Goal:** replace email blasts and shared drives.

Tasks
- [x] Announcements: target by community / building / unit / residency type; schedule for later; pin
  - "Role" is interpreted as **residency type** (owner/tenant/occupant), since announcements are resident-facing, matching Phase 2's ResidencyType decision. Team members with `announcements.view` see every published announcement regardless of audience, since they need full visibility to do their jobs
  - "Floor" targeting has no dedicated UI; a manager gets the same result by selecting that floor's units from the searchable unit list under the Units audience type
  - Scheduled announcements publish via `announcements:publish-due`, run every minute by the scheduler (`routes/console.php`)
- [x] In-app notification centre (bell in the header, unread count, full list at `/notifications`, mark one or all read)
- [x] Notification preferences per user (`/settings/notifications`; in-app enforced now, email/SMS shown as "coming soon" toggles, stored but inert until Phase 12)
- [x] Document library: folders (nested), upload with versioning, visibility (public / residents / owners / board / staff), download only through an authenticated, policy-checked route (not a public signed URL, since visibility rules must be enforced per request)
- [x] Event calendar + RSVP (list view grouped Upcoming/Past rather than a month-grid calendar — a reasonable scope cut for now)
- [x] Phone book (staff, emergency and vendor contacts per community, with a "visible to residents" toggle)
- [ ] Live notifications via Reverb — **deferred**. The bell and list already work (Laravel's `database` notification channel), just not pushed instantly; they update on the next page load or Livewire round-trip. Reverb (WebSockets, a queue worker, `laravel-echo`/`pusher-js` on the frontend) is real new infrastructure, so it's deferred to the phase that needs true real-time UI (front desk live updates, Phase 6) rather than added piecemeal here

Tests
- Targeting: correct recipients for each target type (dataset) — 381 tests total for Phase 3, including a full policy matrix per module
- Scheduled announcement published and notified by the scheduler command (time travel via a past `publish_at`)
- `Notification::fake()` assertions for every targeting case and for the notification-preference opt-out
- Document visibility per role and per residency type; file type/size validation; download blocked when the viewer cannot see the document

✅ **Done when:** manager posts a notice to Building A, only Building A residents see it. **Met** — see `tests/Feature/Announcements/AnnouncementsTest.php`.

---

### Phase 4 — Maintenance
**Goal:** every request tracked from open to closed.

Tasks
- [x] Service requests: category, priority, photos, entry permission, status (`open → assigned → in_progress ↔ on_hold → resolved ↔ in_progress → closed`)
- [x] Comments thread (internal notes vs resident-visible)
- [x] Vendors directory + vendor users (vendor portal) — a vendor's own "My work orders" page (`/my-work-orders`) lists every job assigned to them across all communities, including preventive-maintenance work orders with no linked service request, which the embedded work-order view on a service request's page can't reach
- [x] Work orders from requests, assigned to staff or vendor
- [x] Tasks (general to-dos with due dates)
- [x] Assets (equipment inventory) + preventive maintenance schedules → auto work orders, both from a daily scheduler command (`maintenance:generate-due-work-orders`) and an on-demand "Generate now" button scoped to that one asset
- [x] Filters (status/category/priority) and an SLA overdue flag on service requests
- [ ] Dashboard widgets — **deferred**. No maintenance summary card on the main dashboard yet; the service requests and assets index pages cover the same information today. Revisit once the dashboard has a real widget layout (not a one-off addition here)

Tests
- State machine: allowed/forbidden transitions — 463 tests total after Phase 4, including a full policy matrix per module
- Internal notes never visible to residents
- Vendor sees only assigned work orders, including PM-generated ones with no service request
- Scheduler creates PM work orders on the correct dates (time travel)

✅ **Done when:** resident reports a leak → manager assigns vendor → vendor completes → resident notified. **Met** — see `tests/Feature/ServiceRequests/WorkOrdersTest.php`.

---

### Phase 5 — Amenity booking
**Goal:** self-service bookings without conflicts.

Tasks
- [x] Amenities: hours (daily open/close + closed weekdays), slot length, capacity, max bookings per unit within a rolling period, advance-booking window, min notice, blackout dates, needs approval, fee & deposit amounts
  - Hours are a single daily open/close range in minutes-since-midnight (`opens_at_minutes`/`closes_at_minutes`, e.g. "closed Mondays" via `closed_weekdays`), not a per-weekday schedule — a reasonable scope cut, matching Phase 3's events precedent
  - "Max bookings per unit per period" is a rolling window looking forward from now (`max_bookings_per_unit` within the next `max_bookings_period_days` days), not a calendar month/week — the more useful real-world rule (stops a unit stacking future bookings) and the more testable one
  - A booking is exactly one grid slot; there's no multi-slot ("book 2 hours") or multi-day booking. A guest suite books a whole day as one long slot instead
- [x] Availability + booking (resident) and a bookings-to-manage list + blackout management (manager), on one amenity page — a date picker plus a slot-button grid rather than a calendar-grid UI, the same reasonable scope cut Phase 3 made for events
- [x] Book, cancel, approve, reject; terms acceptance required when an amenity has terms text
- [x] Fees/deposits are snapshotted onto the booking at creation time (so a later price change doesn't rewrite history) but no Charge record is created yet — Phase 7 doesn't have a billing model to create one against. Revisit then

Tests
- No double-booking: the amenity's own row is locked (`lockForUpdate`) for the duration of the booking transaction, so capacity and the per-unit limit are checked and the row inserted atomically — 31 tests total for Phase 5, including capacity-at-the-limit and per-unit-limit cases
- Every rule enforced: hours, closed weekdays, blackout dates, capacity, per-unit limit, advance window, min notice, terms acceptance, cancellation notice window (resident vs. manager override, and an always-cancellable pending booking)
- DST: booking-slot generation resolves each calendar date's own UTC offset (`CarbonImmutable::create` per date, not fixed-offset arithmetic), verified against a dynamically-located real spring-forward transition
- Notification preference opt-out and tenant isolation, matching every other module's coverage

✅ **Done when:** two residents can't book the same slot, rules always apply. **Met** — see `tests/Feature/Amenities/AmenityBookingsTest.php`.

---

### Phase 6 — Front desk & security
**Goal:** concierge/security run a full shift in the app.

Tasks
- [ ] Packages: log (carrier, tracking, shelf), notify resident, release with signature pad, reminders for uncollected
- [ ] Visitors log + resident-created guest passes
- [ ] Visitor parking permits (plate, dates, limits per unit)
- [ ] Incident reports with photos
- [ ] Key tracking (sign-out/in, overdue)
- [ ] Entry authorizations (who may enter a unit)
- [ ] Patrols: routes, QR checkpoints (printable), scan from phone, missed-checkpoint report
- [ ] Front-desk mode: large fast-entry screen, live updates (Reverb)
- [ ] Shift log

Tests
- Package lifecycle and notifications
- Permit limits and expiry
- Staff-only access; residents see only their own packages/passes
- Patrol scan validates route/checkpoint and time window
- Broadcast events asserted

✅ **Done when:** a full front-desk shift can be logged without paper.

---

### Phase 7 — Finance *(manual payments; strictest tests)*
**Goal:** correct books for every community.

Tasks
- [ ] Money as integer cents + `Money` value object
- [ ] Chart of accounts (seeded templates for condo/HOA/rental), fiscal years
- [ ] Double-entry ledger (`ledger_entries`), immutable — corrections by reversal only
- [ ] Charge types; recurring charges (fixed or by unit factor)
- [ ] Billing run (monthly job): generates invoices per unit, idempotent
- [ ] Resident ledger, statement PDF, balance on resident home
- [ ] **Manual payments** (cash/cheque/bank transfer/other) recorded by manager, allocation to oldest invoices, receipts PDF, refunds/NSF reversal
- [ ] Late fee rules + scheduler, overdue reminders (in-app)
- [ ] Amenity fees, violation fines post to ledger
- [ ] Vendor bills → approval flow (manager/board thresholds) → mark paid
- [ ] Budgets; reports: aged receivables, income statement, balance sheet, budget vs actual, general ledger
- [ ] CSV/Excel export; bank statement CSV import + reconciliation
- [ ] `PaymentGateway` interface ready for Stripe

Tests
- **Ledger invariant: total debits = total credits** after every action (asserted in a global test helper)
- Billing run twice → no duplicates
- Unit-factor allocation rounding: sum equals total exactly
- Partial, over- and split payments; reversals
- Late fees with time travel
- Reports reconcile to seeded known values
- Approval thresholds by role
- **Mutation testing** on finance module (score ≥ 80%)

✅ **Done when:** a full month closes, statements are correct, reports balance.

---

### Phase 8 — Governance
**Goal:** boards run the community online.

Tasks
- [ ] Violations: rule library, log with photos, notices (PDF letter), escalation, fines → ledger, resolution
- [ ] Architectural change requests: submit with plans, review by committee/board, conditions, decision letter
- [ ] E-voting: ballots, questions, options, eligibility (owners only), weighted by unit factor or 1-unit-1-vote, quorum, open/close times, proxies, locked results, audit trail
- [ ] Surveys & polls
- [ ] E-consent forms with typed/drawn signature
- [ ] Meetings/AGM: agenda, attendance (quorum), minutes, linked ballots
- [ ] Board portal: approvals queue, financials, board-only documents
- [ ] Forum & classifieds with moderation

Tests
- One vote per eligible unit; tenants cannot vote; proxy can't be double-used
- Weighted results math; quorum calculation
- Votes rejected outside open window; results immutable after close
- Violation escalation timeline (time travel)

✅ **Done when:** a board runs an AGM with a weighted vote end-to-end.

---

### Phase 9 — REST API & PWA
**Goal:** everything usable by a future mobile app.

Tasks
- [ ] `/api/v1` for all modules (reusing Actions), API Resources, filters/sorting/pagination
- [ ] Sanctum tokens (mobile login, device names, revoke)
- [ ] Rate limiting, consistent error format
- [ ] Scribe → OpenAPI spec + Postman collection at `/docs`
- [ ] Outgoing webhooks (company-configured URLs, signed payloads, retries)
- [ ] PWA: manifest, service worker, install prompt, offline shell

Tests
- Every endpoint: 200/201, 401, 403, 404 (other company), 422
- Response shape snapshot / OpenAPI contract tests
- Webhook signature + retry tests (`Http::fake`)

✅ **Done when:** the API docs alone are enough to build a mobile client.

---

### Phase 10 — Community websites & platform admin
**Goal:** complete product for companies.

Tasks
- [ ] Public community website: pages, news, public documents, contact form; subdomain `{community}.propertyflow.test`
- [ ] Company settings: branding (logo, colours), modules on/off per community
- [ ] Super admin panel: companies, usage stats, suspend, impersonate (audited)
- [ ] Plans & limits stored in DB (no billing yet — Cashier/Stripe later)
- [ ] Data export (company download of all data) & resident data deletion (privacy)

Tests
- Public site shows only public data
- Disabled module → routes 404 and hidden in menu
- Impersonation logged and restricted to super admin
- Plan limits enforced (e.g. max units)

✅ **Done when:** a new company can sign up and run alone, with its own public site.

---

### Phase 11 — Production hardening
**Goal:** ready for real users (external services plugged in next).

Tasks
- [ ] Security: OWASP top-10 review, security headers/CSP, rate limits on auth, encrypted sensitive columns, 2FA required for staff roles, signed file URLs, `composer audit`
- [ ] Performance: indexes review, eager loading, caching of dashboards/reports, queue for heavy work, pagination everywhere
- [ ] Load test with k6 (p95 < 300 ms on dashboard & main lists with 10k units seeded)
- [ ] Reliability: health check route, failed-job alerts (log), scheduled DB backups (spatie/laravel-backup to local disk), restore drill
- [ ] Deployment scripts, zero-downtime deploy, `.env.production.example`
- [ ] Docs: admin guide, resident help, runbook
- [ ] Full browser test suite for critical flows on staging

Critical browser flows
1. Company sign-up → create community → import units
2. Invite resident → accept → log in
3. Resident submits request → manager resolves
4. Resident books amenity
5. Staff logs package → resident sees it → release
6. Manager runs billing → records payment → statement correct
7. Board member votes → results

✅ **Done when:** all gates pass and launch checklist (in 02-technical-plan.md) is complete, except items that need external services.

---

### Phase 12 — Integrations *(when you provide keys)*
Order: **Email (then re-enable password reset + email verification in `config/fortify.php`, `User implements MustVerifyEmail`, and drop the no-email arch test) → Stripe → S3 → Sentry → SMS → AI → Push → QuickBooks**.
Each one: implement the real driver behind its interface, test with `Http::fake` / provider test mode, switch via `.env`.

---

## 6. Order of work inside every phase

1. Migration + model + factory + enum
2. Policy + permissions
3. Action classes (business logic) + **unit tests**
4. Livewire screens + **feature tests**
5. API endpoints + **API tests** (or deferred to Phase 9 with a checklist)
6. Seeder data for demo
7. Run full suite, phpstan, pint → commit → demo

---

## 7. Model recommendation for building this

| Work | Model |
|---|---|
| Whole plan (default) | **Claude Opus 5.5** — best at long multi-step builds, architecture, finance/voting logic and writing thorough tests |
| Repetitive CRUD screens, seeders, simple tests (optional, to save usage) | **Claude Sonnet 5** — fast and cheaper, good for well-defined tasks |
| Quick fixes / formatting | Haiku 4.5 — not recommended as the main builder |

**Recommendation:** use **Opus 5.5** for everything, especially Phases 0, 1, 7, 8 and 11 (tenancy, finance, voting, security). If usage matters, switch to Sonnet 5 (`/model`) for Phases 3–6 and back to Opus 5.5 for finance.
