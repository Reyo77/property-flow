<?php

use App\Enums\CompanyRole;
use App\Enums\ServiceRequestStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Community;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestComment;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

beforeEach(fn () => Storage::fake('local'));

function requestPayload(array $overrides = []): array
{
    return ['title' => 'Kitchen tap dripping', 'description' => 'Since Monday.', 'category' => 'plumbing', 'priority' => 'medium', ...$overrides];
}

describe('reporting a problem', function () {
    it('lets a resident report one for their own unit, with photos', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $unit = $resident->residencies()->sole()->unit;
        Sanctum::actingAs($resident->user);

        post(route('api.v1.communities.service-requests.store', $community), requestPayload([
            'unit_id' => $unit->id,
            'entry_permission' => '1',
            'photos' => [UploadedFile::fake()->image('tap.jpg')],
        ]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.unit.id', $unit->id)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.entry_permission', true);

        expect(ServiceRequest::sole())->reported_by_resident_id->toBe($resident->id)
            ->and(ServiceRequest::sole()->attachments()->count())->toBe(1);
    });

    it('refuses a resident a neighbour\'s unit, but lets the team pick any', function () {
        $community = Community::factory()->create();
        $neighbourUnit = Unit::factory()->for($community)->create();
        Sanctum::actingAs(residentOf($community)->user);

        postJson(route('api.v1.communities.service-requests.store', $community), requestPayload(['unit_id' => $neighbourUnit->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unit_id');

        Sanctum::actingAs(teamMember(CompanyRole::PropertyManager, $community->company, [$community]));
        postJson(route('api.v1.communities.service-requests.store', $community), requestPayload(['unit_id' => $neighbourUnit->id]))->assertCreated();
    });

    it('validates the request', function () {
        $community = Community::factory()->create();
        Sanctum::actingAs(residentOf($community)->user);

        postJson(route('api.v1.communities.service-requests.store', $community), ['category' => 'bogus', 'unit_id' => Unit::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description', 'category', 'priority', 'unit_id']);
    });

    it('is refused to someone who doesn\'t live or work there', function () {
        $community = Community::factory()->create();
        Sanctum::actingAs(residentOf(Community::factory()->for($community->company)->create())->user);

        postJson(route('api.v1.communities.service-requests.store', $community), requestPayload())->assertForbidden();
    });
});

describe('following requests', function () {
    it('shows residents only their own units\' requests', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $mine = ServiceRequest::factory()->for($community)->create(['unit_id' => $resident->residencies()->sole()->unit_id]);
        ServiceRequest::factory()->for($community)->create(['unit_id' => Unit::factory()->for($community)]);
        Sanctum::actingAs($resident->user);

        getJson(route('api.v1.communities.service-requests.index', $community))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$mine->id])
            ->assertJsonPath('meta.total', 1);
    });

    it('hides internal comments from the resident', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $request = ServiceRequest::factory()->for($community)->create(['unit_id' => $resident->residencies()->sole()->unit_id]);
        $manager = teamMember(CompanyRole::PropertyManager, $community->company, [$community]);
        Sanctum::actingAs($manager);

        postJson(route('api.v1.communities.service-requests.comments.store', [$community, $request]), ['body' => 'Plumber booked Thursday.'])->assertCreated()->assertJsonPath('data.internal', false);
        postJson(route('api.v1.communities.service-requests.comments.store', [$community, $request]), ['body' => 'Tenant has been difficult.', 'internal' => true])->assertCreated()->assertJsonPath('data.internal', true);

        getJson(route('api.v1.communities.service-requests.show', [$community, $request]))->assertJsonCount(2, 'data.comments');

        Sanctum::actingAs($resident->user);
        getJson(route('api.v1.communities.service-requests.show', [$community, $request]))
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('data.comments.0.body', 'Plumber booked Thursday.');

        postJson(route('api.v1.communities.service-requests.comments.store', [$community, $request]), ['body' => 'Sneaky note', 'internal' => true])->assertForbidden();
        postJson(route('api.v1.communities.service-requests.comments.store', [$community, $request]), ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
        expect(ServiceRequestComment::count())->toBe(2);
    });

    it('lets the team move the status along, refusing impossible steps', function () {
        $community = Community::factory()->create();
        $request = ServiceRequest::factory()->for($community)->create();
        Sanctum::actingAs(teamMember(CompanyRole::PropertyManager, $community->company, [$community]));

        patchJson(route('api.v1.communities.service-requests.update', [$community, $request]), ['status' => 'assigned'])
            ->assertOk()
            ->assertJsonPath('data.status', 'assigned')
            ->assertJsonPath('data.status_label', ServiceRequestStatus::Assigned->label());

        patchJson(route('api.v1.communities.service-requests.update', [$community, $request]), ['status' => 'open'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });

    it('does not let residents change the status', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community);
        $request = ServiceRequest::factory()->for($community)->create(['unit_id' => $resident->residencies()->sole()->unit_id]);
        Sanctum::actingAs($resident->user);

        patchJson(route('api.v1.communities.service-requests.update', [$community, $request]), ['status' => 'closed'])->assertForbidden();
    });
});

describe('work orders', function () {
    it('lists a vendor\'s open jobs and lets them complete one with notes', function () {
        $community = Community::factory()->create();
        $vendor = Vendor::factory()->withLogin()->create(['company_id' => $community->company_id]);
        $job = WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)->inProgress()->create();
        WorkOrder::factory()->for($community)->assignedToVendor($vendor->id)->completed()->create();
        WorkOrder::factory()->for($community)->create();
        Sanctum::actingAs($vendor->fresh()->user);

        getJson(route('api.v1.my-work-orders.index'))->assertOk()->assertJsonPath('data.*.id', [$job->id])->assertJsonPath('data.0.assignee.type', 'vendor');

        patchJson(route('api.v1.communities.work-orders.update', [$community, $job]), ['status' => 'completed', 'completion_notes' => 'Replaced the cartridge.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.completion_notes', 'Replaced the cartridge.');
    });

    it('does not let a vendor touch someone else\'s job, or see the community\'s list', function () {
        $community = Community::factory()->create();
        $vendor = Vendor::factory()->withLogin()->create(['company_id' => $community->company_id]);
        $other = WorkOrder::factory()->for($community)->create();
        Sanctum::actingAs($vendor->fresh()->user);

        patchJson(route('api.v1.communities.work-orders.update', [$community, $other]), ['status' => 'in_progress'])->assertForbidden();
        getJson(route('api.v1.communities.work-orders.index', $community))->assertForbidden();
        getJson(route('api.v1.my-work-orders.index'))->assertOk()->assertJsonCount(0, 'data');
    });

    it('refuses an impossible step', function () {
        $community = Community::factory()->create();
        $job = WorkOrder::factory()->for($community)->completed()->create();
        Sanctum::actingAs(companyAdmin($community->company));

        patchJson(route('api.v1.communities.work-orders.update', [$community, $job]), ['status' => WorkOrderStatus::Pending->value])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    });
});
