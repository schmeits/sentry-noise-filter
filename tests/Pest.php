<?php

declare(strict_types=1);

/**
 * tests/Pest.php
 *
 * Standalone Pest bootstrap. The package depends on Laravel's `config()` helper
 * at runtime, but the tests run without booting Laravel. We provide a minimal
 * fallback `config()` helper that reads from a per-test array, so each test can
 * declare exactly which patterns are configured.
 */

if (! function_exists('config')) {
    /**
     * Minimal config() shim for the test suite. Reads from the static store
     * populated by setTestConfig() and returns null/default for missing keys.
     */
    function config(string|array|null $key = null, mixed $default = null): mixed
    {
        $store = \Schmeits\SentryNoiseFilter\Tests\TestConfig::all();

        if ($key === null) {
            return $store;
        }

        if (is_array($key)) {
            // setting via array, not used in our tests but keeps the helper honest
            \Schmeits\SentryNoiseFilter\Tests\TestConfig::merge($key);

            return null;
        }

        $segments = explode('.', $key);
        $value = $store;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

uses()->beforeEach(function (): void {
    \Schmeits\SentryNoiseFilter\Tests\TestConfig::reset();
})->in(__DIR__);
