# GAP-128 — The websocket server cannot start, and nothing uses it: evidence

Date: 2026-11-18. Base: `main` at `a3743ac6289e4ba4c21ee6d6352c4922a1628c81` (after GAP-126).

## What happened

While the production host `app.zena.com.vn` was provisioned (GAP-126 session, 2026-11-18), the `zena-websocket`
systemd unit crash-looped. Its command, `php artisan websocket:serve`, exits at start. The unit was replaced on the
host by a oneshot `/bin/true` placeholder, so that `.github/workflows/production.yml` can still run
`sudo systemctl restart zena-websocket`.

Reproduced locally (2026-11-18):

```
$ php artisan websocket:serve --port=18999
Starting WebSocket server on 0.0.0.0:18999
   Error
  Interface "Ratchet\MessageComponentInterface" not found
```

## Cause

- `app/Console/Commands/WebSocketServerCommand.php` (`websocket:serve`) builds a `Ratchet\Server\IoServer` around
  `app/WebSocket/DashboardWebSocketHandler.php`, which `implements Ratchet\MessageComponentInterface`.
- Those classes come from `cboden/ratchet`. `composer.json` requires only `ratchet/pawl` (a client) and
  `ratchet/rfc6455` (the protocol). `composer.lock` has no `cboden/ratchet` (0 matches). So the server can never load,
  in any environment.
- This was already noted under GAP-089 (`docs/owner-decisions/GAP-089/02-design.md:41-43`), which retired
  `Dockerfile.websocket` for the same reason, and in `docs/owner-decisions/GAP-126/03-release.md:94`.

## Does anything depend on the websocket server?

No. Checked on `a3743ac6`:

| Place | Finding |
|---|---|
| Browser realtime client | The only client is Laravel Echo in `resources/js/bootstrap.js:31-63` (`broadcaster: 'pusher'`, default `127.0.0.1:6001`). It is imported only by `resources/js/app.js`, which is loaded only by `resources/views/layouts/app.blade.php:20`. |
| Pages that load it | 19 views extend `layouts.app`. None is returned by a live web route: `/app/projects`, `/app/tasks`, `/app/dashboard`, `/app/calendar` render `app.*` views on `layouts.operator`; `projects.index`, `tasks.index` are not routed; `DocumentController::show` (`documents.show`) is not routed. `layouts.operator` (`resources/views/layouts/operator.blade.php:10`) and `layouts.portal` do not load `app.js`. |
| Code that listens | The only `window.Echo` listener is `resources/views/notifications/index.blade.php:284`. That view is not rendered: `/app/notifications` returns `notifications.inbox` (`app/Http/Controllers/Web/NotificationPageController.php:31`). |
| Protocol match | Even if both ran, they could not talk: Echo speaks the Pusher protocol on port 6001; the Ratchet handler speaks its own JSON messages on port 8080 (`config/websocket.php:16`, `--port=8080`). |
| Server-side broadcasting | 7 events implement `ShouldBroadcast` (`app/Events/*`). Production uses `BROADCAST_DRIVER=log` (`.env.example:18`), so they are only written to the log. |
| Production readiness | `GET /api/v1/public/production/ready` (the deploy gate in `production.yml:264,379`) does not check the websocket. |
| CI | No workflow starts the websocket server (`nightly-matrix.yml:68`, GAP-093). No test runs `websocket:serve` or the handler. |
| Deploy | `production.yml:247` (cutover) and `production.yml:360` (auto-rollback) run `sudo systemctl restart zena-websocket`. `docs/runbooks/gap-049-host-provisioning.md:49-51` asks for a websocket unit "running whatever websocket server command this application uses", and `:62` grants `deploy` sudo for that restart. |

Notifications work without it: the bell and inbox read the database (GAP-124), and Slack is sent by
`app/Observers/NotificationSlackObserver.php`.

## Other dead realtime code (disclosed, not part of the fix unless the Owner asks)

These assume a realtime server that does not exist, but do not break anything:

- `app/Services/WebSocketService.php`, `app/Services/WebSocketClient.php`, `app/Services/DashboardRealTimeService.php`,
  `config/websocket.php`.
- Admin API `api/websocket/{info,stats,channels,test}` (`routes/api.php:1064-1070`, `Api\WebSocketController`) and
  `api/.../realtime/*` (`routes/api.php:286-295`, `Api\RealTimeController`).
- Health probes `HealthController::checkWebSocket()` and `SystemHealthController::checkWebSocketHealth()` look for a
  server on port 6001 and report it unreachable.
- Echo/`pusher-js` in `resources/js/bootstrap.js`, loaded only by the unused `layouts.app`.
- `scripts/start-websocket-server.sh`; 4 test files (`tests/Feature/WebSocketTest.php`, `tests/Feature/Api/WebSocketTest.php`,
  `tests/Feature/RealtimeSyncWebSocket*.php`) cover the services/API above, not the server.

## Options for the Owner

**A — Remove the dead server (recommended).** Delete `websocket:serve`, `DashboardWebSocketHandler`,
`scripts/start-websocket-server.sh` and their PHPStan baseline entries; drop the two `systemctl restart zena-websocket`
lines from `production.yml`; drop the websocket unit and its sudo line from the provisioning runbook. On the host, after
the change is deployed, the Owner (or whoever holds root) disables and deletes the placeholder unit and the sudo line.
Users see no change. Revert = revert the PR (and recreate the placeholder unit).

**B — Add `cboden/ratchet` and really run it.** The server would start, but no page would connect to it (see the table:
Echo speaks a different protocol on a different port, and no live page loads Echo). Getting real live updates on screen
is a new feature (choose a server such as Laravel Reverb, configure Echo, build listeners in the operator pages), which
is its own business request, not a fix.

**C — Defer.** Keep the `/bin/true` placeholder. Costs: a unit and a sudo grant that do nothing, a runbook that asks for
a server that cannot run, and every deploy "restarts" nothing.
