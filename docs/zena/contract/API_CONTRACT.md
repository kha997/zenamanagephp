# ZENA API Contract

## Success envelope
Every successful ZENA response carries:
- `success`: always `true`.
- `status`: the HTTP status code (e.g., `200`, `201`).
- `status_text`: a human hint such as `"success"`.
- `data`: the payload (object or array). When a list is paginated, the `meta.pagination` block appears; otherwise `meta` is omitted.

Example for a paginated list:
```json
{
  "success": true,
  "status": 200,
  "status_text": "success",
  "data": [],
  "meta": {
    "pagination": {"current_page": 1, "per_page": 15, "total": 0}
  }
}
```

## Error envelope
Errors are wrapped inside an `error` object with `id`, `code`, `message`, and `details`. Status codes mirror the HTTP response.
- Authentication errors: `E401.AUTHENTICATION` (401).
- Authorization failures: `E403.AUTHORIZATION` (403).
- Missing resources: `E404.NOT_FOUND` (404).
- Tenant header issues surface `TENANT_REQUIRED` (400) or `TENANT_INVALID` (403).

```json
{
  "error": {
    "id": "b8d4c3",
    "code": "E401.AUTHENTICATION",
    "message": "Unauthorized",
    "details": []
  }
}
```

## Tenant header
Every protected route expects `X-Tenant-ID`. Requests without it return 400/TENANT_REQUIRED, while malformed IDs yield 403/TENANT_INVALID. Treat the header as mandatory metadata before RBAC kicks in.

## Login throttling
`POST /api/zena/auth/login` is guarded by `throttle:zena-login` (roughly 10 attempts per minute) so clients must handle 429 responses before retrying.

## No debug leaks rule
Responses must never echo stack traces, exception names, `xdebug`, `symfony`, `whoops`, or debug helpers like `dump(`, `dd(`, or `var_dump`. In addition, JSON keys cannot contain `authorization`, `bearer`, `token`, or `password` substrings.
