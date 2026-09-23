<?php

use App\Enums\AreaUnit;
use App\Enums\CommunityType;
use App\Livewire\Communities\Create;
use App\Livewire\Communities\Edit;
use App\Models\Community;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

describe('index', function () {
    it('redirects guests to the login page', function () {
        get(route('communities.index'))->assertRedirect(route('login'));
    });

    it('forbids company members without a role', function () {
        actingAs(memberWithoutRole());

        get(route('communities.index'))->assertForbidden();
    });

    it('lists only the company\'s own communities', function () {
        $admin = companyAdmin();
        Community::factory()->for($admin->company)->create(['name' => 'Harbour Towers']);
        Community::factory()->create(['name' => 'Someone Else\'s Place']);

        actingAs($admin);

        get(route('communities.index'))
            ->assertOk()
            ->assertSee('Harbour Towers')
            ->assertDontSee('Someone Else');
    });

    it('escapes community names', function () {
        $admin = companyAdmin();
        Community::factory()->for($admin->company)->create(['name' => '<script>alert(1)</script>']);

        actingAs($admin);

        get(route('communities.index'))
            ->assertOk()
            ->assertSee('&lt;script&gt;', escape: false)
            ->assertDontSee('<script>alert(1)</script>', escape: false);
    });
});

describe('create', function () {
    it('creates a community for the admin\'s company and makes it current', function () {
        $admin = companyAdmin();

        actingAs($admin);

        Livewire::test(Create::class)
            ->set('form.name', 'Harbour Towers')
            ->set('form.type', CommunityType::Hoa->value)
            ->set('form.city', 'Toronto')
            ->set('form.country', 'ca')
            ->set('form.timezone', 'America/Toronto')
            ->set('form.currency', 'cad')
            ->set('form.area_unit', AreaUnit::SquareMetres->value)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('communities.show', Community::sole()));

        $community = Community::sole();

        expect($community)
            ->company_id->toBe($admin->company_id)
            ->name->toBe('Harbour Towers')
            ->type->toBe(CommunityType::Hoa)
            ->country->toBe('CA')
            ->currency->toBe('CAD')
            ->area_unit->toBe(AreaUnit::SquareMetres)
            ->address_line_1->toBeNull()
            ->and(session('current_community_id'))->toBe($community->id);
    });

    it('requires a name', function () {
        actingAs(companyAdmin());

        Livewire::test(Create::class)
            ->set('form.name', '')
            ->call('save')
            ->assertHasErrors(['form.name' => 'required']);

        expect(Community::count())->toBe(0);
    });

    it('rejects invalid settings', function (string $field, string $value) {
        actingAs(companyAdmin());

        Livewire::test(Create::class)
            ->set('form.name', 'Harbour Towers')
            ->set("form.{$field}", $value)
            ->call('save')
            ->assertHasErrors(["form.{$field}"]);
    })->with([
        'unknown type' => ['type', 'castle'],
        'unknown timezone' => ['timezone', 'Mars/Olympus'],
        'three-letter country' => ['country', 'CAN'],
        'two-letter currency' => ['currency', 'CA'],
        'unknown area unit' => ['area_unit', 'acres'],
    ]);

    it('forbids company members without a role', function () {
        actingAs(memberWithoutRole());

        Livewire::test(Create::class)->assertForbidden();
    });

    it('records who created the community', function () {
        $admin = companyAdmin();

        actingAs($admin);

        Livewire::test(Create::class)
            ->set('form.name', 'Harbour Towers')
            ->call('save');

        $activity = Activity::query()->where('subject_type', (new Community)->getMorphClass())->sole();

        expect($activity)
            ->event->toBe('created')
            ->causer_id->toBe($admin->id);
    });
});

describe('update', function () {
    it('updates the community', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Edit::class, ['community' => $community])
            ->assertSet('form.name', $community->name)
            ->set('form.name', 'Renamed Towers')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('communities.show', $community));

        expect($community->refresh()->name)->toBe('Renamed Towers');
    });

    it('forbids company members without a role', function () {
        $member = memberWithoutRole();
        $community = Community::factory()->for($member->company)->create();

        actingAs($member);

        get(route('communities.edit', $community))->assertForbidden();
    });
});

describe('delete', function () {
    it('soft deletes the community', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();

        actingAs($admin);

        Livewire::test(Edit::class, ['community' => $community])
            ->call('delete')
            ->assertRedirect(route('communities.index'));

        expect($community->refresh()->trashed())->toBeTrue();
    });
});
