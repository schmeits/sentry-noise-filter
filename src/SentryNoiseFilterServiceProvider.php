<?php

declare(strict_types=1);

/**
 * src/SentryNoiseFilterServiceProvider.php
 *
 * Registers the noise filter as a Sentry before_send callback.
 * Auto-discovered by Laravel — no manual registration needed.
 */

namespace Schmeits\SentryNoiseFilter;

use Illuminate\Support\ServiceProvider;

class SentryNoiseFilterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sentry-filter.php', 'sentry-filter');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sentry-filter.php' => config_path('sentry-filter.php'),
            ], 'sentry-filter-config');
        }

        // Hook into Sentry's before_send via config override
        $this->app->booted(function () {
            if (! config('sentry-filter.enabled', true)) {
                return;
            }

            $existingBeforeSend = config('sentry.before_send');
            $noiseFilter = new NoiseFilter;

            config(['sentry.before_send' => function (\Sentry\Event $event) use ($existingBeforeSend, $noiseFilter): ?\Sentry\Event {
                // Run the noise filter first
                $result = $noiseFilter($event);

                if ($result === null) {
                    return null;
                }

                // Chain with any existing before_send from the project
                if (is_callable($existingBeforeSend)) {
                    return $existingBeforeSend($event);
                }

                return $event;
            }]);
        });
    }
}
