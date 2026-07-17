# Horizon / Supervisor mismatch

Date: 2026-07-17
Status: chosen for implementation

## Problem

`docker/supervisor/supervisord.conf` previously started `php artisan horizon`, but `composer.json` does not require `laravel/horizon`. A production image using that supervisor config would try to start a command that is not installed.

## Decision

Do not add Horizon for this slice. Keep the existing queue-worker architecture and remove supervisor programs for packages that are not installed.

Reasons:

- `docker-compose.prod.yml` already runs queues with `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`.
- `docker/supervisord.conf` already uses the same `queue:work` pattern.
- Adding Horizon would introduce a new package, dashboard/runtime surface, and operational expectations that are not needed to fix this mismatch.

## Required behavior

- `docker/supervisor/supervisord.conf` must not run `php artisan horizon` unless `composer.json` requires `laravel/horizon`.
- The supervisor Laravel group must reference only installed/supported local programs.
- Queue processing remains handled by `php artisan queue:work`.

## Validation

- Static architecture test guards against reintroducing the mismatch.
- `docker-compose.prod.yml` and supervisor config remain aligned on `queue:work`.
