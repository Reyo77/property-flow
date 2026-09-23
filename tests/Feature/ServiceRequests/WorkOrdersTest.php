<?php

use App\Enums\Assignee;
use App\Enums\CompanyRole;
use App\Enums\NotificationCategory;
use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Livewire\ServiceRequests\Show;
use App\Models\Community;
use App\Models\NotificationPreference;
use App\Models\Residency;
use App\Models\ServiceRequest;
use App\Models\Vendor;
use App\Models\WorkOrder;
use App\Notifications\ServiceRequestStatusChanged;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('creates a work order, assigns it to staff and notifies the resident', function () {
    Notification::fake();

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $request = ServiceRequest::factory()->for($community)->create(['unit_id' => $unit->id]);
    $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
        ->call('openWorkOrderForm')
        ->set('title', 'Fix the leak')
        ->set('assignee_type', Assignee::Staff->value)
        ->set('assigned_user_id', (string) $staff->id)
        ->call('createWorkOrder')
        ->assertHasNoErrors();

    $workOrder = WorkOrder::sole();

    expect($workOrder)
        ->service_request_id->toBe($request->id)
        ->community_id->toBe($community->id)
        ->assignee_type->toBe(Assignee::Staff)
        ->assigned_user_id->toBe($staff->id)
        ->status->toBe(WorkOrderStatus::Pending)
        ->created_by_id->toBe($admin->id);

    expect($request->refresh()->status)->toBe(ServiceRequestStatus::Assigned);

    Notification::assertSentTo($resident->user, ServiceRequestStatusChanged::class);
});

it('assigns a work order to a vendor', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $request = ServiceRequest::factory()->for($community)->create();
    $vendor = Vendor::factory()->for($admin->company)->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
        ->set('title', 'Fix the leak')
        ->set('assignee_type', Assignee::Vendor->value)
        ->set('assigned_vendor_id', (string) $vendor->id)
        ->call('createWorkOrder')
        ->assertHasNoErrors();

    expect(WorkOrder::sole())->assignee_type->toBe(Assignee::Vendor)->assigned_vendor_id->toBe($vendor->id);
});

it('requires an assignee for the chosen assignee type', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $request = ServiceRequest::factory()->for($community)->create();

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
        ->set('title', 'x')
        ->set('assignee_type', Assignee::Staff->value)
        ->call('createWorkOrder')
        ->assertHasErrors('assigned_user_id');
});

it('will not create a second active work order for the same request', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $request = ServiceRequest::factory()->for($community)->assigned()->create();
    WorkOrder::factory()->for($community)->create(['service_request_id' => $request->id, 'status' => WorkOrderStatus::Pending]);
    $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
        ->set('title', 'Second attempt')
        ->set('assignee_type', Assignee::Staff->value)
        ->set('assigned_user_id', (string) $staff->id)
        ->call('createWorkOrder');

    expect(WorkOrder::count())->toBe(1);
});

it('allows a new work order once the previous one was cancelled', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $request = ServiceRequest::factory()->for($community)->assigned()->create();
    WorkOrder::factory()->for($community)->create(['service_request_id' => $request->id, 'status' => WorkOrderStatus::Cancelled]);
    $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
        ->set('title', 'Reassigned')
        ->set('assignee_type', Assignee::Staff->value)
        ->set('assigned_user_id', (string) $staff->id)
        ->call('createWorkOrder')
        ->assertHasNoErrors();

    expect(WorkOrder::count())->toBe(2);
});

it('follows the work order state machine and resolves the service request on completion', function () {
    Notification::fake();

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $request = ServiceRequest::factory()->for($community)->assigned()->create(['unit_id' => $unit->id]);
    $workOrder = WorkOrder::factory()->for($community)->assignedToUser($admin->id)->create(['service_request_id' => $request->id]);

    actingAs($admin);

    $component = Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request]);

    $component->call('transitionWorkOrderTo', WorkOrderStatus::InProgress->value);
    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::InProgress);

    $component->set('completionNotes', 'Replaced the washer.')
        ->call('transitionWorkOrderTo', WorkOrderStatus::Completed->value);

    expect($workOrder->refresh())
        ->status->toBe(WorkOrderStatus::Completed)
        ->completion_notes->toBe('Replaced the washer.')
        ->completed_at->not->toBeNull();

    expect($request->refresh()->status)->toBe(ServiceRequestStatus::Resolved);

    Notification::assertSentTo($resident->user, ServiceRequestStatusChanged::class);
});

it('refuses a work order transition the state machine forbids', function () {
    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $request = ServiceRequest::factory()->for($community)->create();
    $workOrder = WorkOrder::factory()->for($community)->completed()->create(['service_request_id' => $request->id]);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
        ->call('transitionWorkOrderTo', WorkOrderStatus::InProgress->value);

    expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::Completed);
});

it('respects the maintenance-updates notification preference', function () {
    Notification::fake();

    $admin = companyAdmin();
    $community = Community::factory()->for($admin->company)->create();
    $resident = residentOf($community);
    $unit = Residency::where('resident_id', $resident->id)->sole()->unit;
    $request = ServiceRequest::factory()->for($community)->create(['unit_id' => $unit->id]);
    NotificationPreference::factory()->for($resident->user)->create(['category' => NotificationCategory::MaintenanceUpdates, 'in_app' => false]);
    $staff = teamMember(CompanyRole::Staff, $admin->company, [$community]);

    actingAs($admin);

    Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
        ->set('title', 'x')
        ->set('assignee_type', Assignee::Staff->value)
        ->set('assigned_user_id', (string) $staff->id)
        ->call('createWorkOrder');

    Notification::assertNotSentTo($resident->user, ServiceRequestStatusChanged::class);
});

describe('vendor portal access', function () {
    it('lets an assigned vendor view and update progress on their work order', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $request = ServiceRequest::factory()->for($community)->assigned()->create();
        $vendor = Vendor::factory()->for($admin->company)->withLogin()->create();
        $workOrder = WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)->create(['service_request_id' => $request->id]);

        actingAs($vendor->user);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->assertOk()
            ->call('transitionWorkOrderTo', WorkOrderStatus::InProgress->value)
            ->assertHasNoErrors();

        expect($workOrder->refresh()->status)->toBe(WorkOrderStatus::InProgress);
    });

    it('does not let a vendor view a request assigned to a different vendor', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $request = ServiceRequest::factory()->for($community)->assigned()->create();
        $assignedVendor = Vendor::factory()->for($admin->company)->create();
        WorkOrder::factory()->for($community)->assignedToVendor($assignedVendor->id)->create(['service_request_id' => $request->id]);
        $otherVendor = Vendor::factory()->for($admin->company)->withLogin()->create();

        actingAs($otherVendor->user);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])->assertForbidden();
    });

    it('does not let a vendor manage the service request itself', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $request = ServiceRequest::factory()->for($community)->assigned()->create();
        $vendor = Vendor::factory()->for($admin->company)->withLogin()->create();
        WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)->create(['service_request_id' => $request->id]);

        actingAs($vendor->user);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->call('transitionTo', ServiceRequestStatus::Closed->value)
            ->assertForbidden();
    });

    it('does not let a vendor create a new work order', function () {
        $admin = companyAdmin();
        $community = Community::factory()->for($admin->company)->create();
        $request = ServiceRequest::factory()->for($community)->assigned()->create();
        $vendor = Vendor::factory()->for($admin->company)->withLogin()->create();
        WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)->create(['service_request_id' => $request->id]);

        actingAs($vendor->user);

        Livewire::test(Show::class, ['community' => $community, 'serviceRequest' => $request])
            ->call('openWorkOrderForm')
            ->assertForbidden();
    });
});

it('cannot manage a work order from another company', function () {
    $admin = companyAdmin();
    $foreignWorkOrder = WorkOrder::factory()->create();

    actingAs($admin);

    expect($admin->can('view', $foreignWorkOrder))->toBeFalse()
        ->and($admin->can('updateProgress', $foreignWorkOrder))->toBeFalse();
});
