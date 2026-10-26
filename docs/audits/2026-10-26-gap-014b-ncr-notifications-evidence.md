# GAP-014b — Evidence: NCR notifications are written but can never run

Base: `681043875ecda4c0cd19a19908c2dfbb41031194` (main after GAP-013).

## Where NCRs change

- Create: `Api\InspectionController::storeNcr()` (`POST /api/zena/inspections/{inspection}/ncrs`), status `open`,
  `created_by` = actor, optional `assigned_to` (same tenant).
- Status: `Api\InspectionController::updateNcrStatus()`; transitions open → in_progress → resolved → closed.
- The web pages (`Web\InspectionPageController::storeNcr` / `updateNcrStatus`) call the same API controller methods.
- Neither method creates a notification or dispatches an event.

## The existing notification code is dead and broken

`app/Listeners/NcrEventListener.php` has handlers for "created", "updated", "assigned" and "resolved", but:
1. **The event classes it imports do not exist**: `App\Events\NcrCreated`, `NcrUpdated`, `NcrAssigned`, `NcrResolved`
   (`app/Events/` has no NCR class).
2. Nothing dispatches them, and the listener is not registered in `app/Providers/EventServiceProvider.php`.
3. Even if called, its `Notification::create()` calls use `message` and `channels`, which are not fillable columns
   (`app/Models/Notification.php:73-87` has `body`, `channel`), and `data` is double-encoded with `json_encode` although the
   model casts it to an array.
4. The "created" handler notifies every member of every project team.

## Consequence

Nobody is told when an NCR is raised, assigned or resolved; the assignee has to find it in the list.

## Notes for Gate 2

- Recipients per step (e.g. assignee on create, creator on resolved/closed, project PM).
- Where to send from: directly in the controller/service (like GAP-012/013) or by creating the missing events.
- What to do with the dead listener (fix or delete).
