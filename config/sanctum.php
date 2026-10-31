<?php

// Token-only API auth for the SPA (no cookie/session mode). Package defaults fill any missing keys.
return [
    'stateful' => [],
    'guard' => [],
    'expiration' => (int) env('SANCTUM_EXPIRATION', 60 * 24 * 14), // minutes
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];
