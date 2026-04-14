<?php

declare(strict_types=1);

/**
 * src/SentryNoiseFilterServiceProvider.php
 *
 * Registers Sentry exception handling + noise filter automatically.
 * Auto-discovered by Laravel — no manual registration or bootstrap/app.php changes needed.
 *
 * Hooks the before_send callback directly on the Sentry client's Options object
 * instead of via Laravel config, so `config:cache` remains possible (closures can't be cached).
 */

namespace Schmeits\SentryNoiseFilter;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Sentry\Laravel\Integration;
use Sentry\SentrySdk;

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
     * Safe to use alongside Integration::handles() — dedup in before_send prevents double events.
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
     * Hook the noise filter + dedup into Sentry's before_send callback.
     * Wraps any existing before_send from the Sentry client options.
     *
     * Uses the Sentry client's Options directly (not Laravel config) so `config:cache`
     * continues to work — closures can't be serialized to a compiled config cache.
     */
    private function registerNoiseFilter(): void
    {
        $client = SentrySdk::getCurrentHub()->getClient();

        if ($client === null) {
            return;
        }

        $options = $client->getOptions();
        $existingBeforeSend = $options->getBeforeSendCallback();
        $noiseFilter = config('sentry-filter.enabled', true) ? new NoiseFilter : null;
        $sentThisRequest = [];

        $options->setBeforeSendCallback(function (\Sentry\Event $event, ?\Sentry\EventHint $hint = null) use ($existingBeforeSend, $noiseFilter, &$sentThisRequest): ?\Sentry\Event {
            // Dedup: prevent double events from multiple exception handlers
            $exceptions = $event->getExceptions();
            if (! empty($exceptions)) {
                $sig = ($exceptions[0]->getType() ?? '') . ':' . ($exceptions[0]->getValue() ?? '');

                if (in_array($sig, $sentThisRequest, true)) {
                    return null;
                }

                $sentThisRequest[] = $sig;
            }

            // Noise filter
            if ($noiseFilter !== null) {
                $result = $noiseFilter($event);

                if ($result === null) {
                    return null;
                }
            }

            // Chain with any existing before_send from the project
            if (is_callable($existingBeforeSend)) {
                return $existingBeforeSend($event, $hint);
            }

            return $event;
        });
    }
}
