<?php

use App\Enums\AssetCategory;
use App\Enums\Assignee;
use App\Enums\CompanyRole;
use App\Livewire\Assets\Index;
use App\Livewire\Assets\Show;
use App\Models\Asset;
use App\Models\Community;
use App\Models\MaintenanceSchedule;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('lists the community\'s assets', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    Asset::factory()->for($community)->create(['name' => 'North Tower Elevator']);
    Asset::factory()->for(Community::factory()->for($admin->company))->create(['name' => 'Elsewhere Asset']);

    actingAs($admin);

    get(route('communities.assets.index', $community))
        ->assertOk()
        ->assertSee('North Tower Elevator')
        ->assertDontSee('Elsewhere Asset');
});

it('creates, updates and deletes an asset', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])
        ->call('create')
        ->set('name', 'Rooftop HVAC')
        ->set('category', AssetCategory::Hvac->value)
        ->set('location', 'Rooftop')
        ->call('save')
        ->assertHasNoErrors();

    $asset = Asset::sole();

    expect($asset)->community_id->toBe($community->id)->name->toBe('Rooftop HVAC');

    Livewire::test(Index::class, ['community' => $community])
        ->call('edit', $asset->id)
        ->set('location', 'Basement')
        ->call('save');

    expect($asset->refresh()->location)->toBe('Basement');

    Livewire::test(Index::class, ['community' => $community])->call('delete', $asset->id);

    expect($asset->refresh()->trashed())->toBeTrue();
});

it('adds a maintenance schedule assigned to staff', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $asset = Asset::factory()->for($community)->create();
    $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'asset' => $asset])
        ->call('createSchedule')
        ->set('title', 'Quarterly inspection')
        ->set('interval_days', '90')
        ->set('next_due_on', now()->addDays(90)->toDateString())
        ->set('assignee_type', Assignee::Staff->value)
        ->set('assigned_user_id', (string) $staff->id)
        ->call('saveSchedule')
        ->assertHasNoErrors();

    $schedule = MaintenanceSchedule::sole();

    expect($schedule)
        ->asset_id->toBe($asset->id)
        ->title->toBe('Quarterly inspection')
        ->interval_days->toBe(90)
        ->assignee_type->toBe(Assignee::Staff)
        ->assigned_user_id->toBe($staff->id)
        ->active->toBeTrue();
});

it('adds a maintenance schedule assigned to a vendor', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $asset = Asset::factory()->for($community)->create();
    $vendor = Vendor::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'asset' => $asset])
        ->set('title', 'Annual service')
        ->set('interval_days', '365')
        ->set('next_due_on', now()->addYear()->toDateString())
        ->set('assignee_type', Assignee::Vendor->value)
        ->set('assigned_vendor_id', (string) $vendor->id)
        ->call('saveSchedule')
        ->assertHasNoErrors();

    expect(MaintenanceSchedule::sole())->assignee_type->toBe(Assignee::Vendor)->assigned_vendor_id->toBe($vendor->id);
});

it('toggles a schedule active and inactive', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $asset = Asset::factory()->for($community)->create();
    $schedule = MaintenanceSchedule::factory()->for($asset)->create();

    actingAs($admin);

    $component = Livewire::test(Show::class, ['community' => $community, 'asset' => $asset]);

    $component->call('toggleActive', $schedule->id);
    expect($schedule->refresh()->active)->toBeFalse();

    $component->call('toggleActive', $schedule->id);
    expect($schedule->refresh()->active)->toBeTrue();
});

it('generates a work order immediately for a due schedule on this asset only', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $asset = Asset::factory()->for($community)->create();
    $dueSchedule = MaintenanceSchedule::factory()->for($asset)->due()->create(['title' => 'Due now']);
    MaintenanceSchedule::factory()->for($asset)->create(['title' => 'Not due yet']);
    $otherCompanyDue = MaintenanceSchedule::factory()->due()->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'asset' => $asset])->call('generateNow');

    $workOrder = WorkOrder::sole();

    expect($workOrder)
        ->title->toBe('Due now')
        ->asset_id->toBe($asset->id)
        ->maintenance_schedule_id->toBe($dueSchedule->id);

    expect($dueSchedule->refresh())
        ->next_due_on->toDateString()->toBe(now()->addDays($dueSchedule->interval_days)->toDateString())
        ->last_generated_on->not->toBeNull();

    expect($otherCompanyDue->refresh()->last_generated_on)->toBeNull();
});

describe('scheduler command', function () {
    it('generates work orders for schedules due today and advances their next due date', function () {
        $asset = Asset::factory()->create();
        $schedule = MaintenanceSchedule::factory()->for($asset)->create([
            'title' => 'Elevator inspection',
            'interval_days' => 90,
            'next_due_on' => today()->toDateString(),
        ]);

        Artisan::call('maintenance:generate-due-work-orders');

        $workOrder = WorkOrder::withoutGlobalScopes()->where('maintenance_schedule_id', $schedule->id)->sole();

        expect($workOrder)->title->toBe('Elevator inspection')->community_id->toBe($asset->community_id);

        expect($schedule->refresh())
            ->next_due_on->toDateString()->toBe(today()->addDays(90)->toDateString())
            ->last_generated_on->toDateString()->toBe(today()->toDateString());
    });

    it('does not generate a work order before the due date', function () {
        $asset = Asset::factory()->create();
        MaintenanceSchedule::factory()->for($asset)->create(['next_due_on' => today()->addDay()->toDateString()]);

        Artisan::call('maintenance:generate-due-work-orders');

        expect(WorkOrder::withoutGlobalScopes()->count())->toBe(0);
    });

    it('does not generate a work order for an inactive schedule', function () {
        $asset = Asset::factory()->create();
        MaintenanceSchedule::factory()->for($asset)->due()->inactive()->create();

        Artisan::call('maintenance:generate-due-work-orders');

        expect(WorkOrder::withoutGlobalScopes()->count())->toBe(0);
    });

    it('generates repeatedly as each due date arrives', function () {
        $asset = Asset::factory()->create();
        $schedule = MaintenanceSchedule::factory()->for($asset)->create(['interval_days' => 7, 'next_due_on' => today()->toDateString()]);

        Artisan::call('maintenance:generate-due-work-orders');
        expect(WorkOrder::withoutGlobalScopes()->count())->toBe(1);

        Artisan::call('maintenance:generate-due-work-orders');
        expect(WorkOrder::withoutGlobalScopes()->count())->toBe(1);

        test()->travel(7)->days();
        Artisan::call('maintenance:generate-due-work-orders');
        expect(WorkOrder::withoutGlobalScopes()->count())->toBe(2);

        test()->travelBack();
    });

    it('processes overdue schedules from every company in one run', function () {
        $companyA = MaintenanceSchedule::factory()->overdue()->create();
        $companyB = MaintenanceSchedule::factory()->overdue()->create();

        Artisan::call('maintenance:generate-due-work-orders');

        expect(WorkOrder::withoutGlobalScopes()->count())->toBe(2)
            ->and($companyA->refresh()->last_generated_on)->not->toBeNull()
            ->and($companyB->refresh()->last_generated_on)->not->toBeNull();
    });
});

it('forbids staff without manage-assets from creating assets or schedules, but they can view', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $asset = Asset::factory()->for($community)->create();
    $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);

    actingAs($staff);

    get(route('communities.assets.index', $community))->assertOk();
    Livewire::test(Index::class, ['community' => $community])->call('create')->assertForbidden();
    Livewire::test(Show::class, ['community' => $community, 'asset' => $asset])->call('createSchedule')->assertForbidden();
});

it('cannot manage an asset from another company', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $foreign = Asset::factory()->create();

    actingAs($admin);

    Livewire::test(Index::class, ['community' => $community])->call('edit', $foreign->id)->assertNotFound();
});
