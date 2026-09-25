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
- [x] Fees/deposits are snapshotted onto the booking at creation time (so a later price change doesn't rewrite history). Since Phase 7 they are billed to the unit when the booking is confirmed

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
- [x] Packages: log (carrier, tracking, shelf), notify resident, release with a canvas signature pad, daily reminders for packages still uncollected after 3 days
- [x] Visitors log (staff-only, not resident-visible — front-desk records aren't a resident's business) + resident-created guest passes (a 6-character code, redeemed at the desk, which also logs the guest as a visitor in the same step)
- [x] Visitor parking permits (plate, dates). "Limits per unit" is a fixed cap of 3 simultaneously-active permits — the plan didn't specify a number, and a fixed cap avoids a whole configurable-limits UI for one setting
- [x] Incident reports with photos (reuses the polymorphic `Attachment` model from service requests), severity levels, resolution notes — an internal staff/security record, not resident-visible
- [x] Key tracking: sign-out/in with who and when, an optional due-back time, `isOverdue()`
- [x] Entry authorizations (who may enter a unit without the resident present)
- [x] Patrols: routes with ordered checkpoints, each with a real scannable QR code (`endroid/qr-code`, added with approval — self-hosted, no network calls), scanning requires the guard's own authenticated session so the scan is attributed to them, missed-checkpoint report per day
- [x] Front-desk mode: a large quick-link screen per community with a live activity feed over a private Reverb channel (`laravel/reverb`, added with approval — needs `php artisan reverb:start` plus a queue worker running locally alongside Herd). The feed only shows activity that happens while the screen is open; each module's own index page remains the source of truth for history
- [x] Shift log: append-only freeform notes, no edit/delete — a log loses its point if it can be quietly rewritten

Tests
- Package lifecycle and notifications, including the notification-preference opt-out — 552 tests total after Phase 6
- Permit limits (capacity and expiry freeing the limit back up)
- Staff-only access throughout; residents see only their own packages and guest passes, and have no access at all to the visitor log, incident reports, keys, patrols or shift log
- Patrol scan requires `manage-patrols`, 404s for another company's checkpoint token, and the missed-checkpoint report correctly distinguishes a scanned checkpoint from a missed one
- Broadcast events asserted with `Event::fake()` against `App\Events\FrontDeskActivity`

✅ **Done when:** a full front-desk shift can be logged without paper. **Met** — see `tests/Feature/Packages/PackagesTest.php`, `tests/Feature/Patrols/PatrolsTest.php`.

---

### Phase 7 — Finance *(manual payments; strictest tests)*
**Goal:** correct books for every community.

Delivered in three milestones (7a ledger & receivables, 7b billing & bills, 7c reports & reconciliation).

Tasks
- [x] Money as integer cents + `Money` value object (`App\Support\Finance\Money`): parsing never goes through a float, `percentOf` rounds half away from zero, `allocate` splits by the largest-remainder method with bcmath so parts always sum to the whole
- [x] Chart of accounts (per-type templates for condo/HOA/co-op/rental/mixed, provisioned on first use and never overwritten afterwards), fiscal years (configurable start month; a closed year refuses postings)
- [x] Double-entry ledger (`journal_entries` + `ledger_entries`), immutable twice over — a model guard *and* MySQL triggers refuse updates/deletes. Corrections are reversal entries only. Year-end close is virtual: the balance sheet folds cumulative income − expenses into equity instead of posting closing entries
- [x] Charge types; recurring charges (fixed per unit, a community total split by unit factor, or a single unit's charge such as parking)
- [x] Billing run (`finance:run-billing`, daily): one invoice per unit per month holding every charge that applies; a unique billing key per unit+month makes re-runs and crashed runs safe
- [x] Resident account page with running-balance statement, statement PDF (`barryvdh/laravel-dompdf`, added with approval), balance card on the resident dashboard
- [x] **Manual payments** (cash/cheque/bank transfer/other): oldest invoice first, overpayments held as credit and applied to the next invoice automatically, receipt PDF, reversal for refund/NSF/error that reopens the invoices it paid
- [x] Late fee rules (grace days, flat or % of the balance, minimum balance) via `finance:assess-late-fees` — at most one fee per overdue invoice, never a fee on a fee, never retroactive to invoices due before the rule existed; overdue reminders in-app (`finance:send-overdue-reminders`, weekly per invoice, new Billing notification category)
- [x] Amenity fees and deposits post to the ledger when a booking is confirmed (deposits as a liability) and are voided if the booking is cancelled unpaid. Violation fines: the *Fines* income account exists; posting to it lands with violations in Phase 8
- [x] Vendor bills → approve/reject → mark paid. Managers approve up to the community's limit (default $5,000), the board above it — enforced in the action itself, not just the UI. Approval posts expense/payable; payment clears the payable against the bank
- [x] Budgets (annual per account, per fiscal year); reports: aged receivables, income statement, balance sheet, budget vs actual (year-to-date budget = the annual figure split evenly by month to the cent), general ledger. One `ReportTable` drives the page, the export and the tests
- [x] CSV/Excel export of every report; bank statement import (CSV or XLSX; signed amount or deposit/withdrawal columns; ISO or MM/DD/YYYY dates) with auto-matching by amount, reference and date, manual match, "record in the books" for bank charges/interest, and sign-off once the difference is zero
- [x] `PaymentGateway` interface shaped like hosted checkout (create → redirect → look up by reference, from the return link or a webhook) with a local test-mode driver; `ConfirmOnlinePayment` is idempotent on a unique gateway reference, ready for a Stripe driver

Tests
- **Ledger invariant** — every journal entry and every community's ledger balances — asserted after *every* test in `tests/Feature/Finance` (global `afterEach` in `tests/Pest.php`)
- Billing run twice (and ten times) → no duplicates; an interrupted run picks up where it stopped
- Unit-factor allocation: fixed awkward cases plus a randomised run of 12 totals over 13 random factors — the sum equals the total exactly every time
- Partial, over- and split payments; credits; reversals of each kind; voids; a deliberately messy month that must still reconcile invoice-by-invoice
- Late fees with time travel (grace period boundaries, idempotency, no fee on a fee, no retroactive fees)
- Reports reconcile to a hand-computed quarter (every figure in the test's docblock), the balance sheet balances on every date, aged receivables always total the receivables account
- Approval thresholds by role (manager at the limit / one cent over, board, admin, staff) and a stale-copy double-approval race
- **Mutation testing** on the finance core (`composer test:mutate`, and a CI job): each core class — `Money`, the ledger posting/reversal actions, invoicing/payment/allocation, the billing run, late fees and reminders, vendor bills, the bank statement parser and reconciliation, and the reports — is mutated against *its own* test file and must score ≥ 80% (see `tests/mutate.php`). Mutating the whole module against every test that touches it was tried and abandoned: a single ledger mutant re-ran hundreds of database-backed tests, projecting to many hours. Runs sequentially — with `--parallel`, worker database set-up failures count as "killed" mutants and the score reads a meaningless 100%. Locally it loads Herd's bundled Xdebug for the run only (no global config change); CI uses pcov
- Tenant isolation for every finance model and page, like every other module

✅ **Done when:** a full month closes, statements are correct, reports balance. **Met** — the demo seed bills three months through the real billing run, takes payments (including a bounced cheque and a prepayment), charges late fees, and closes last month: bank statement imported, auto-matched, bank charge booked, reconciliation signed off with two genuinely outstanding deposits. See `tests/Feature/Finance/ReportsTest.php` and `tests/Feature/Finance/BankReconciliationTest.php`.

---

### Phase 8 — Governance
**Goal:** boards run the community online.

Delivered in three milestones (8a e-voting & meetings, 8b violations & renovation requests, 8c surveys, consent, board portal & community board).

Tasks
- [x] Violations: rule library (cure days, fine, maximum fines), log with photos, courtesy notice → warning → fines, one step per cure period (`violations:escalate` daily), fines invoiced to the unit on the Fines account (idempotent), notice letters as PDF, resolve/dismiss. Escalation stops at the rule's limit and waits for the board
- [x] Architectural (renovation) change requests: owners submit with plans, board reviews and approves, approves with conditions (conditions required) or denies (reason required), decision letter PDF, owner notified
- [x] E-voting: ballots with questions and options; owners only (tenants/occupants never vote); weighted by unit factor or one unit one vote; quorum; open/close times in the community's own time zone; proxies (per ballot, replaceable, revocable until used); results counted and frozen at close (`ballots:close-ended` every minute); nobody sees a tally while voting is open; audit trail of who voted for which unit and when — never how
- [x] Surveys & polls: single/multiple choice, 1–5 ratings, written answers; residents or owners only; anonymous option; one response per person; poll voters see live results
- [x] E-consent forms with typed name and drawn signature, IP and time, and a fingerprint of the exact text signed (so a later edit can't pass as consented to)
- [x] Meetings/AGM: agenda, attendance per unit (in person, online, proxy), live quorum, minutes (draft → published), linked ballots; board meetings hidden from residents
- [x] Board portal: approvals queue (bills needing the board, renovation requests, violations awaiting a decision, ballots to close), fiscal-year-to-date financials, upcoming meetings, board-only documents
- [x] Forum & classifieds (for sale, wanted, free, services) with reporting, and moderation that hides rather than deletes (plus lock and pin)

Tests
- One vote per unit, enforced by a unique index: two owners of one unit, an owner and their proxy, or a proxy twice — only the first counts; tenants, occupants, former owners and other communities' units are refused
- Weighted results math and quorum against hand-computed values (unit factor and per unit), including a turnout one hair under quorum, and a unit that voted then lost its owner
- Votes rejected before opening and from the closing minute; closing early refused; results unchanged by later changes to units or owners; votes append-only
- Violation escalation timeline with time travel (each boundary day), idempotent under repeat and stale concurrent runs, stops at the fine limit or at a warning for fine-less rules
- Survey answer validation per question kind; consent signature fingerprint detects a changed form; moderation, reporting and visibility rules
- Tenant isolation for every new model and page

✅ **Done when:** a board runs an AGM with a weighted vote end-to-end. **Met** — `tests/Feature/Governance/GovernancePagesTest.php` schedules an AGM, checks owners in until quorum, and the demo seed runs last year's AGM (closed ballot with frozen results, published minutes) and this year's budget vote (open, with a proxy vote).

Bugs found and fixed along the way (in earlier phases' code): soft-deleted units were still billed monthly and counted on the voting roll, and the scheduled finance jobs still ran for deleted communities (`withoutGlobalScopes()` also removes the soft-delete scope); draft meeting minutes leaked into residents' page source through Livewire's serialised component state.

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
