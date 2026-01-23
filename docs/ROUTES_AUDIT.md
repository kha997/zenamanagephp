# Routes Audit

This lightweight tooling gives a snapshot of every registered route, highlights duplicates or overrides, and surfaces legacy \`InspectionController\` stubs so the team can migrate surfaces incrementally.

## Running the audit

- Default execution (markdown output):

```
php artisan routes:audit
```

- To emit CSV, or write somewhere else, supply the flags explicitly:

```
php artisan routes:audit --format=csv --output=storage/app/routes-audit.csv
```

When \`--output\` is left at the default (\`storage/app/routes-audit.md\`), the command keeps that path for markdown but swaps the extension to \`.csv\` automatically whenever \`--format=csv\` is supplied unless you override the path yourself.

## Surface classification

Routes are grouped according to their URI prefix:

- **api_v1** when the URI starts with \`api/v1/\` (newer service modules).
- **api_zena** when the URI starts with \`api/zena/\` (legacy controller wrappers).
- **api** when the URI starts with \`api/\` but does not fall into the two previous buckets.
- **admin** when the URI starts with \`admin/\`.
- **web** for everything else.

Grouping allows a quick glance at which services still rely on legacy tooling and where new surface work belongs.

## Interpreting the Markdown report

The default Markdown report is divided into five sections:

1. **Summary** — high-level counts for total routes scanned, duplicates, middleware overrides, and stub candidates.
2. **Duplicates** — each \`METHOD + URI\` pair that ships multiple actions is shown with every controller/method combination that is registered for it.
3. **Overrides** — two subsections highlight (a) repeated route names (common when legacy and canonical routes coexist) and (b) URIs registered with different middleware stacks (a possible sign one surface shadows another during guard transitions).
4. **Stub Candidates** — every action that references \`InspectionController\` is listed with its middleware and group so you can plan an extraction or retirement.
5. **Full Inventory** — the complete route table with method, URI, name, action, middleware list, and assigned group bucket.

## CSV output

- The CSV includes the same basic columns as the Markdown inventory plus:
  - \`duplicate_key\`: populated for rows whose \`METHOD + URI\` appears more than once.
  - \`duplicate_count\`: the total number of entries that share that key.

This format is handy when loading data into spreadsheets or filtering programmatically.

## Stub heuristics

A route is marked as a “stub candidate” when its action references \`InspectionController\`, either because it already lives in \`app/Http/Controllers/Api/InspectionController.php\` or because the action name matches that controller. The assumption is those endpoints currently return canned inspection messages and can be migrated as a single unit.

Use this documentation together with the generated report to plan a safe and measurable migration toward the canonical surfaces.

## How to use `routes:audit` output to plan migrations

Run `php artisan routes:audit` (markdown or CSV) and focus on the rows grouped under `api` and `api_zena` because they represent the legacy wiring you are replacing. Once the `X-API-Legacy` telemetry is flowing, sort those routes by the observed request volume and prioritize the ones with the highest legacy traffic—those conversions yield the biggest wins. Combine the audit report with the telemetry to balance risk (many callers) against effort (routes that are easy to retarget) before flipping additional canonical flags.
