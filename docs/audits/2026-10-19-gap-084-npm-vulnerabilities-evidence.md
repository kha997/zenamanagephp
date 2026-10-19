# GAP-084 — Evidence: npm audit findings

Base: `2a01adeaeb854bafb081cc04b5541a45c78c2cde` (main after GAP-083).
Sources:
- `npm audit --package-lock-only` on the base lock.
- `npm audit fix --package-lock-only` dry run. Run on a scratch copy; nothing was committed.
- The repo's front-end build files.

## Findings on the base: 17 (12 high, 4 moderate, 1 low, 0 critical)

| Package | Severity | Kind | Fix |
|---|---|---|---|
| `axios` | high | runtime dependency (browser) | in range (1.20+) |
| `postcss` | high | direct dev | in range |
| `browserslist`, `nanoid`, `source-map-js`, `socket.io-parser`, `fast-glob`* | high | transitive | in range |
| `baseline-browser-mapping`, `postcss-nested`* | moderate | transitive | in range |
| `vite` 4.5.14 | high | direct dev (dev server and build) | **major only**: vite 8 |
| `esbuild` 0.18 | moderate | via vite | **major only**: vite 8 |
| `laravel-vite-plugin` 0.8.1 | low | direct dev | **major only**: 3.x (follows vite) |
| `tailwindcss` 3.4.19 | high | direct dev (build only) | **major only**: tailwindcss 4 |
| `braces`, `micromatch`, `chokidar` | high | via tailwindcss 3 | **no patched release** (`braces` advisory range `<=3.0.3`; 3.0.3 is the latest release). They are removed only by moving to tailwindcss 4 |
| `postcss-selector-parser` 6.1.4 | moderate | via tailwindcss 3 | **major only**: tailwindcss 4 |

\* The in-range update leaves these packages vulnerable while tailwindcss 3 still pulls them in.

**After the in-range update** (`npm audit fix` without `--force`; `package-lock.json` only, 44 lines change): 10
findings (6 high, 3 moderate, 1 low, 0 critical). Every remaining finding is in build or dev tooling: vite,
esbuild, laravel-vite-plugin, tailwindcss and its dependency chain.

## Where the tooling is used

- `vite.config.js`: `laravel-vite-plugin`. Ten inputs: `resources/css/app.css`, `operator.css`, and eight
  `resources/js/*.js` files. Four Blade views use `@vite(...)`.
- `tailwind.config.js` (v3 JS config, `@tailwindcss/forms` plugin) and `postcss.config.js`
  (`tailwindcss`, `autoprefixer`).
- Only `resources/css/operator.css` uses the `@tailwind` directives. `resources/css/tailwind.css` uses
  `@apply`, but it is not a Vite input. Several layouts load a prebuilt `css/tailwind.css`, and 15 views load
  the Tailwind CDN.
- Build sites:
  - `Dockerfile.prod`: `node:18-alpine`, `npm ci`, `npm run build`. Vite 8 needs Node ≥ 20.19.
  - `production.yml` (retired, GAP-049).
  - `staging-smoke.yml`: Node 20, `npm ci`.
  - `button-tests.yml`.
- The CI image (`Dockerfile`) does not build front-end assets. `public/build` is not committed.

## Upgrade impact (not executed)

- **vite 4 → 8**:
  - needs `laravel-vite-plugin` 0.8 → 3.x and Node ≥ 20.19 (`Dockerfile.prod` is on 18);
  - the config shape above is supported.
- **tailwindcss 3 → 4**:
  - CSS-first configuration: `@import "tailwindcss"` replaces the `@tailwind` directives, and `@config` or
    `@theme` replaces the JS config;
  - PostCSS plugin moves to `@tailwindcss/postcss`, or the Vite plugin is used;
  - changed defaults: border colour, ring width, shadow and radius scale renames;
  - modern-browser baseline (Safari 16.4+, Chrome 111+).

  Expect visible style differences in the Operator zone that need a visual check.
