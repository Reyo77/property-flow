<?php

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('publishes a manifest browsers accept for installing', function () {
    $manifest = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)->toHaveKeys(['name', 'short_name', 'start_url', 'display', 'icons'])
        ->and($manifest['display'])->toBe('standalone')
        ->and(collect($manifest['icons'])->pluck('sizes')->all())->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose')->all())->toContain('maskable');

    foreach ($manifest['icons'] as $icon) {
        [$width, $height] = getimagesize(public_path(ltrim($icon['src'], '/'))) ?: [0, 0];

        expect("{$width}x{$height}")->toBe($icon['sizes']);
    }
});

it('links the manifest and registers the service worker on every signed-in page', function () {
    actingAs(companyAdmin());

    get(route('dashboard'))
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false)
        ->assertSee('data-test="install-app"', false);
});

it('serves the offline page without signing in, and precaches it', function () {
    get(route('offline'))->assertOk()->assertSee("You're offline");

    $worker = (string) file_get_contents(public_path('sw.js'));
    $navigation = str($worker)->after("request.mode === 'navigate'")->before('return;')->toString();

    expect($worker)->toContain("'/offline'")
        // Private pages must never be kept on the device: a page request only falls back to the offline page.
        ->and($navigation)->toContain("caches.match('/offline')")
        ->and($navigation)->not->toContain('cache.put');
});
