# PropertyFlow — Technical Plan & Execution Roadmap

This is the build guide. It covers the stack, architecture, database, APIs, frontend, testing strategy and a phase-by-phase plan that starts with a small working app and grows into a production-ready platform.

**Rule for every phase:** a phase is not done until its tests pass in CI. No tests = not shipped.

---

## 1. Technology Stack

| Layer | Choice | Why |
|---|---|---|
| Language | **PHP 8.4** | Already installed; modern typing, enums, readonly |
| Framework | **Laravel (latest stable)** | Auth, queues, mail, events, policies, scheduler out of the box |
| Database | **MySQL 8** | Relational data (units, ledgers, votes) fits SQL well |
| Frontend (web) | **Blade + Livewire + Alpine.js + Tailwind CSS** | Stays in PHP, reactive UI without a separate SPA |
| UI components | Flux UI / Tailwind components | Consistent forms, tables, modals |
| API (mobile/integrations) | **REST JSON API `/api/v1`** with **Laravel Sanctum** | Token auth for mobile apps and 3rd parties |
| Roles & permissions | **spatie/laravel-permission** | Role + permission per community |
| Multi-tenancy | Single DB, `company_id` scoping via global scopes | Simple, cheap, easy to report across properties |
| Queues | **Redis + Laravel Horizon** | Emails, SMS, billing runs, notifications |
| Cache / sessions | Redis | Speed |
| File storage | Local in dev, **S3-compatible** in prod | Documents, photos, attachments |
| Search | Laravel Scout (database driver → Meilisearch later) | Search residents, units, documents |
| Payments | **Stripe** (Laravel Cashier for SaaS billing, Stripe PaymentIntents for resident payments) | Cards + ACH/PAD |
| Email | Laravel Mail (Postmark / SES) | Notices, receipts |
| SMS | Twilio | Package & visitor alerts |
| Push | Web Push / FCM | Mobile notifications |
| PDFs | barryvdh/laravel-dompdf | Statements, receipts, notices |
| Excel/CSV | maatwebsite/excel | Imports and exports |
| Audit log | spatie/laravel-activitylog | Who changed what |
| Real-time | Laravel Reverb (WebSockets) | Live front-desk updates, chat |
| AI | Claude API (Anthropic PHP SDK / HTTP client) | Assistant, drafting, summaries |
| Local dev | **Laravel Herd** + DBngin / Herd MySQL | Already in use |
| Testing | **Pest**, Pest browser testing / Laravel Dusk, Larastan, Pint | See section 7 |
| CI/CD | GitHub Actions | Tests + static analysis on every push |
| Hosting | Laravel Forge / Cloud on AWS or DigitalOcean | Managed PHP hosting |
| Monitoring | Sentry, Laravel Pulse, uptime checks | Errors & performance |

---

## 2. Architecture

```
            ┌──────────────┐   ┌──────────────┐   ┌──────────────┐
            │ Manager/Board│   │ Resident Web │   │ Mobile Apps  │
            │ (Livewire)   │   │ (Livewire/PWA)│  │ / 3rd party  │
            └──────┬───────┘   └──────┬───────┘   └──────┬───────┘
                   │  session auth    │                  │ Sanctum token
                   ▼                  ▼                  ▼
            ┌──────────────────────────────────────────────────┐
            │  Laravel app                                     │
            │  Routes → Controllers/Livewire → Actions/Services│
            │  Policies (authz) · Form Requests (validation)   │
            │  Eloquent Models (company-scoped) · Events       │
            └───────┬─────────────┬──────────────┬─────────────┘
                    ▼             ▼              ▼
                 MySQL        Redis (queue,   S3 storage
                              cache, Horizon)
                                  │
                   Jobs → Mail · SMS · Push · Stripe · Claude API
```

### Code organisation
```
app/
  Actions/          # single-purpose business actions (CreateServiceRequest, PostCharge…)
  Enums/            # statuses, roles, types
  Events/ Listeners/
  Http/
    Controllers/Api/V1/
    Requests/       # validation
    Resources/      # API JSON shape
  Livewire/         # web screens, grouped by module
  Models/
  Policies/
  Services/         # Stripe, SMS, AI, Billing engine
  Support/Tenancy/  # company scoping
database/
  migrations/ factories/ seeders/
tests/
  Unit/ Feature/ Api/ Browser/
```

### Key principles
- **Every tenant-owned table has `company_id`** and a global scope; cross-company access must be impossible (and tested).
- Business logic lives in **Actions**, reused by Livewire and API controllers.
- Money stored as **integer cents**, never floats.
- All state changes that matter write to the **audit log**.
- Anything slow (mail, SMS, PDFs, billing) runs on a **queue**.

---

## 3. Users & Roles

| Role | Scope | Can do |
|---|---|---|
| Super Admin | Platform | Manage companies, plans, support access |
| Company Admin | Company | Everything in their company, billing, users |
| Property Manager | Assigned communities | Day-to-day operations, finance, residents |
| Board Member | One community | View reports, approve, vote, board documents |
| Staff (concierge/security/maintenance) | One community | Front desk, packages, visitors, work orders |
| Resident – Owner | Own unit(s) | Pay, book, request, vote, documents |
| Resident – Tenant | Own unit | Same as owner minus voting/owner docs |
| Vendor | Assigned work | See work orders, upload invoices |

Permissions are granular (e.g. `packages.create`, `ledger.view`) so companies can make custom roles.

---

## 4. Database Design (main tables)

**Core**
- `companies` — tenant (name, plan, settings, stripe ids)
- `users` — login (name, email, phone, password, 2FA)
- `communities` — condo/HOA/rental property (company_id, type, address, timezone, currency)
- `buildings` — (community_id, name, floors)
- `units` — (building_id, number, floor, size, unit_factor/share %, parking, locker)
- `residents` — link user ↔ unit (type: owner/tenant/occupant, move_in, move_out, primary)
- `vehicles`, `pets`, `emergency_contacts`
- `roles`, `permissions`, `model_has_roles` (spatie, team = company; each company has its own editable role set)
- `invitations`

**Communication**
- `announcements`, `announcement_recipients`, `notifications`
- `events`, `event_rsvps`
- `forum_topics`, `forum_posts`, `classifieds`
- `surveys`, `survey_questions`, `survey_responses`

**Documents**
- `document_folders`, `documents` (visibility: public/residents/owners/board/staff)

**Maintenance**
- `service_requests` (unit, category, priority, status, assigned_to)
- `service_request_comments`, `attachments` (polymorphic)
- `work_orders`, `vendors`, `vendor_contacts`
- `assets`, `maintenance_schedules`, `tasks`

**Amenities**
- `amenities` (rules, slots, capacity, fee, deposit, needs_approval)
- `amenity_bookings`

**Security / front desk**
- `packages`, `visitors`, `guest_passes`, `parking_permits`
- `incidents`, `keys`, `key_logs`
- `patrol_routes`, `patrol_checkpoints`, `patrol_scans`
- `entry_authorizations`

**Finance**
- `accounts` (chart of accounts), `fiscal_years`, `budgets`, `budget_lines`
- `charge_types`, `recurring_charges`
- `invoices`, `invoice_lines` (resident charges)
- `payments`, `payment_allocations`, `payment_methods`
- `ledger_entries` (double-entry: account, debit, credit, source polymorphic)
- `vendor_bills`, `vendor_bill_approvals`, `vendor_payments`
- `late_fee_rules`, `bank_accounts`, `bank_transactions`, `reconciliations`

**Governance**
- `violations`, `violation_notices`, `fines`
- `architectural_requests`, `architectural_reviews`
- `ballots`, `ballot_questions`, `ballot_options`, `votes`, `proxies`
- `consent_forms`, `consent_signatures`
- `meetings`, `meeting_attendees`, `meeting_minutes`

**System**
- `activity_log`, `settings`, `webhooks`, `api_tokens` (Sanctum), `jobs`, `failed_jobs`

All tables: `id`, `company_id` (where tenant-owned), `timestamps`, `softDeletes` on user-facing records. Indexes on every foreign key and common filters (`status`, `community_id`, dates).

---

## 5. API Reference (REST `/api/v1`)

**Conventions**
- Auth: `Authorization: Bearer <sanctum-token>`
- JSON only; responses via API Resources: `{ "data": ..., "meta": ..., "links": ... }`
- Pagination: `?page=&per_page=` (max 100)
- Filtering/sorting: `?filter[status]=open&sort=-created_at`
- Errors: `422` validation `{message, errors}`, `401`, `403`, `404`, `429`
- Rate limit: 60 req/min per token (configurable)
- Community context: `/api/v1/communities/{community}/...`
- Versioned; breaking changes → `/v2`
- Docs auto-generated with **Scribe** (OpenAPI + Postman collection)

### Auth & profile
| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/auth/login` | Get token |
| POST | `/auth/logout` | Revoke token |
| POST | `/auth/forgot-password` / `/auth/reset-password` | Password reset |
| GET/PATCH | `/me` | Profile |
| GET | `/me/units` | Units I belong to |
| POST | `/me/devices` | Register push token |

### Community structure
| Method | Endpoint |
|---|---|
| GET/POST | `/communities` |
| GET/PATCH/DELETE | `/communities/{id}` |
| GET/POST | `/communities/{id}/buildings` |
| GET/POST | `/communities/{id}/units` |
| GET/PATCH | `/units/{id}` |
| GET/POST | `/units/{id}/residents` |
| GET/POST | `/residents` · `/residents/{id}/vehicles` · `/residents/{id}/pets` |
| POST | `/communities/{id}/imports/units` (CSV) |

### Communication
| Method | Endpoint |
|---|---|
| GET/POST | `/communities/{id}/announcements` |
| POST | `/announcements/{id}/send` |
| GET/POST | `/communities/{id}/events` · `POST /events/{id}/rsvp` |
| GET/POST | `/communities/{id}/forum/topics` · `/topics/{id}/posts` |
| GET/POST | `/communities/{id}/surveys` · `POST /surveys/{id}/responses` |
| GET | `/notifications` · `POST /notifications/{id}/read` |

### Documents
| GET/POST | `/communities/{id}/documents` · `GET /documents/{id}/download` |

### Maintenance
| Method | Endpoint |
|---|---|
| GET/POST | `/communities/{id}/service-requests` |
| GET/PATCH | `/service-requests/{id}` |
| POST | `/service-requests/{id}/comments` · `/attachments` · `/assign` · `/close` |
| GET/POST | `/work-orders` · `/vendors` · `/assets` · `/maintenance-schedules` · `/tasks` |

### Amenities
| Method | Endpoint |
|---|---|
| GET | `/communities/{id}/amenities` |
| GET | `/amenities/{id}/availability?date=` |
| GET/POST | `/amenities/{id}/bookings` |
| POST | `/bookings/{id}/approve` · `/reject` · `/cancel` |

### Security / front desk
| Method | Endpoint |
|---|---|
| GET/POST | `/communities/{id}/packages` · `POST /packages/{id}/release` |
| GET/POST | `/communities/{id}/visitors` · `/guest-passes` · `/parking-permits` |
| GET/POST | `/communities/{id}/incidents` |
| GET/POST | `/keys` · `POST /keys/{id}/sign-out` · `/sign-in` |
| GET | `/patrol-routes` · `POST /patrol-scans` |

### Finance
| Method | Endpoint |
|---|---|
| GET | `/units/{id}/ledger` · `/units/{id}/statement.pdf` |
| GET/POST | `/communities/{id}/invoices` · `/charge-types` · `/recurring-charges` |
| POST | `/communities/{id}/billing-runs` (generate monthly charges) |
| POST | `/payments/intent` (Stripe) · `GET /payments` |
| GET/POST | `/vendor-bills` · `POST /vendor-bills/{id}/approve` |
| GET | `/communities/{id}/reports/{type}` (aged receivables, income statement, balance sheet, budget vs actual) |

### Governance
| Method | Endpoint |
|---|---|
| GET/POST | `/communities/{id}/violations` · `POST /violations/{id}/notices` |
| GET/POST | `/communities/{id}/architectural-requests` · `POST /{id}/review` |
| GET/POST | `/communities/{id}/ballots` · `POST /ballots/{id}/votes` · `POST /ballots/{id}/proxies` · `GET /ballots/{id}/results` |
| GET/POST | `/consent-forms` · `POST /consent-forms/{id}/sign` |
| GET/POST | `/communities/{id}/meetings` |

### AI
| POST | `/communities/{id}/assistant/ask` · `/ai/draft-announcement` · `/ai/board-report` |

### Webhooks (incoming)
| POST | `/webhooks/stripe` · `/webhooks/twilio` |

---

## 6. Frontend

### Web app (Blade + Livewire + Tailwind)
- **Layout:** sidebar (modules), top bar (community switcher, search, notifications, profile)
- **Manager dashboard:** open requests, overdue payments, today's bookings, packages waiting, recent incidents, KPIs per community
- **Screens per module:** list (filter, search, sort, bulk actions) → detail → create/edit form (modals where short)
- **Board portal:** approvals queue, financial reports, ballots, board documents
- **Resident portal:** home (balance, announcements, bookings, packages), pay now, book amenity, new request, documents, voting
- **Front desk mode:** big-button, fast-entry screen for packages/visitors/keys, live updates via Reverb
- **Vendor portal:** assigned work orders, upload invoice
- Responsive, mobile-first; installable **PWA** for residents and staff
- Accessibility: WCAG 2.1 AA (labels, contrast, keyboard)
- Dark mode, i18n-ready (all strings via `__()`), timezone & currency per community

### Mobile
- Phase 1–10: PWA + REST API.
- Later: native apps (Flutter or React Native) built on the same `/api/v1`.

---

## 7. Testing Strategy (mandatory)

Testing is part of every task, not a phase at the end.

### Tools
| Type | Tool | What it covers |
|---|---|---|
| Unit | **Pest** | Actions, services, money math, billing rules, enums |
| Feature | **Pest** + `RefreshDatabase` | HTTP routes, Livewire components, policies, jobs, notifications |
| API | **Pest** (`getJson/postJson`) | Every endpoint: success, validation, auth, permission, tenant isolation |
| Browser / E2E | **Pest browser tests** (or Laravel Dusk) | Critical flows end-to-end in a real browser |
| Static analysis | **Larastan (PHPStan) level 8+** | Type errors before runtime |
| Code style | **Laravel Pint** | Consistent formatting |
| Architecture | Pest `arch()` tests | e.g. no `dd()`, controllers don't touch DB directly, models extend base model |
| Mutation | Pest `--mutate` (finance + billing modules) | Tests actually catch bugs |
| Load | **k6** | Dashboards, API, billing run under load |
| Security | `composer audit`, OWASP ZAP scan, Enlightn | Vulnerabilities |

### Rules
1. **Every feature ships with tests** — feature test for the happy path, validation failures, and permission denial.
2. **Tenant isolation test for every model/endpoint**: a user from Company A must get `404/403` for Company B data.
3. **Policy tests for every role** in the roles table.
4. **Finance must be exact**: ledger always balances (debits = credits) — tested with property-based / many-case datasets. Mutation score ≥ 80% in finance.
5. **External services are faked** (`Mail::fake`, `Queue::fake`, `Http::fake`, Stripe test mode, Notification fake).
6. **Factories + seeders** for every model; a `DemoSeeder` builds a realistic community for manual QA.
7. **Coverage gate:** ≥ 80% overall, ≥ 95% on finance, auth, and tenancy.
8. **CI blocks merge** if tests, Larastan, Pint or coverage fail.
9. **Bug fix = regression test first**, then the fix.
10. Browser tests for the critical paths: login, resident pays dues, books amenity, submits request, manager closes request, staff logs package, board member votes.

### CI pipeline (GitHub Actions)
```
push / PR →
  composer install → pint --test → phpstan →
  pest --parallel --coverage --min=80 →
  browser tests (critical paths) →
  composer audit →
  (main branch) deploy to staging
```

### Environments
- **Local** (Herd) → **CI** (MySQL service container) → **Staging** (prod-like, Stripe test mode) → **Production**

---

## 8. Execution Plan (phases)

> ⚠️ Superseded by [04-implementation-plan.md](04-implementation-plan.md) (final plan, no external services for now).

Each phase ends with a **working, tested app**. Build → test → demo → next.

### Phase 0 — Project foundation (Week 1)
- Create Laravel app, connect MySQL, Git repo, `.env.example`
- Install Livewire, Tailwind, Pest, Larastan, Pint, Sanctum, spatie/permission, activitylog
- GitHub Actions CI pipeline
- Base layout, design tokens, component library
- **Tests:** CI green with smoke test, arch tests set up
- ✅ **Done when:** `php artisan test` + phpstan + pint pass in CI

### Phase 1 — Basic app (MVP core) (Weeks 2–4)
- Auth: login, register via invite, password reset, email verify, 2FA
- Companies (tenancy), communities, buildings, units
- Residents (owner/tenant), vehicles, pets, CSV import
- Roles & permissions, user invitations
- Manager dashboard (counts), community switcher
- Audit log
- **Tests:** auth flows, CRUD feature tests, tenant isolation, role policies, import validation
- ✅ **Done when:** a manager can set up a community, add units, invite residents who can log in

### Phase 2 — Communication & documents (Weeks 5–6)
- Announcements (email + in-app, targeted by building/unit/role), scheduled sends
- Notification centre, user notification preferences
- Document library with folders & visibility
- Event calendar, phone book
- **Tests:** targeting logic, notifications faked & asserted, document access by role, file upload validation
- ✅ **Done when:** manager posts a notice and the right residents receive it

### Phase 3 — Maintenance (Weeks 7–9)
- Service requests (resident creates with photos; manager assigns; status flow; comments)
- Vendors, work orders, vendor portal
- Tasks, assets, preventive maintenance schedules (scheduler creates work orders)
- **Tests:** status transitions (state machine), assignment notifications, scheduler tests with time travel, vendor sees only their jobs
- ✅ **Done when:** request → work order → vendor → closed, all notified

### Phase 4 — Amenity booking (Weeks 10–11)
- Amenities with rules (slots, capacity, max per unit, blackout dates, approval)
- Availability calendar, booking, cancel, approve
- Fees/deposits (recorded now, charged in Phase 6)
- **Tests:** overlap/double-booking prevention, rules enforcement, timezone edge cases, concurrent booking test
- ✅ **Done when:** resident books the party room and conflicts are impossible

### Phase 5 — Security & front desk (Weeks 12–14)
- Packages (log, notify via email/SMS, release with signature)
- Visitors, guest passes, parking permits
- Incident reports, key tracking, entry authorizations
- Patrol routes & QR checkpoint scans
- Front-desk fast mode + live updates (Reverb)
- **Tests:** package lifecycle, SMS faked, permit expiry, staff-only access, broadcast events
- ✅ **Done when:** concierge runs a full shift on PropertyFlow

### Phase 6 — Finance & payments (Weeks 15–20) ⚠️ highest test bar
- Chart of accounts, double-entry ledger
- Charge types, recurring dues (by unit factor), billing runs
- Invoices, statements (PDF), resident ledger
- Stripe online payments (card + bank), webhooks, receipts, autopay
- Late fees, reminders, aged receivables
- Vendor bills + board/manager approval flow, vendor payments
- Budgets, budget vs actual, income statement, balance sheet
- QuickBooks / CSV export, bank reconciliation
- Amenity fees & violation fines post to ledger
- **Tests:** ledger always balances, rounding, partial payments, refunds, duplicate webhook (idempotency), late-fee schedule with time travel, report totals vs seeded data, mutation testing
- ✅ **Done when:** a full month closes with correct books and residents pay online

### Phase 7 — Governance (Weeks 21–24)
- Violations (log with photos → notice → fine → resolution)
- Architectural change requests with review workflow
- E-voting: ballots, weighted votes by unit factor, quorum, proxy voting, results
- Surveys & polls, e-consent forms
- Meetings / AGM: agenda, attendance, minutes
- Board portal
- Forum & classifieds
- **Tests:** one vote per eligible unit, weighting math, proxy rules, ballot closes on time, results locked, tenants can't vote
- ✅ **Done when:** a board runs an AGM vote online end-to-end

### Phase 8 — Public API & mobile readiness (Weeks 25–26)
- Complete `/api/v1` for all modules, Sanctum tokens, rate limits
- Scribe API docs (OpenAPI), Postman collection
- Push notifications (FCM / Web Push), PWA install + offline shell
- Outgoing webhooks for integrations
- **Tests:** API test for every endpoint (auth, validation, permission, isolation), contract tests against OpenAPI spec
- ✅ **Done when:** a mobile client could be built using only the docs

### Phase 9 — AI features (Weeks 27–28)
- Resident assistant answering from community documents (retrieval over document library)
- Draft announcements / violation letters
- Financial summary & board report generation
- Guardrails: community-scoped context, no cross-tenant data, logging, human review before sending
- **Tests:** `Http::fake` for Claude API, prompt building tests, tenant scoping of context, rate limiting
- ✅ **Done when:** manager can generate a board report draft in one click

### Phase 10 — Community websites & SaaS billing (Weeks 29–30)
- Public community site (pages, contact, public docs) with custom domain
- Company subscription plans via Laravel Cashier (per-door pricing), trials
- Super admin panel (companies, usage, impersonation with audit)
- **Tests:** plan limits enforced, subscription webhooks, public pages expose only public data
- ✅ **Done when:** a new company can sign up, pay, and go live alone

### Phase 11 — Production hardening & launch (Weeks 31–34)
- **Security:** OWASP top-10 review, ZAP scan, rate limits, CSP headers, encrypted sensitive fields, 2FA enforcement for staff, signed URLs for files, GDPR/PIPEDA data export & delete
- **Performance:** N+1 checks (preventLazyLoading), indexes, caching, queue tuning, k6 load test (target p95 < 300 ms on key pages)
- **Reliability:** Horizon, failed-job alerts, daily DB backups + restore drill, zero-downtime deploys, health check endpoint
- **Observability:** Sentry, Pulse, uptime monitor, logs
- **Docs:** admin guide, resident help centre, API docs, runbook
- **UAT:** pilot with 1–2 real communities, fix feedback
- **Tests:** full regression suite, browser tests on staging, load + security reports signed off
- ✅ **Done when:** launch checklist below is 100%

### Launch checklist
- [ ] All tests green, coverage gates met
- [ ] Larastan clean, `composer audit` clean
- [ ] Load test passed, security scan passed
- [ ] Backups verified by restore
- [ ] Monitoring & alerts live
- [ ] Stripe live mode verified
- [ ] Legal: terms, privacy policy
- [ ] Pilot communities signed off

---

## 9. Working agreement

- Small branches, one feature per PR, tests in the same PR
- Conventional commits; CHANGELOG per release
- Demo at the end of every phase
- Keep this document updated as decisions change
