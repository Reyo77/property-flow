<?php

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('sets baseline security headers on every response', function () {
    get(route('home'))
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy');
});

it('sets security headers on an authenticated page too', function () {
    actingAs(companyAdmin());

    get(route('dashboard'))
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Content-Security-Policy');
});

it('allows the reverb websocket origin in connect-src, so live features are not silently blocked', function () {
    config([
        'broadcasting.connections.reverb.options.host' => 'reverb.example.com',
        'broadcasting.connections.reverb.options.port' => 8080,
        'broadcasting.connections.reverb.options.scheme' => 'https',
    ]);

    $response = get(route('home'));
    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("connect-src 'self' wss://reverb.example.com:8080");
});
