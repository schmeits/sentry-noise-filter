# Sentry Noise Filter for Laravel

A drop-in package that sets up [Sentry](https://sentry.io/) / [GlitchTip](https://glitchtip.com/) error tracking and filters common noise. Install it, set your DSN, done.

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

Add your DSN to `.env`:

```env
SENTRY_LARAVEL_DSN=https://your-key@your-glitchtip-or-sentry-instance/project-id
```

That's it. The package:
- Pulls in `sentry/sentry-laravel` automatically
- Auto-registers the exception handler (no `bootstrap/app.php` changes needed)
- Applies the noise filter to all outgoing error events

### Migrating from manual Sentry setup

If you previously had `Integration::handles($exceptions)` in `bootstrap/app.php`, you can remove it — the package handles this automatically:

```php
// bootstrap/app.php — REMOVE this block:
->withExceptions(function (Exceptions $exceptions) {
    Integration::handles($exceptions);  // No longer needed
})
```

### Recommended .env settings

```env
SENTRY_LARAVEL_DSN=https://your-key@your-instance/project-id
SENTRY_SEND_DEFAULT_PII=false
SENTRY_TRACES_SAMPLE_RATE=0
```

- `SENTRY_SEND_DEFAULT_PII=false` — Don't send personal data (IP, cookies, user info). Set to `true` only if you need it for debugging.
- `SENTRY_TRACES_SAMPLE_RATE=0` — Disables performance monitoring. Set to `0.1` (10% of requests) if you want performance data.

## Configuration

The default configuration works out of the box. To customize the filter patterns, publish the config:

```bash
php artisan vendor:publish --tag=sentry-filter-config
```

This creates `config/sentry-filter.php`:

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

The package does two things automatically on boot:

1. **Registers exception handling** — Equivalent to `Integration::handles()` in `bootstrap/app.php`, but done automatically via the ServiceProvider
2. **Applies noise filtering** — Hooks into Sentry's `before_send` callback. When an error is about to be sent, it checks the exception type and message against all configured patterns. Matches are suppressed, everything else passes through.

The noise filter **chains with existing filters** — if your project already has a custom `before_send` in `config/sentry.php`, it will still run after the noise filter.

## Disabling

Disable filtering without removing the package:

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
