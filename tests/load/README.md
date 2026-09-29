# Load testing (k6)

Not part of the Pest suite — these are manual, run-on-demand load tests against a real running
instance of the app (local Herd or staging), not the test database.

## Setup

Two-factor authentication is required for staff (Phase 11), so the load-test user needs it
pre-confirmed with a known secret — there's no way to script a real authenticator app. Seed a
test company at whatever scale you want to test (the Phase 11 target was 10k units) and enable
2FA for its admin with a secret you control:

```bash
php artisan tinker --execute '
    $company = \App\Models\Company::factory()->create(["name" => "Load Test Co"]);
    $community = \App\Models\Community::factory()->for($company)->create();
    \App\Models\Unit::factory()->for($community)->count(10000)->create(); // slow via factories;
    // for a real 10k+ run, bulk-insert with DB::table("units")->insert() in chunks instead.

    $admin = \App\Models\User::factory()->for($company)->companyAdmin()->create([
        "email" => "loadtest@propertyflow.test",
    ]);
    $admin->communities()->attach($community);

    $secret = (new \PragmaRX\Google2FA\Google2FA())->generateSecretKey();
    $admin->forceFill([
        "two_factor_secret" => encrypt($secret),
        "two_factor_recovery_codes" => encrypt(json_encode(["recovery-code-load-test"])),
        "two_factor_confirmed_at" => now(),
    ])->save();

    echo "SECRET={$secret}\nCOMMUNITY_ID={$community->id}\n";
'
```

Keep the printed `SECRET` — you need a fresh one-time code from it (valid ~30s) immediately
before each run:

```bash
OTP=$(php artisan tinker --execute '
    echo (new \PragmaRX\Google2FA\Google2FA())->getCurrentOtp("<SECRET>");
' | tail -1 | tr -d '[:space:]')

OTP="$OTP" COMMUNITY_ID=<COMMUNITY_ID> k6 run --insecure-skip-tls-verify tests/load/dashboard-and-lists.js
```

`--insecure-skip-tls-verify` is only needed for Herd's self-signed local certificate.

## What it tests

`dashboard-and-lists.js` logs in for real (password + the one-time code — the actual Fortify
flow, not a forged session), then hits `/dashboard`, `/communities/{id}/units` and
`/communities/{id}/residents` on a ramp from 0 to `VUS` (default 10) virtual users, asserting
p95 latency stays under `P95_TARGET_MS` (default 300ms) for each page.

## Clean up afterwards

```bash
php artisan tinker --execute '\App\Models\Company::withoutGlobalScopes()->where("name", "Load Test Co")->each->delete();'
```

`Company::delete()` cascades to its communities, units and users, so this is enough — verify with
a row count if you want to be sure nothing was left behind.

## Result from the Phase 11 run (10k units, local Herd dev environment)

| Concurrency | dashboard p95 | units p95 | residents p95 |
|---|---|---|---|
| 10 VUs | 78ms | 126ms | 74ms | 
| 40 VUs | 473ms | 529ms | 472ms |

The 10 VU run comfortably beat the 300ms target. The 40 VU run didn't — but that's Herd's local
PHP-FPM pool (`pm.max_children = 5` by default), not the app: the math lines up almost exactly
(5 workers × ~100ms/request ≈ the ~45 req/s ceiling actually observed), and a production
deployment sized for its expected concurrency wouldn't hit that ceiling at this request rate.
Worth re-running against a production-like server before trusting the number at higher
concurrency.
