# API Migration and Deprecation Flags

## Legacy vs canonical surfaces

- **Legacy** routes live under `/api/*` and `/api/zena/*` and are handled by the controllers in `app/Http/Controllers/Api` (including the legacy `InspectionController` stubs). These endpoints continue to power existing tenants until we switch them over.
- **Canonical** surfaces are the `/api/v1/*` routes wired up in the `src` modules. They represent the modern API stack with updated data contracts and middleware.

When the migration feature flags are unset (the default), every request continues to use the legacy bindings so nothing in production changes unexpectedly.

## Deprecation headers emitted by legacy endpoints

Legacy routes now emit a consistent set of headers so downstream clients can detect the migration state without parsing the response body:

- `Deprecation`: when present, this header carries an ISO 8601 timestamp marking when a legacy endpoint started showing the warning.
- `Sunset`: uses `API_MIGRATION_SUNSET` (if provided) to communicate a final removal date for legacy bindings.
- `Link`: points to `API_MIGRATION_DOCS_URL` to document migration expectations when the flag is defined.
- `X-API-Legacy`: set to `1` on every legacy response to make it easy for telemetry collectors to filter historic traffic.

These headers only appear on legacy endpoints; once a route permanently flips to the canonical controller, the headers disappear.

## Environment variables

- `API_MIGRATION_DOCS_URL` – URL surfaced in the `Link` header so clients know where to read more about the transition (default: empty).
- `API_MIGRATION_SUNSET` – an ISO 8601 timestamp used for the `Sunset` header when deprecating legacy bindings (default: empty).
- `API_CANONICAL_PROJECTS` – when set to `1`, the `/api/projects` and `/api/zena/projects` URIs bind to the canonical controllers in `src` instead of the legacy wrappers.
- `API_CANONICAL_DOCUMENTS` (reserved) – a future toggle for document-related routes; leave empty until the feature is activated.
- `API_CANONICAL_INSPECTIONS` (reserved) – a future toggle for inspection flows; leave empty until it ships.

Leaving these variables empty or unset keeps the current legacy wiring intact.

## Rollout procedure

1. **Staging** – flip `API_CANONICAL_PROJECTS=1`, enable the telemetry pipeline that records `X-API-Legacy`, and watch for errors or unexpected traffic once the hits land in `src`’s controllers.
2. **Production (headers only)** – keep the legacy wiring but emit the deprecation headers so client teams see the notice before tenants move. No route is flipped yet.
3. **Production (canary tenants)** – pick a handful of tenants, toggle `API_CANONICAL_PROJECTS=1` for them, and verify the canonical controllers serve the same data plus the telemetry remains healthy.
4. **Production (full toggle)** – once the canary runs succeed, roll `API_CANONICAL_PROJECTS=1` everywhere and retire the legacy headers/handlers.

Always rely on the telemetry collected from `X-API-Legacy` and the `routes:audit` report to surface high-usage legacy endpoints before you flip additional toggles.
