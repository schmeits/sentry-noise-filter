<?php

declare(strict_types=1);

/**
 * tests/NoiseFilterTest.php
 *
 * Unit tests for the NoiseFilter service. Both pattern types are covered:
 * the flat substring lists and the stack-aware {value, frame} pairs.
 */

namespace Schmeits\SentryNoiseFilter\Tests;

use Schmeits\SentryNoiseFilter\NoiseFilter;
use Sentry\Event;
use Sentry\ExceptionDataBag;
use Sentry\Frame;
use Sentry\Stacktrace;

/**
 * @param  list<string>  $framePaths  list of file paths to put in the stacktrace
 */
function makeEvent(string $type, string $value, array $framePaths = []): Event
{
    $stacktrace = null;

    if ($framePaths !== []) {
        $frames = array_map(
            static fn (string $path): Frame => new Frame(null, $path, 1, null, $path),
            $framePaths
        );

        $stacktrace = new Stacktrace($frames);
    }

    $exception = new ExceptionDataBag(new \RuntimeException($value), $stacktrace);
    // ExceptionDataBag derives its type from the throwable. To assert against an
    // explicit type string, override it after construction.
    $exception->setType($type);
    $exception->setValue($value);

    $event = Event::createEvent();
    $event->setExceptions([$exception]);

    return $event;
}

test('passes events through when no patterns match', function (): void {
    TestConfig::set('sentry-filter.bot_patterns', ['SomethingElse']);

    $event = makeEvent('LogicException', 'oh no');

    expect((new NoiseFilter)($event))->toBe($event);
});

test('suppresses events that match a simple pattern', function (): void {
    TestConfig::set('sentry-filter.bot_patterns', ['CannotUpdateLockedPropertyException']);

    $event = makeEvent('Livewire\\CannotUpdateLockedPropertyException', 'locked');

    expect((new NoiseFilter)($event))->toBeNull();
});

test('suppresses events that match a stack pattern when both value and frame match', function (): void {
    TestConfig::set('sentry-filter.stack_patterns', [
        [
            'value' => 'Trying to access array offset on null',
            'frame' => 'Mechanisms/HandleComponents/HandleComponents.php',
        ],
    ]);

    $event = makeEvent(
        'ErrorException',
        'Trying to access array offset on null',
        ['/vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php']
    );

    expect((new NoiseFilter)($event))->toBeNull();
});

test('does not suppress when only the value matches but no frame does', function (): void {
    TestConfig::set('sentry-filter.stack_patterns', [
        [
            'value' => 'Trying to access array offset on null',
            'frame' => 'Mechanisms/HandleComponents/HandleComponents.php',
        ],
    ]);

    $event = makeEvent(
        'ErrorException',
        'Trying to access array offset on null',
        ['/app/Http/Controllers/SomeController.php']
    );

    expect((new NoiseFilter)($event))->toBe($event);
});

test('does not suppress when only the frame matches but the value does not', function (): void {
    TestConfig::set('sentry-filter.stack_patterns', [
        [
            'value' => 'Trying to access array offset on null',
            'frame' => 'Mechanisms/HandleComponents/HandleComponents.php',
        ],
    ]);

    $event = makeEvent(
        'TypeError',
        'Some unrelated error',
        ['/vendor/livewire/livewire/src/Mechanisms/HandleComponents/HandleComponents.php']
    );

    expect((new NoiseFilter)($event))->toBe($event);
});

test('suppresses boost-namespace errors leaking from production composer scripts', function (): void {
    TestConfig::set('sentry-filter.dev_patterns', [
        'There are no commands defined in the "boost"',
    ]);

    $event = makeEvent(
        'Symfony\\Component\\Console\\Exception\\NamespaceNotFoundException',
        'There are no commands defined in the "boost" namespace.',
    );

    expect((new NoiseFilter)($event))->toBeNull();
});

test('passes events through when no exception is attached', function (): void {
    TestConfig::set('sentry-filter.bot_patterns', ['anything']);

    $event = Event::createEvent();

    expect((new NoiseFilter)($event))->toBe($event);
});

test('passes events through when filter is disabled', function (): void {
    TestConfig::set('sentry-filter.enabled', false);
    TestConfig::set('sentry-filter.bot_patterns', ['ErrorException']);

    $event = makeEvent('ErrorException', 'should still pass');

    expect((new NoiseFilter)($event))->toBe($event);
});
