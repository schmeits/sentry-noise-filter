# Sentry Noise Filter for Laravel

A drop-in package that filters common noise from your [Sentry](https://sentry.io/) or [GlitchTip](https://glitchtip.com/) error tracking. Install it, set your DSN, done.

## The Problem

Laravel apps using Livewire and Filament get bombarded with errors that aren't real bugs:

- **Bot spam** — Bots hitting `/livewire/update` with invalid payloads, causing `CannotUpdateLockedPropertyException`, Filament notification TypeErrors, and `RootTagMissingFromViewException`
- **Transient infra errors** — `Connection refused`, `MySQL server has gone away`, `Redis server went away` during server updates and restarts
- **Dev leaks** — `Vite manifest not found` and dev-only command errors accidentally sent from local environments

This package silently suppresses these before they reach your error tracker, so you only see real application errors.

## Installation

```bash
composer require schmeits/sentry-noise-filter
```

That's it. The package auto-discovers its ServiceProvider — no manual registration needed.

### If you don't have Sentry configured yet

The package requires `sentry/sentry-laravel`. If it's not installed yet, it will be pulled in automatically. Then add your DSN to `.env`:

```env
SENTRY_LARAVEL_DSN=https://your-key@your-glitchtip-or-sentry-instance/project-id
```

## Configuration

The default configuration works out of the box. To customize the filter patterns, publish the config:

```bash
php artisan vendor:publish --tag=sentry-filter-config
```

This creates `config/sentry-filter.php` where you can:

```php
return [
    // Disable the filter entirely
    'enabled' => env('SENTRY_FILTER_ENABLED', true),

    // Livewire/Filament bot patterns (suppress by default)
    'bot_patterns' => [
        'CannotUpdateLockedPropertyException',
        'RootTagMissingFromViewException',
        // ...
    ],

    // Transient infrastructure errors (suppress by default)
    'infra_patterns' => [
        'Connection refused',
        'MySQL server has gone away',
        'Redis server went away',
    ],

    // Local development errors (suppress by default)
    'dev_patterns' => [
        'Vite manifest not found',
        'Command "boost" is not defined',
    ],

    // Add your own project-specific patterns
    'extra_patterns' => [
        // 'Some noisy error you want to suppress',
    ],
];
```

## How It Works

The package hooks into Sentry's `before_send` callback via the Laravel config system. When an error event is about to be sent:

1. It checks the exception type and message against all configured patterns
2. If any pattern matches, the event is suppressed (returns `null`)
3. If no pattern matches, the event is passed through to any existing `before_send` callback

This means it **chains with existing filters** — if your project already has a custom `before_send` in `config/sentry.php`, it will still run after the noise filter.

## Disabling

Set the environment variable to disable filtering without removing the package:

```env
SENTRY_FILTER_ENABLED=false
```

## Compatibility

- PHP 8.2+
- Laravel 10, 11, 12, 13
- sentry/sentry-laravel 4.x
- Works with both Sentry and GlitchTip (Sentry-compatible API)

## License

MIT
