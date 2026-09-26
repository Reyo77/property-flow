<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outgoing webhooks
    |--------------------------------------------------------------------------
    |
    | How deliveries are made to the endpoints companies register. Each delivery is tried once
    | and then retried after each of the `retry_after_seconds` delays; once they are used up it
    | is marked failed. An endpoint that fails `disable_after_failures` deliveries in a row is
    | switched off until an admin turns it back on.
    |
    */

    'timeout_seconds' => 10,

    'retry_after_seconds' => [60, 300, 1800, 7200, 21600],

    'disable_after_failures' => 15,

    // Refuse to deliver to addresses on private, loopback or reserved networks, so an endpoint
    // can't be used to reach services inside our own network. Off only for local development.
    'block_private_networks' => (bool) env('WEBHOOKS_BLOCK_PRIVATE_NETWORKS', true),

    // Endpoints must use https, except in local development.
    'require_https' => (bool) env('WEBHOOKS_REQUIRE_HTTPS', true),

];
