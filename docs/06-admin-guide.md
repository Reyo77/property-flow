# PropertyFlow — Admin Guide

> For company admins, property managers, board members and front-desk/maintenance staff.
> Residents: see `07-resident-help.md` instead.

---

## 1. Getting started

1. **Sign up** creates your company and makes you its Company Admin.
2. **Set up two-factor authentication** the first time you sign in — every team role except
   Vendor is required to before doing anything else (Settings → Security). Vendors and residents
   aren't required to, since they can't manage anything sensitive.
3. **Create a community** (Communities → New community). A company can run several — a management
   company with multiple buildings, or a single self-managed HOA with just one.
4. **Add buildings and units**, either one at a time or in bulk via the CSV/Excel importer on the
   Units page (a template with the expected columns is available from the same screen).
5. **Invite your team** (Team → Invite member). Since no email is wired up yet, invitations are
   copyable links you send yourself (Slack, text, however) rather than automatic emails.
6. **Invite residents**: from a unit's page, add the resident, then invite them the same way —
   a link, not an email.

---

## 2. Roles

| Role | Can do |
|---|---|
| **Company Admin** | Everything. Only role that can manage other admins, company settings, webhooks, and billing/plan info. |
| **Property Manager** | Day-to-day operations across assigned communities: residents, maintenance, finance, governance, front desk. Can't change company settings or other people's access beyond their own level. |
| **Board Member** | Read access to most things plus governance actions (ballots, meetings, approving architectural requests, bylaw decisions). No finance-entry or resident-management rights by default. |
| **Staff** | Front-line operations: maintenance, front desk, security. Limited visibility elsewhere. |
| **Vendor** | Their own portal only (`/my-work-orders`) — the jobs assigned to them, nothing else in the app. |

Company Admins can also create **custom roles** (Team → Roles) with any combination of
permissions, for situations the five built-in roles don't fit.

A team member only sees the communities they're assigned to, unless their role has "access every
community" (on by default for Company Admin and Property Manager).

---

## 3. Communities

Each community has its own settings: address, timezone, currency, fiscal year, billing due day,
and a bill-approval limit (bills above it need a second approver). A community also has a unique
public web address — see §9.

**Modules**: from a community's Edit page, switch off feature areas it doesn't use (Amenities,
Maintenance, Governance, Finance, Front Desk, Security). Their menu items and routes disappear
entirely for that community — useful for e.g. a small HOA with no amenities to book, or a
rental portfolio with no governance/voting needs. Residents, Buildings, Units and Communication
(announcements, documents, events, phone book) are always on.

---

## 4. Residents

Add residents to units as **owner**, **tenant**, or **occupant** — a unit can have several
residents at once (e.g. an owner and a tenant), and a resident can have moved through several
units over time (their residency history stays intact). Only owners can vote in governance.

Residents without a portal login still show up everywhere (unit rosters, phone book, emergency
contacts) — a login is optional, added by inviting them.

**Data deletion requests**: a resident can ask, from their own profile, that their personal data
be erased. Review these under Data deletion requests (sidebar). Approving anonymizes their name,
email and phone immediately and can't be undone — their residency, invoice and vote history stays
on record (needed for financial/audit continuity), just no longer tied to their name.

---

## 5. Communication

- **Announcements**: target the whole community, specific buildings/units, or a residency type
  (owners only, etc.). Pin important ones. Schedule for later, or publish immediately.
  Check "Show on the public website" to also put a community-wide announcement on your public
  news page (§9) — only available for community-wide announcements, since a public visitor has
  no unit to match a narrower audience against.
- **Documents**: organize into folders, set a visibility level per document or folder (Public,
  Residents, Owners only, Board & managers, Staff only). "Public" also appears on the public
  website, unauthenticated.
- **Events**, **Phone book** (staff/contacts directory), **Contact messages** (from your public
  site's contact form).

---

## 6. Maintenance

Residents (or staff, on their behalf) submit **service requests** with photos; staff assign them
to a **work order**, either handled in-house or by a **vendor**. Work orders have their own status
that feeds back into the parent request. **Preventive maintenance schedules** on an **asset**
auto-generate work orders on a recurring basis — no need to remember. **Tasks** are simpler
to-dos with a due date, not tied to a resident-facing request.

---

## 7. Amenities

Set up each bookable space (gym, party room, pool, etc.) with its own hours, slot length,
capacity, per-unit booking limit, cancellation window, and whether bookings need manager approval.
Add blackout dates for closures. A fee/deposit, if any, is snapshotted onto the booking at the
time it's made.

---

## 8. Front desk & security

**Front-desk mode** is a focused, activity-feed screen for staff working the desk: log packages
(residents are notified, and sign for pickup), log visitors, redeem resident-issued guest passes,
issue parking permits (capped per unit). **Security**: incident reports, key sign-out/in, entry
authorizations (who may let themselves into a unit), patrol routes with scannable QR checkpoints,
and an append-only shift log.

---

## 9. Finance

Integer-cents accounting throughout, an append-only double-entry ledger (corrections are made by
reversal, never by editing a posted entry). Set up your chart of accounts and charge types once;
after that:

- **Billing**: run it to generate the period's recurring charges as invoices.
- **Late fees**: assessed automatically per each community's late-fee rule.
- **Payments**: record manually, or residents can pay online (a local test-mode gateway for now —
  a real processor is a Phase 12 integration).
- **Vendor bills**: submitted, approved (up to a manager's limit, a second approver above it),
  and paid.
- **Reconciliation**: import a bank statement (CSV) and match its lines against the ledger.
- **Reports & budget**: income/expense and balance-sheet style reports per fiscal year, plus a
  PDF export.

---

## 10. Governance

**Ballots** (formal votes, one per issue, with a defined voting window and quorum), **meetings**
(agenda, minutes, attendance), the **board portal** (a dashboard just for board members), and
**renovation/architectural requests** (owners submit, the board or managers decide). Bylaw
**violations** go through their own rule set, notices and fines, with automatic escalation for
ones left unresolved. **Surveys & polls**, **e-consent forms** (drawn-signature), and the
**community board** (a resident discussion/classifieds forum, moderated by staff) round out
resident engagement.

---

## 11. Company settings (Company Admin only)

Branding (name, logo, brand color — shown on your public site), your current **plan** and its
usage limits (communities/units/team members — a company with no plan is unlimited), and
**data export**: request a zip of your company's core data (communities, units, residents, team)
at any time, built in the background and downloadable once ready.

**Webhooks**: send events (announcement.published, invoice.paid, and more) to your own systems as
signed JSON POSTs; see `/docs` for the full event list and payload shapes, and the REST API
reference alongside it.

---

## 12. Public website

Every community gets its own page automatically at
`https://{community-slug}.property-flow.test` (a real deployment would use your own domain) — a
home page, news (announcements you've marked public), public documents, and a contact form that
lands in your Contact messages inbox. No setup needed beyond marking the content you want visible;
the "Public site" button on a community's Overview page links straight to it.

---

## 13. If something looks wrong

- **A page is missing that should be there**: check the community's Modules (§3) — it may be
  switched off — and the signed-in user's role/permissions (§2).
- **Can't reach a page you expect to see**: two-factor authentication may not be confirmed yet
  (§1) — you're redirected to Settings → Security until it is.
- **Data looks wrong across the whole company**: contact PropertyFlow support (or, if you have
  platform access, check `/platform/companies` for a suspension).
