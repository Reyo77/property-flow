<?php

use App\Models\Amenity;
use App\Models\AmenityBooking;
use App\Models\Announcement;
use App\Models\Building;
use App\Models\Community;
use App\Models\Document;
use App\Models\Event;
use App\Models\GuestPass;
use App\Models\IncidentReport;
use App\Models\Package;
use App\Models\ServiceRequest;
use App\Models\Unit;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(fn () => Storage::fake('local'));

/**
 * Every read endpoint under a community: the route name and a function that creates a record in
 * the given community and returns the extra route parameters (none for a list).
 *
 * @return array<string, array{0: string, 1: Closure(Community): array<string, int>}>
 */
function communityEndpoints(): array
{
    $none = fn (Community $community): array => [];

    return [
        'community' => ['api.v1.communities.show', $none],
        'buildings' => ['api.v1.communities.buildings.index', function (Community $c): array {
            Building::factory()->for($c)->create();

            return [];
        }],
        'units' => ['api.v1.communities.units.index', $none],
        'unit' => ['api.v1.communities.units.show', fn (Community $c) => ['unit' => Unit::factory()->for($c)->create()->id]],
        'residents' => ['api.v1.communities.residents.index', $none],
        'resident' => ['api.v1.communities.residents.show', fn (Community $c) => ['resident' => residentOf($c)->id]],
        'announcements' => ['api.v1.communities.announcements.index', $none],
        'announcement' => ['api.v1.communities.announcements.show', fn (Community $c) => ['announcement' => Announcement::factory()->for($c)->published()->create()->id]],
        'events' => ['api.v1.communities.events.index', $none],
        'event' => ['api.v1.communities.events.show', fn (Community $c) => ['event' => Event::factory()->for($c)->create()->id]],
        'document folders' => ['api.v1.communities.document-folders.index', $none],
        'documents' => ['api.v1.communities.documents.index', $none],
        'document' => ['api.v1.communities.documents.show', fn (Community $c) => ['document' => Document::factory()->for($c)->withVersion()->create()->id]],
        'service requests' => ['api.v1.communities.service-requests.index', $none],
        'service request' => ['api.v1.communities.service-requests.show', fn (Community $c) => ['serviceRequest' => ServiceRequest::factory()->for($c)->create()->id]],
        'work orders' => ['api.v1.communities.work-orders.index', $none],
        'work order' => ['api.v1.communities.work-orders.show', fn (Community $c) => ['workOrder' => WorkOrder::factory()->for($c)->create()->id]],
        'amenities' => ['api.v1.communities.amenities.index', $none],
        'amenity' => ['api.v1.communities.amenities.show', fn (Community $c) => ['amenity' => Amenity::factory()->for($c)->create()->id]],
        'amenity bookings' => ['api.v1.communities.amenity-bookings.index', $none],
        'amenity booking' => ['api.v1.communities.amenity-bookings.show', fn (Community $c) => ['amenityBooking' => AmenityBooking::factory()->for(Amenity::factory()->for($c))->create()->id]],
        'packages' => ['api.v1.communities.packages.index', $none],
        'package' => ['api.v1.communities.packages.show', fn (Community $c) => ['package' => Package::factory()->for($c)->create()->id]],
        'visitors' => ['api.v1.communities.visitors.index', $none],
        'guest passes' => ['api.v1.communities.guest-passes.index', $none],
        'guest pass' => ['api.v1.communities.guest-passes.show', fn (Community $c) => ['guestPass' => GuestPass::factory()->for($c)->create()->id]],
        'parking permits' => ['api.v1.communities.parking-permits.index', $none],
        'incident reports' => ['api.v1.communities.incident-reports.index', $none],
        'incident report' => ['api.v1.communities.incident-reports.show', fn (Community $c) => ['incidentReport' => IncidentReport::factory()->for($c)->create()->id]],
    ];
}

function endpointUrl(string $route, Community $community, array $parameters): string
{
    return route($route, ['community' => $community->id, ...$parameters]);
}

describe('every community endpoint', function () {
    it('answers the company admin', function (string $route, Closure $make) {
        $community = Community::factory()->create();
        $parameters = $make($community);
        Sanctum::actingAs(companyAdmin($community->company));

        getJson(endpointUrl($route, $community, $parameters))->assertOk()->assertJsonStructure(['data']);
    })->with(communityEndpoints());

    it('requires a token', function (string $route, Closure $make) {
        $community = Community::factory()->create();

        getJson(endpointUrl($route, $community, $make($community)))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);
    })->with(communityEndpoints());

    it('is a 404 for another company\'s community or record', function (string $route, Closure $make) {
        $foreign = Community::factory()->create();
        $parameters = $make($foreign);
        $mine = Community::factory()->create();
        Sanctum::actingAs(companyAdmin($mine->company));

        getJson(endpointUrl($route, $foreign, $parameters))->assertNotFound()->assertExactJson(['message' => 'Not found.', 'code' => 'not_found']);

        if ($parameters !== []) {
            getJson(endpointUrl($route, $mine, $parameters))->assertNotFound();
        }
    })->with(communityEndpoints());

    it('is a 403 for a resident of another community in the same company', function (string $route, Closure $make) {
        $community = Community::factory()->create();
        $parameters = $make($community);
        Sanctum::actingAs(residentOf(Community::factory()->for($community->company)->create())->user);

        getJson(endpointUrl($route, $community, $parameters))->assertForbidden()->assertJsonPath('code', 'forbidden');
    })->with(communityEndpoints());
});

describe('list conventions', function () {
    it('pages with data, links and meta', function () {
        $community = Community::factory()->create();
        Unit::factory()->for($community)->count(3)->sequence(['number' => '101'], ['number' => '102'], ['number' => '103'])->create();
        Sanctum::actingAs(companyAdmin($community->company));

        getJson(route('api.v1.communities.units.index', ['community' => $community, 'per_page' => 2, 'sort' => '-number']))
            ->assertOk()
            ->assertJsonPath('data.*.number', ['103', '102'])
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next']]);
    });

    it('rejects unknown filters and sorts, bad enum values and page sizes', function (array $query, string $field) {
        $community = Community::factory()->create();
        Sanctum::actingAs(companyAdmin($community->company));

        getJson(route('api.v1.communities.service-requests.index', ['community' => $community, ...$query]))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors($field);
    })->with([
        'unknown filter' => [['filter' => ['colour' => 'red']], 'filter.colour'],
        'bad enum value' => [['filter' => ['status' => 'bogus']], 'filter.status'],
        'unknown sort' => [['sort' => 'title'], 'sort'],
        'page too big' => [['per_page' => 500], 'per_page'],
        'page too small' => [['per_page' => 0], 'per_page'],
    ]);
});
