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
