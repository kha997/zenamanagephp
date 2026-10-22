# GAP-089 — Evidence: `Dockerfile` and `Dockerfile.websocket` do not build

Base: `8e6523e2ea31b7d9139ede916af11d7eacefa17a` (main after GAP-088).

The GAP-088 temporary proof on PR #361 built both files in CI. Neither builds as committed. The failures
below were all observed in those CI runs, except where marked "by reading".

## `Dockerfile.websocket` (Alpine, `php:8.2-fpm-alpine`)

| Line | Problem | Observed |
|---|---|---|
| 7-21 | `apk add` has no `linux-headers`, so `docker-php-ext-install sockets` fails with `linux/sock_diag.h: No such file or directory` | CI, PR #361 |
| 7-21 | `apk add` has no `$PHPIZE_DEPS` (autoconf, gcc, ...), which `pecl install` needs | CI: with both packages added in the CI workspace, the image built and `php -m` listed `redis` |
| 78 | `CMD ["php", "artisan", "websockets:serve", ..., "--port=6001"]` names a command that does not exist. The app's command is `websocket:serve` (`app/Console/Commands/WebSocketServerCommand.php`), which defaults to port 8080. | by reading |
| 71-75 | `HEALTHCHECK` and `EXPOSE` use port 6001; `docker-compose.yml` maps 8080 | by reading |

Consumers:
- `release-management.yml` builds and pushes this image on every release, so a release would fail at this step;
- `docker-compose.yml` (service `websocket`).

## `Dockerfile` (Debian, `php:8.2-fpm`)

| Line | Problem | Observed |
|---|---|---|
| 80 | `COPY docker/php/php-fpm.conf` — the file is `docker/php-fpm.conf` | CI, PR #361 (build stops before any step runs) |
| 32-40 | `docker-php-ext-install ... intl` fails | CI, PR #361 (after working around line 80); the `apt-get` list has no `libicu-dev` |
| 56-59 | `composer install` runs before `COPY . .`; Composer's `post-autoload-dump` calls `php artisan package:discover`, and `artisan` is not in the image yet | by reading; not reached in CI |

Consumers:
- `docker-compose.yml`;
- `docker-compose.prod.yml` (used by `automated-deployment.yml`);
- `docker-compose.loadbalancer.yml`.

No workflow builds this file directly.

## Related, by reading

`docker-compose.loadbalancer.yml:260` references `Dockerfile.monitoring`, which does not exist in the repository.

## Notes for Gate 2

- Decide, file by file, whether to repair it so it builds and starts, or to retire it.
- Decide how to keep the files from breaking again, for example by building them in PR CI.
- Each fix must be shown by an exact-head CI build.
