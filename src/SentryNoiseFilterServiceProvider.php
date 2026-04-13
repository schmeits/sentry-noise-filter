<?php

declare(strict_types=1);

/**
 * src/SentryNoiseFilterServiceProvider.php
 *
 * Registers Sentry exception handling + noise filter automatically.
 * Auto-discovered by Laravel — no manual registration or bootstrap/app.php changes needed.
 */

namespace Schmeits\SentryNoiseFilter;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Sentry\Laravel\Integration;

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

        $this->app->booted(function () {
            if (! config('sentry.dsn')) {
                return;
            }

            $this->registerExceptionHandler();
            $this->registerNoiseFilter();
        });
    }

    /**
     * Auto-register Sentry exception handling.
     * Replaces the need for Integration::handles($exceptions) in bootstrap/app.php.
     */
    private function registerExceptionHandler(): void
    {
        $handler = $this->app->make(ExceptionHandler::class);

        if (method_exists($handler, 'reportable')) {
            $handler->reportable(function (\Throwable $e) {
                Integration::captureUnhandledException($e);
            });
        }
    }

    /**
     * Hook the noise filter into Sentry's before_send callback.
     * Chains with any existing before_send from the project's config/sentry.php.
     */
    private function registerNoiseFilter(): void
    {
        if (! config('sentry-filter.enabled', true)) {
            return;
        }

        $existingBeforeSend = config('sentry.before_send');
        $noiseFilter = new NoiseFilter;

        config(['sentry.before_send' => function (\Sentry\Event $event) use ($existingBeforeSend, $noiseFilter): ?\Sentry\Event {
            $result = $noiseFilter($event);

            if ($result === null) {
                return null;
            }

            if (is_callable($existingBeforeSend)) {
                return $existingBeforeSend($event);
            }

            return $event;
        }]);
    }
}
