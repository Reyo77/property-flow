The PropertyFlow API is what our mobile apps use, and it's open to your own apps and integrations. Everything is under `/api/v1`, speaks JSON, and follows the rules below.

## Signing in

1. `POST /api/v1/auth/token` with `email`, `password` and a `device_name` (shown when listing and revoking tokens). Accounts with two-factor authentication also send `code` — if it's missing you get a `422` with `code: "two_factor_required"`; ask for it and try again.
2. Send the returned token on every request: `Authorization: Bearer {token}`.
3. `GET /api/v1/me` tells you who you are: your role and `permissions` (to show or hide features), the `communities` you work in as a team member, and the `homes` you live in as a resident.

Each device has its own token. List them with `GET /api/v1/auth/tokens`; sign a device out with `DELETE /api/v1/auth/tokens/{id}` (or `current`). A token stops working when the account is deactivated or its password changes.

## Communities

Almost everything belongs to a community and lives under `/api/v1/communities/{community}/…`. A community or record from another company always answers `404`; one you can see but may not act on answers `403`. What a list contains depends on who's asking: residents see their own units' service requests, invoices and packages, the announcements and documents meant for them, and so on — the same rules as the web app.

## Lists

Every list is paginated and shares these query parameters:

| Parameter | Meaning |
|---|---|
| `page` | Page number, from 1. |
| `per_page` | Items per page, 1–100 (default 25). |
| `sort` | A field to sort by; prefix `-` for descending, e.g. `sort=-created_at`. Each endpoint lists its sortable fields. |
| `filter[name]` | Narrow the list, e.g. `filter[status]=open`. Each endpoint lists its filters and their allowed values. |

Responses have `data` (the items), `links` (`first`, `last`, `prev`, `next`) and `meta` (`current_page`, `last_page`, `per_page`, `total`). An unknown filter, sort field or value is a `422` — never silently ignored.

## Values

- **Money** is an integer number of cents in the community's `currency` (e.g. `45000` = $450.00). Send amounts the same way (`amount_cents`, `price_cents`).
- **Times** are ISO 8601 in UTC (`2026-10-02T14:05:00+00:00`). Show them in the community's `timezone`. Where you send a time without an offset, it's read on the community's own clock.
- **Dates** without a time (due dates, move-in dates) are `YYYY-MM-DD`.
- **Statuses** come with a `…_label` in the account's language, ready to display.
- **Files** — documents, receipts, notice and decision letters, statements — have a `…_url`; fetch it with the same bearer token.

## Errors

Every error has the same shape. `code` is stable — branch on it; `message` is for people.

```json
{ "message": "The title field is required.", "code": "validation_failed", "errors": { "title": ["The title field is required."] } }
```

| Status | `code` | When |
|---|---|---|
| 401 | `unauthenticated` | No token, or it was revoked. |
| 401 | `account_deactivated` | The account was deactivated; the token has been revoked. |
| 403 | `forbidden` | You may see the record but not do this. |
| 404 | `not_found` | It doesn't exist, or belongs to another company. |
| 422 | `validation_failed` | Check `errors`, keyed by field. |
| 422 | `two_factor_required` | Sign-in needs the authenticator `code`. |
| 429 | `too_many_requests` | Slow down; wait `Retry-After` seconds. |

## Rate limits

120 requests a minute per user. Sign-in allows 5 attempts a minute per email and address.

## Webhooks

Company admins can have events pushed to their own systems instead of polling: **Webhooks** in the web app's sidebar. Each delivery is a `POST` of JSON:

```json
{
  "id": "5f0c1c8e-8a4b-4a44-9a3c-0b8f6f1f2d10",
  "event": "service_request.created",
  "created_at": "2026-10-02T14:05:00+00:00",
  "company_id": 1,
  "community_id": 1,
  "data": { "id": 42, "title": "Kitchen tap dripping", "status": "open" }
}
```

`data` has the same shape as the matching endpoint below. Events: `service_request.created`, `service_request.status_changed`, `work_order.status_changed`, `package.logged`, `package.released`, `visitor.checked_in`, `amenity_booking.created`, `amenity_booking.status_changed`, `invoice.issued`, `payment.received`, `violation.reported`, `violation.escalated`, `architectural_request.submitted`, `architectural_request.decided`, `ballot.closed` (with `results`), `resident.moved_in`, `resident.moved_out` — and `webhook.ping` from **Send test**.

Headers: `PropertyFlow-Event`, `PropertyFlow-Delivery` (the `id`, for de-duplicating) and `PropertyFlow-Signature: t={unix time},v1={signature}`. **Check the signature before trusting a delivery**: compute HMAC-SHA256 of `"{t}.{raw request body}"` with the endpoint's signing secret, compare it with `v1` in constant time, and reject a `t` more than five minutes old.

```php
[$t, $v1] = sscanf($_SERVER['HTTP_PROPERTYFLOW_SIGNATURE'], 't=%d,v1=%s');
$expected = hash_hmac('sha256', $t.'.'.file_get_contents('php://input'), $secret);
$valid = hash_equals($expected, $v1) && abs(time() - $t) <= 300;
```

```javascript
const { t, v1 } = Object.fromEntries(header.split(',').map((part) => part.split('=')));
const expected = crypto.createHmac('sha256', secret).update(`${t}.${rawBody}`).digest('hex');
const valid = crypto.timingSafeEqual(Buffer.from(expected), Buffer.from(v1)) && Math.abs(Date.now() / 1000 - t) <= 300;
```

Answer with any `2xx` within 10 seconds. Anything else — including redirects, which are never followed — is retried after 1 minute, 5 minutes, 30 minutes, 2 hours and 6 hours. An endpoint that fails 15 deliveries in a row is switched off until an admin turns it back on. Deliveries are only sent to public `https` addresses.
