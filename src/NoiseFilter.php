<?php

declare(strict_types=1);

/**
 * src/NoiseFilter.php
 *
 * Core filtering logic. Checks Sentry events against configured noise patterns
 * and returns null for events that should be suppressed.
 *
 * Two pattern types are supported:
 *  - "Simple" patterns (bot_patterns, infra_patterns, dev_patterns, extra_patterns):
 *    a single substring matched against the exception type + message.
 *  - "Stack" patterns (stack_patterns): a pair of substrings, one matched against
 *    the exception type/message and one against the stacktrace file paths. Both
 *    must match. Useful when the message alone is too generic to filter on, but
 *    the combination with a specific vendor frame is uniquely bot-spam.
 */

namespace Schmeits\SentryNoiseFilter;

use Sentry\Event;
use Sentry\ExceptionDataBag;

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

        if (! $exception instanceof ExceptionDataBag) {
            return $event;
        }

        if ($this->matchesSimplePattern($exception)) {
            return null;
        }

        if ($this->matchesStackPattern($exception)) {
            return null;
        }

        return $event;
    }

    /**
     * Match against the flat substring lists.
     */
    private function matchesSimplePattern(ExceptionDataBag $exception): bool
    {
        $searchable = $this->buildSearchable($exception);

        $allPatterns = array_merge(
            (array) config('sentry-filter.bot_patterns', []),
            (array) config('sentry-filter.infra_patterns', []),
            (array) config('sentry-filter.dev_patterns', []),
            (array) config('sentry-filter.extra_patterns', []),
        );

        foreach ($allPatterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                continue;
            }

            if (str_contains($searchable, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Match against {value, frame} pairs. Both must match for suppression.
     */
    private function matchesStackPattern(ExceptionDataBag $exception): bool
    {
        $patterns = (array) config('sentry-filter.stack_patterns', []);

        if ($patterns === []) {
            return false;
        }

        $searchable = $this->buildSearchable($exception);
        $frameFiles = $this->collectFrameFiles($exception);

        foreach ($patterns as $pattern) {
            if (! is_array($pattern)) {
                continue;
            }

            $valueNeedle = $pattern['value'] ?? null;
            $frameNeedle = $pattern['frame'] ?? null;

            if (! is_string($valueNeedle) || $valueNeedle === '') {
                continue;
            }

            if (! is_string($frameNeedle) || $frameNeedle === '') {
                continue;
            }

            if (! str_contains($searchable, $valueNeedle)) {
                continue;
            }

            foreach ($frameFiles as $file) {
                if (str_contains($file, $frameNeedle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function buildSearchable(ExceptionDataBag $exception): string
    {
        return ($exception->getType() ?? '').' '.($exception->getValue() ?? '');
    }

    /**
     * @return list<string>
     */
    private function collectFrameFiles(ExceptionDataBag $exception): array
    {
        $stacktrace = $exception->getStacktrace();

        if ($stacktrace === null) {
            return [];
        }

        $files = [];

        foreach ($stacktrace->getFrames() as $frame) {
            $file = $frame->getFile();
            if (is_string($file) && $file !== '') {
                $files[] = $file;
            }

            $abs = $frame->getAbsoluteFilePath();
            if (is_string($abs) && $abs !== '') {
                $files[] = $abs;
            }
        }

        return $files;
    }
}
