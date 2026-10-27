# GAP-076 — Evidence: docker/php/php.ini breaks the production image at runtime

Base: `e7358e0a9dd9cc35e00bfef1aa6dad1329c4d5ae` (main after GAP-075).

`docker/php/php.ini` is copied into `Dockerfile.prod`, `Dockerfile`,
`Dockerfile.websocket`, and mounted by `docker-compose.yml`.

## 1. Functions the app needs are disabled

`disable_functions = exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source`
(lines 14 and 156). Reproduced with the file mounted into `php:8.2-fpm-alpine`:

```
function_exists("proc_open")  => false
is_callable("exec")           => false
is_callable("curl_exec")      => false
is_callable("parse_ini_file") => false
```

Impact in the production image:

- `artisan schedule:work` (supervisord `laravel-schedule`) runs each task via
  Symfony Process → `proc_open` → scheduled tasks cannot run.
- Backups (`DatabaseBackupService`, `Backup/MysqlClient`, `BackupCommand`)
  call `mysqldump`/`mysql` through processes → backups fail.
- Outgoing HTTP (Laravel `Http::`/Guzzle in 13 app files, Sentry) uses
  `curl_exec`/`curl_multi_exec` → calls fail.
- Worker/metrics commands that shell out also fail.

## 2. Broken extension lines

`extension=pdo_mysql, mbstring, openssl, curl, gd, zip, intl, redis, imagick, opcache`
(lines 64–73). In the official image these are either compiled in (openssl,
curl), already enabled by `docker-php-ext-*` (pdo_mysql, mbstring, gd, zip,
intl, redis), not installed (imagick, removed in GAP-071), or a Zend extension
(`opcache` must be `zend_extension`). PHP logs a startup warning for each
("Unable to load dynamic library …", "Invalid library (appears to be a Zend
Extension…)").

## 3. Other settings that conflict with the app

- `opcache.save_comments = 0` drops docblocks; `l5-swagger` reads `@OA\`
  annotations (4 files in `app/`).
- `opcache.file_cache=/tmp/opcache` (opcache.ini) points to a directory that is
  never created.
- `session.save_path = "tcp://redis:6379"` assumes a `redis` host that the
  single-container image does not have (only used if native PHP sessions are
  started).
- The `[opcache]` block is duplicated in `php.ini` and `opcache.ini`.

## What is not affected

GAP-074's runtime smoke only checks `/api/health` and `/robots.txt`, which do
not use processes or outgoing HTTP, so it stays green while these features are
broken.
