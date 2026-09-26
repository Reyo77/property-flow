<?php

use App\Enums\CompanyRole;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEvent;
use App\Livewire\Webhooks\Index;
use App\Models\Company;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(fn () => Http::preventStrayRequests());

it('lets a company admin add a webhook and shows its signing secret once saved', function () {
    actingAs($admin = companyAdmin());

    Livewire::test(Index::class)
        ->call('create')
        ->set('url', 'https://hooks.example.test/propertyflow')
        ->set('description', 'Accounting')
        ->set('events', [WebhookEvent::PaymentReceived->value, WebhookEvent::InvoiceIssued->value, 'made.up'])
        ->call('save')
        ->assertHasNoErrors()
        ->assertSee('whsec_', false);

    expect(WebhookEndpoint::sole())
        ->company_id->toBe($admin->company_id)
        ->events->toBe(['payment.received', 'invoice.issued'])
        ->secret->toStartWith('whsec_');
});

it('refuses an http address, a private address and an empty event list', function (string $url, array $events, string $field) {
    config(['webhooks.block_private_networks' => true]);
    actingAs(companyAdmin());

    Livewire::test(Index::class)
        ->call('create')
        ->set('url', $url)
        ->set('events', $events)
        ->call('save')
        ->assertHasErrors($field);

    expect(WebhookEndpoint::count())->toBe(0);
})->with([
    'plain http' => ['http://93.184.215.14/hooks', ['payment.received'], 'url'],
    'private network' => ['https://10.1.2.3/hooks', ['payment.received'], 'url'],
    'no events' => ['https://93.184.215.14/hooks', [], 'events'],
]);

it('sends a test ping, shows the delivery, and resends a failed one', function () {
    Http::fake(['*' => Http::sequence()->push('down', 500)->push('ok', 200)]);
    config(['webhooks.retry_after_seconds' => []]);
    actingAs($admin = companyAdmin());
    $endpoint = WebhookEndpoint::factory()->for($admin->company)->create();

    $page = Livewire::test(Index::class)->call('sendTest', $endpoint->id)->assertSee('webhook.ping');

    $delivery = WebhookDelivery::sole();
    expect($delivery->status)->toBe(WebhookDeliveryStatus::Failed);

    $page->call('redeliver', $delivery->id);
    expect($delivery->fresh())->status->toBe(WebhookDeliveryStatus::Succeeded)->attempts->toBe(1);
});

it('rotates the secret', function () {
    actingAs($admin = companyAdmin());
    $endpoint = WebhookEndpoint::factory()->for($admin->company)->create();
    $old = $endpoint->secret;

    Livewire::test(Index::class)->call('rotateSecret', $endpoint->id)->assertSee($endpoint->fresh()?->secret);

    expect($endpoint->fresh()?->secret)->not->toBe($old);
});

it('switches an endpoint back on and clears its failures', function () {
    actingAs($admin = companyAdmin());
    $endpoint = WebhookEndpoint::factory()->for($admin->company)->inactive()->create(['consecutive_failures' => 15]);

    Livewire::test(Index::class)->call('edit', $endpoint->id)->set('active', true)->call('save')->assertHasNoErrors();

    expect($endpoint->fresh())->is_active->toBeTrue()->consecutive_failures->toBe(0)->disabled_at->toBeNull();
});

it('is for people who may manage webhooks only', function () {
    $company = Company::factory()->create();

    actingAs(teamMember(CompanyRole::PropertyManager, $company));
    get(route('webhooks.index'))->assertForbidden();

    actingAs(companyAdmin($company));
    get(route('webhooks.index'))->assertOk();
});

it('never shows or touches another company\'s webhooks', function () {
    $theirs = WebhookEndpoint::factory()->create(['url' => 'https://theirs.example.test/hook']);
    actingAs(companyAdmin());

    Livewire::test(Index::class)->assertDontSee('theirs.example.test');

    foreach (['edit', 'revealSecret', 'rotateSecret', 'sendTest', 'delete'] as $action) {
        Livewire::test(Index::class)->call($action, $theirs->id)->assertNotFound();
    }

    expect($theirs->fresh())->not->toBeNull();
});
