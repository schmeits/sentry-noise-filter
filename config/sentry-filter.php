<?php

declare(strict_types=1);

/**
 * config/sentry-filter.php
 *
 * Configuration for the Sentry noise filter.
 * Publish with: php artisan vendor:publish --tag=sentry-filter-config
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Enable/disable the noise filter
    |--------------------------------------------------------------------------
    */
    'enabled' => env('SENTRY_FILTER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Bot patterns
    |--------------------------------------------------------------------------
    |
    | Exception types and messages from bots hitting Livewire/Filament endpoints
    | with invalid payloads. These are never real application errors.
    |
    */
    'bot_patterns' => [
        'CannotUpdateLockedPropertyException',
        'RootTagMissingFromViewException',
        'Filament\\Notifications\\Collection::fromLivewire',
        'Cannot assign array to property Filament\\Notifications\\Livewire\\Notifications',
        'Cannot assign array to property Filament\\Pages\\BasePage',
    ],

    /*
    |--------------------------------------------------------------------------
    | Transient infrastructure patterns
    |--------------------------------------------------------------------------
    |
    | Errors from temporary service outages (server updates, restarts).
    | These resolve themselves and don't require developer action.
    |
    */
    'infra_patterns' => [
        'Connection refused',
        'MySQL server has gone away',
        'Redis server went away',
    ],

    /*
    |--------------------------------------------------------------------------
    | Development patterns
    |--------------------------------------------------------------------------
    |
    | Errors that should only occur in local development but sometimes
    | leak to error tracking when the DSN is configured locally.
    |
    */
    'dev_patterns' => [
        'Vite manifest not found',
        'Command "boost" is not defined',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra patterns (project-specific)
    |--------------------------------------------------------------------------
    |
    | Add additional patterns here for project-specific noise.
    | These are checked against both exception type and message.
    |
    */
    'extra_patterns' => [],

];
