<?php

use App\Livewire\FrontDesk\Mode;
use App\Models\Community;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('shows front-desk mode to staff with front-desk access', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    get(route('communities.front-desk.mode', $community))->assertOk();
});

it('appends live activity as it is received and caps the feed at 50 entries', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    $component = Livewire::test(Mode::class, ['community' => $community]);

    $component->call('recordActivity', ['type' => 'package', 'message' => 'UPS package logged.', 'occurred_at' => now()->toIso8601String()]);

    expect($component->get('activity'))->toHaveCount(1)
        ->and($component->get('activity')[0]['message'])->toBe('UPS package logged.');

    foreach (range(1, 60) as $i) {
        $component->call('recordActivity', ['type' => 'visitor', 'message' => "Visitor {$i}", 'occurred_at' => now()->toIso8601String()]);
    }

    expect($component->get('activity'))->toHaveCount(50)
        ->and($component->get('activity')[0]['message'])->toBe('Visitor 60');
});

it('does not give residents access to front-desk mode', function () {
    $community = Community::factory()->create();
    $resident = residentOf($community);

    actingAs($resident->user);

    get(route('communities.front-desk.mode', $community))->assertForbidden();
});
