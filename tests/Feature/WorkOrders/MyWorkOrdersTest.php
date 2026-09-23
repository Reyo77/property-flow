<?php

use App\Enums\WorkOrderStatus;
use App\Livewire\WorkOrders\MyWorkOrders;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('lists a vendor\'s open work orders across communities, including ones with no service request', function () {
    $vendor = Vendor::factory()->withLogin()->create();
    $community = Community::factory()->for($vendor->company)->create();
    $otherCommunity = Community::factory()->for($vendor->company)->create();

    $fromRequest = WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)
        ->create(['title' => 'Fix the leak', 'service_request_id' => ServiceRequest::factory()->for($community)->create()->id]);
    $preventiveMaintenance = WorkOrder::factory()->for($otherCommunity)->assignedToVendor($vendor->id)
        ->create(['title' => 'Elevator inspection', 'service_request_id' => null]);
    $completed = WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)->completed()
        ->create(['title' => 'Already done']);
    $someoneElses = WorkOrder::factory()->for($community)->create(['title' => 'Not mine']);

    actingAs($vendor->user);

    Livewire::test(MyWorkOrders::class)
        ->assertOk()
        ->assertSee('Fix the leak')
        ->assertSee('Elevator inspection')
        ->assertDontSee('Already done')
        ->assertDontSee('Not mine');

    expect(WorkOrder::withoutGlobalScopes()->count())->toBe(4);
});

it('lets a vendor transition a preventive-maintenance work order that has no service request', function () {
    $vendor = Vendor::factory()->withLogin()->create();
    $community = Community::factory()->for($vendor->company)->create();
    $workOrder = WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)
        ->create(['service_request_id' => null, 'status' => WorkOrderStatus::Pending]);

    actingAs($vendor->user);

    Livewire::test(MyWorkOrders::class)
        ->call('transitionTo', $workOrder->id, WorkOrderStatus::InProgress->value)
        ->assertHasNoErrors();

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::InProgress);
});

it('does not let a vendor transition another vendor\'s work order', function () {
    $vendor = Vendor::factory()->withLogin()->create();
    $otherVendor = Vendor::factory()->for($vendor->company)->create();
    $community = Community::factory()->for($vendor->company)->create();
    $workOrder = WorkOrder::factory()->for($community)->assignedToVendor($otherVendor->id)->create();

    actingAs($vendor->user);

    Livewire::test(MyWorkOrders::class)
        ->call('transitionTo', $workOrder->id, WorkOrderStatus::InProgress->value)
        ->assertNotFound();
});

it('is forbidden for a user who is not a vendor', function () {
    $admin = companyAdmin();

    actingAs($admin);

    Livewire::test(MyWorkOrders::class)->assertForbidden();
});
