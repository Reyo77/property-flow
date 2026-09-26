<?php

use App\Models\Community;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Yaml\Yaml;

use function Pest\Laravel\get;
use function Pest\Laravel\getJson;

beforeEach(fn () => Storage::fake('local'));

/**
 * The published OpenAPI spec (regenerate with `php artisan scribe:generate` after changing the API).
 *
 * @return array<string, mixed>
 */
function openApiSpec(): array
{
    static $spec = null;

    return $spec ??= Yaml::parseFile(public_path('docs/openapi.yaml'));
}

/**
 * The documented example response for a route, or null.
 *
 * @return array<string, mixed>|null
 */
function documentedExample(string $method, string $uri, int $status = 200): ?array
{
    $operation = openApiSpec()['paths']['/'.$uri][strtolower($method)] ?? null;

    return $operation['responses'][(string) $status]['content']['application/json']['schema']['example'] ?? null;
}

it('documents every API route', function () {
    $documented = collect(openApiSpec()['paths'])->flatMap(fn (array $operations, string $path) => array_map(fn (string $method) => strtoupper($method).' '.$path, array_keys($operations)));

    $routes = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route) => str_starts_with($route->uri(), 'api/v1/'))
        // PATCH is documented as its twin PUT; HEAD comes free with GET.
        ->flatMap(fn (Route $route) => array_map(fn (string $method) => $method.' /'.$route->uri(), array_diff($route->methods(), ['HEAD', ...(in_array('PUT', $route->methods(), true) ? ['PATCH'] : [])])));

    expect($routes->diff($documented)->values()->all())->toBe([]);
});

it('returns only documented fields', function (string $route, Closure $make) {
    $community = Community::factory()->create();
    $parameters = $make($community);
    Sanctum::actingAs(companyAdmin($community->company));
    $uri = ltrim(Router::getRoutes()->getByName($route)?->uri() ?? '', '/');

    $response = getJson(route($route, ['community' => $community->id, ...$parameters]))->assertOk()->json();
    $example = documentedExample('GET', $uri);

    expect($example)->not->toBeNull("No documented example for {$route}.");

    // Fields that only appear for some viewers or when related data is loaded, documented in the
    // endpoint descriptions: a draft's scheduled time, closed results, published minutes.
    $conditional = ['publish_at', 'results', 'minutes'];
    $undocumented = fn (array $actual, array $documented) => array_values(array_diff(array_keys($actual), array_keys($documented), $conditional));

    expect($undocumented($response, $example))->toBe([], "{$route} returns undocumented top-level fields.");

    $item = array_is_list($response['data']) ? ($response['data'][0] ?? null) : $response['data'];
    $documentedItem = array_is_list($example['data']) ? ($example['data'][0] ?? null) : $example['data'];

    if ($item !== null) {
        expect($documentedItem)->not->toBeNull("{$route} documents no example item.")
            ->and($undocumented($item, $documentedItem))->toBe([], "{$route} returns undocumented fields.");
    }
})->with('community endpoints');

it('serves the docs, the OpenAPI spec and the Postman collection', function () {
    get('/docs')->assertRedirect('/docs/index.html');

    expect(file_exists(public_path('docs/index.html')))->toBeTrue()
        ->and(openApiSpec()['info']['title'])->toBe('PropertyFlow API')
        ->and(json_decode((string) file_get_contents(public_path('docs/collection.json')), true)['info']['name'])->toBe('PropertyFlow API');
});
