# GAP-017 — Evidence: invitation dead ends on the live routes

Base: `a2ee71a5f3ece423f41fd0fb0b7c79da565a853f` (main after GAP-093).

## The register row is stale

`OPERATIONAL_GAP_REGISTER.md` (GAP-017) says the "invitation expired" link renders the missing view
`invitations.expired`. That call is in `app/Http/Controllers/Web/InvitationController.php:145-154` (`accept()`), but no
route points to that method any more:
- `GET /invitations/accept/{token}` is served by `Web\AuthenticatedInvitationAcceptController::show`
  (`routes/web.php:471-473`, middleware `auth`, `tenant.isolation`, `throttle:invitation-accept`);
- `php artisan route:list` shows no other route to `Web\InvitationController::accept`. The legacy
  `routes/legacy/api_v1.php:129-130` points to `Api\InvitationController`, and those routes are not mounted.

So the missing `invitations.expired` view cannot be reached today.

## What a user can actually hit

1. **`GET /invitations/decline/{token}` always returns HTTP 500.** The route (`routes/web.php:476`, no middleware)
   points to `Web\InvitationController::decline`, and that method does not exist. Local reproduction on the base above:
   - `GET /invitations/decline/abc123` → `500`; log: `BadMethodCallException: Method
     App\Http\Controllers\Web\InvitationController::decline does not exist`.
   - `GET /invite/decline/abc123` → `301` to the same URL (`routes/web.php:573`), then `500`.
   - The route needs no login, so anyone with the URL gets a 500.
   - `git log -S'function decline'` finds no commit that ever added the method.
   - No view, email or test in the repo links to the decline URL; the only links are the route itself and the legacy
     redirect.
2. **Expired invitation on the accept page.** `TeamInvitationService::resolveByToken()` does not filter by expiry, so a
   signed-in invitee sees the normal accept page with the expiry date (`resources/views/invitations/accept.blade.php:29`).
   Accepting goes through `Api\InvitationController::accept`, which turns a service conflict into an error envelope; this is
   not a 500. Whether an expired invitation should show its own page is a product question, not a crash.

## Unreachable code found on the way

`Web\InvitationController::accept()` and `processAcceptance()` have no route. They are dead code, and the only place that
references the missing `invitations.expired` view.

## Notes for Gate 2

- Options for the decline route: implement decline on the current invitation model (`TeamInvitationService`), or remove
  the route and its legacy redirect because nothing links to them.
- Add a regression test for whichever option is chosen.
- Whether to delete the unreachable `accept()`/`processAcceptance()` methods.
