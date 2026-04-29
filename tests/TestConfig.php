<?php

declare(strict_types=1);

/**
 * tests/TestConfig.php
 *
 * Per-test in-memory config store, used by the config() shim in Pest.php so
 * each test can declare which `sentry-filter.*` values are present without
 * booting Laravel.
 */

namespace Schmeits\SentryNoiseFilter\Tests;

class TestConfig
{
    /**
     * @var array<string, mixed>
     */
    private static array $store = [];

    public static function reset(): void
    {
        self::$store = [
            'sentry-filter' => [
                'enabled' => true,
                'bot_patterns' => [],
                'infra_patterns' => [],
                'dev_patterns' => [],
                'extra_patterns' => [],
                'stack_patterns' => [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function merge(array $values): void
    {
        foreach ($values as $key => $value) {
            self::set($key, $value);
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $store = &self::$store;

        foreach ($segments as $i => $segment) {
            if ($i === array_key_last($segments)) {
                $store[$segment] = $value;

                return;
            }

            if (! isset($store[$segment]) || ! is_array($store[$segment])) {
                $store[$segment] = [];
            }

            $store = &$store[$segment];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return self::$store;
    }
}
