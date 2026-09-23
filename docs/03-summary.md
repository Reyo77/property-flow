# PropertyFlow — Summary

**What:** A clone of Property Control — one platform to manage condos, HOAs and rental communities.

**For:** property management companies, managers, boards, residents, front-desk/security staff and vendors.

**Stack:** PHP 8.4 · Laravel · MySQL · Livewire + Tailwind (web) · REST API with Sanctum (mobile) · Stripe · Redis queues · Pest for testing.

**How we build it:** start small, add a module each phase, test everything.

| # | Phase | Result |
|---|---|---|
| 0 | Foundation | Project, CI and testing tools ready |
| 1 | **Basic app** | Login, communities, buildings, units, residents, roles |
| 2 | Communication | Announcements, notifications, documents, calendar |
| 3 | Maintenance | Service requests, work orders, vendors |
| 4 | Amenities | Online booking with rules |
| 5 | Front desk | Packages, visitors, parking, incidents, keys, patrols |
| 6 | Finance | Dues, online payments, ledger, reports |
| 7 | Governance | Violations, e-voting, approvals, meetings |
| 8 | API & mobile | Full REST API, docs, push, PWA |
| 9 | AI | Assistant, drafting, board reports |
| 10 | Websites & SaaS | Community sites, subscription plans |
| 11 | Production | Security, speed, backups, monitoring, launch |

**Testing rule:** no feature is done without tests. CI blocks anything that fails. Finance and data isolation get the strictest tests.

**End goal:** a production-ready PropertyFlow, feature-matched with Property Control, in ~34 weeks.

**Phase 1 scope:** no external services (payments, email, SMS, AI) — they plug in later. Build plan: [Implementation Plan](04-implementation-plan.md).

See: [Product Overview](01-product-overview.md) · [Technical Plan](02-technical-plan.md) · [Implementation Plan](04-implementation-plan.md)
