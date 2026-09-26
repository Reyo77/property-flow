<?php

use App\Models\Community;
use App\Models\Unit;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\getJson;

beforeEach(fn () => Storage::fake('local'));

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
    })->with('community endpoints');

    it('requires a token', function (string $route, Closure $make) {
        $community = Community::factory()->create();

        getJson(endpointUrl($route, $community, $make($community)))
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);
    })->with('community endpoints');

    it('is a 404 for another company\'s community or record', function (string $route, Closure $make) {
        $foreign = Community::factory()->create();
        $parameters = $make($foreign);
        $mine = Community::factory()->create();
        Sanctum::actingAs(companyAdmin($mine->company));

        getJson(endpointUrl($route, $foreign, $parameters))->assertNotFound()->assertExactJson(['message' => 'Not found.', 'code' => 'not_found']);

        if ($parameters !== []) {
            getJson(endpointUrl($route, $mine, $parameters))->assertNotFound();
        }
    })->with('community endpoints');

    it('is a 403 for a resident of another community in the same company', function (string $route, Closure $make) {
        $community = Community::factory()->create();
        $parameters = $make($community);
        Sanctum::actingAs(residentOf(Community::factory()->for($community->company)->create())->user);

        getJson(endpointUrl($route, $community, $parameters))->assertForbidden()->assertJsonPath('code', 'forbidden');
    })->with('community endpoints');
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
