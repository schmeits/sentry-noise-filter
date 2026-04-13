<?php

declare(strict_types=1);

/**
 * src/NoiseFilter.php
 *
 * Core filtering logic. Checks Sentry events against configured noise patterns
 * and returns null for events that should be suppressed.
 */

namespace Schmeits\SentryNoiseFilter;

use Sentry\Event;

class NoiseFilter
{
    /**
     * Filter a Sentry event. Returns null to suppress, or the event to send.
     */
    public function __invoke(Event $event): ?Event
    {
        if (! config('sentry-filter.enabled', true)) {
            return $event;
        }

        $exception = $event->getExceptions()[0] ?? null;

        if (! $exception) {
            return $event;
        }

        $type = $exception->getType() ?? '';
        $value = $exception->getValue() ?? '';
        $searchable = $type . ' ' . $value;

        // Check all pattern groups
        $allPatterns = array_merge(
            config('sentry-filter.bot_patterns', []),
            config('sentry-filter.infra_patterns', []),
            config('sentry-filter.dev_patterns', []),
            config('sentry-filter.extra_patterns', []),
        );

        foreach ($allPatterns as $pattern) {
            if (str_contains($searchable, $pattern)) {
                return null;
            }
        }

        return $event;
    }
}
