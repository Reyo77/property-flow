<?php

use App\Actions\Finance\RecordPayment;
use App\Actions\FrontDesk\LogPackage;
use App\Actions\Governance\CloseBallot;
use App\Actions\Maintenance\CreateServiceRequest;
use App\Actions\Residents\AddResidentToUnit;
use App\Enums\PaymentMethod;
use App\Enums\ResidencyType;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Models\Ballot;
use App\Models\Community;
use App\Models\Unit;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Finance\Money;
use App\Support\Webhooks\Webhooks;
use App\Support\Webhooks\WebhookSignature;
use App\Support\Webhooks\WebhookUrlGuard;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();
    Http::preventStrayRequests();
});

function reportLeak(Community $community): void
{
    app(CreateServiceRequest::class)->handle($community, companyAdmin($community->company), [
        'title' => 'Leak', 'description' => 'Under the sink', 'category' => 'plumbing', 'priority' => 'high', 'unit_id' => null, 'entry_permission' => false,
    ]);
}

describe('sending', function () {
    it('posts a signed JSON payload with the event, delivery id and API-shaped data', function () {
        Http::fake(['hooks.example.test/*' => Http::response('ok')]);
        $community = Community::factory()->create();
        $endpoint = WebhookEndpoint::factory()->for($community->company)->listeningTo([WebhookEvent::ServiceRequestCreated])->create(['url' => 'https://hooks.example.test/in']);

        reportLeak($community);

        $delivery = WebhookDelivery::sole();
        expect($delivery)->status->toBe(WebhookDeliveryStatus::Succeeded)->attempts->toBe(1)->response_status->toBe(200)
            ->and($endpoint->fresh()?->last_delivered_at)->not->toBeNull();

        Http::assertSent(function (Request $request) use ($delivery, $endpoint, $community) {
            $payload = $request->data();

            return $request->url() === 'https://hooks.example.test/in'
                && $request->hasHeader('PropertyFlow-Event', 'service_request.created')
                && $request->hasHeader('PropertyFlow-Delivery', $delivery->uuid)
                && WebhookSignature::verify($request->header(WebhookSignature::HEADER)[0], $request->body(), $endpoint->secret, now()->getTimestamp())
                && ! WebhookSignature::verify($request->header(WebhookSignature::HEADER)[0], $request->body(), 'whsec_wrong', now()->getTimestamp())
                && $payload['id'] === $delivery->uuid
                && $payload['event'] === 'service_request.created'
                && $payload['community_id'] === $community->id
                && $payload['data']['title'] === 'Leak'
                && $payload['data']['status'] === 'open';
        });
    });

    it('sends only to active endpoints of the same company that subscribed to the event', function () {
        Http::fake(['*' => Http::response('ok')]);
        $community = Community::factory()->create();
        WebhookEndpoint::factory()->for($community->company)->listeningTo([WebhookEvent::PackageLogged])->create();
        WebhookEndpoint::factory()->for($community->company)->inactive()->create();
        WebhookEndpoint::factory()->listeningTo([WebhookEvent::ServiceRequestCreated])->create();

        reportLeak($community);

        expect(WebhookDelivery::count())->toBe(0);
        Http::assertNothingSent();
    });

    it('sends nothing for work that is rolled back', function () {
        Http::fake(['*' => Http::response('ok')]);
        $community = Community::factory()->create();
        WebhookEndpoint::factory()->for($community->company)->create();

        try {
            DB::transaction(function () use ($community) {
                reportLeak($community);

                throw new RuntimeException('Something later failed');
            });
        } catch (RuntimeException) {
        }

        Http::assertNothingSent();
        expect(WebhookDelivery::count())->toBe(0);
    });

    it('describes each event with the matching API data', function (WebhookEvent $event, Closure $trigger, Closure $check) {
        Http::fake(['*' => Http::response('ok')]);
        $community = Community::factory()->create();
        WebhookEndpoint::factory()->for($community->company)->listeningTo([$event])->create();

        $trigger($community);

        $delivery = WebhookDelivery::sole();
        expect($delivery->event)->toBe($event->value)->and($check($delivery->payload['data']))->toBeTrue();
    })->with([
        'package logged' => [WebhookEvent::PackageLogged, fn (Community $c) => app(LogPackage::class)->handle($c, companyAdmin($c->company), ['unit_id' => null, 'resident_id' => null, 'carrier' => 'UPS', 'tracking_number' => null, 'shelf_location' => 'A1']), fn (array $data) => $data['carrier'] === 'UPS' && $data['status'] === 'awaiting_pickup'],
        'payment received' => [WebhookEvent::PaymentReceived, fn (Community $c) => app(RecordPayment::class)->handle(Unit::factory()->for($c)->create(), PaymentMethod::Cheque, Money::of(12345), CarbonImmutable::today()), fn (array $data) => $data['amount_cents'] === 12345 && $data['method'] === 'cheque'],
        'resident moved in' => [WebhookEvent::ResidentMovedIn, fn (Community $c) => app(AddResidentToUnit::class)->handle(Unit::factory()->for($c)->create(), null, ['name' => 'Rita Resident', 'email' => 'rita@example.test', 'phone' => null], ResidencyType::Owner, true, '2026-10-01'), fn (array $data) => $data['resident']['name'] === 'Rita Resident' && $data['residency']['type'] === 'owner'],
        'ballot closed, with results' => [WebhookEvent::BallotClosed, function (Community $c) {
            $ballot = Ballot::factory()->for($c)->open()->withQuestion()->create();
            $ballot->forceFill(['closes_at' => now()->subMinute()])->save();
            app(CloseBallot::class)->handle($ballot);
        }, fn (array $data) => $data['status'] === 'closed' && $data['results']['voted_units'] === 0],
    ]);
});

describe('failures', function () {
    it('retries a failing endpoint on schedule, then gives up and counts the failure', function () {
        Http::fake(['*' => Http::response('down', 503)]);
        $community = Community::factory()->create();
        $endpoint = WebhookEndpoint::factory()->for($community->company)->create();

        // The queue runs synchronously in tests, so every retry happens at once.
        reportLeak($community);

        expect(WebhookDelivery::sole())->status->toBe(WebhookDeliveryStatus::Failed)
            ->attempts->toBe(count(config('webhooks.retry_after_seconds')) + 1)
            ->response_status->toBe(503)
            ->and($endpoint->fresh())->consecutive_failures->toBe(1)->is_active->toBeTrue();
        Http::assertSentCount(6);
    });

    it('succeeds on a retry and clears the failure count', function () {
        Http::fake(['*' => Http::sequence()->push('down', 500)->push('ok', 200)]);
        $community = Community::factory()->create();
        $endpoint = WebhookEndpoint::factory()->for($community->company)->create(['consecutive_failures' => 3]);

        reportLeak($community);

        expect(WebhookDelivery::sole())->status->toBe(WebhookDeliveryStatus::Succeeded)->attempts->toBe(2)
            ->and($endpoint->fresh()?->consecutive_failures)->toBe(0);
    });

    it('switches an endpoint off after too many failures in a row', function () {
        config(['webhooks.disable_after_failures' => 2, 'webhooks.retry_after_seconds' => []]);
        Http::fake(['*' => Http::response('nope', 500)]);
        $community = Community::factory()->create();
        $endpoint = WebhookEndpoint::factory()->for($community->company)->create();

        reportLeak($community);
        expect($endpoint->fresh()?->is_active)->toBeTrue();

        reportLeak($community);
        expect($endpoint->fresh())->is_active->toBeFalse()->disabled_at->not->toBeNull();

        reportLeak($community);
        expect(WebhookDelivery::count())->toBe(2);
    });

    it('does not follow redirects', function () {
        config(['webhooks.retry_after_seconds' => []]);
        Http::fake(['*' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data'])]);
        $community = Community::factory()->create();
        WebhookEndpoint::factory()->for($community->company)->create();

        reportLeak($community);

        expect(WebhookDelivery::sole())->status->toBe(WebhookDeliveryStatus::Failed)->response_status->toBe(302);
        Http::assertSentCount(1);
    });

    it('refuses to deliver to a private network address', function () {
        config(['webhooks.block_private_networks' => true, 'webhooks.retry_after_seconds' => []]);
        Http::fake();
        $endpoint = WebhookEndpoint::factory()->create(['url' => 'https://10.0.0.5/hooks']);

        app(Webhooks::class)->send($endpoint, WebhookEvent::Ping, null, []);

        expect(WebhookDelivery::sole())->status->toBe(WebhookDeliveryStatus::Failed)->response_excerpt->toContain('private network');
        Http::assertNothingSent();
    });
});

describe('the address guard', function () {
    it('allows public https addresses and refuses the rest', function (string $url, bool $allowed) {
        config(['webhooks.block_private_networks' => true, 'webhooks.require_https' => true]);

        expect(app(WebhookUrlGuard::class)->problem($url) === null)->toBe($allowed);
    })->with([
        'public https' => ['https://93.184.215.14/hooks', true],
        'plain http' => ['http://93.184.215.14/hooks', false],
        'loopback' => ['https://127.0.0.1/hooks', false],
        'private range' => ['https://192.168.1.20/hooks', false],
        'link-local metadata' => ['https://169.254.169.254/latest', false],
        'IPv6 loopback' => ['https://[::1]/hooks', false],
        'credentials in the address' => ['https://user:pass@93.184.215.14/hooks', false],
        'not a web address' => ['ftp://93.184.215.14/hooks', false],
    ]);
});

describe('signatures', function () {
    it('rejects an old timestamp or a tampered body', function () {
        $header = WebhookSignature::header('{"a":1}', 'whsec_test', 1_760_000_000);

        expect(WebhookSignature::verify($header, '{"a":1}', 'whsec_test', 1_760_000_100))->toBeTrue()
            ->and(WebhookSignature::verify($header, '{"a":1}', 'whsec_test', 1_760_000_000 + 301))->toBeFalse()
            ->and(WebhookSignature::verify($header, '{"a":2}', 'whsec_test', 1_760_000_100))->toBeFalse()
            ->and(WebhookSignature::verify('garbage', '{"a":1}', 'whsec_test', 1_760_000_100))->toBeFalse();
    });
});
