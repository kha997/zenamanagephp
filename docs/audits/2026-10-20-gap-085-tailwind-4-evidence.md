# GAP-085 — Evidence: tailwindcss 3 → 4

Base: `8bf36c4cfbc7defac9d9ed59965a83cbdecf3efe` (main after GAP-084).

Sources:
- `npm audit`.
- The repo's Blade views.
- A scratch run of the official upgrade tool `npx @tailwindcss/upgrade` (v4.3.3). It ran on a disposable worktree and nothing was committed.

## Why

`npm audit` still reports 7 findings: 5 high, 2 moderate, 0 critical. All of them are in the tailwindcss 3 build chain:
- `tailwindcss` itself;
- `braces`, `micromatch` and `chokidar`, which have no patched release;
- `fast-glob`, `postcss-nested` and `postcss-selector-parser`.

They disappear only when tailwindcss 3 is removed. GAP-084's scratch build with tailwindcss 4 measured 0 findings.

## Three ways pages get Tailwind today

| Source | Version | Who uses it |
|---|---|---|
| Vite-built `resources/css/operator.css` (`@tailwind` directives, `tailwind.config.js`) | 3.4.19, **the only one this upgrade changes** | `layouts/operator` and `layouts/portal`; 85 view files reference those layouts |
| `cdn.tailwindcss.com` `<script>` | Play CDN v3 (unversioned URL) | 15 views |
| Prebuilt `public/css/tailwind.css` | v3 output | 3 layouts (`admin-base`, `dashboard`, `app-base`) |

`resources/css/tailwind.css` (`@tailwind` + `@apply`) is not a Vite input.

## What tailwindcss 4 changes for the Vite-built CSS

- **Configuration**:
  - CSS-first: `@import "tailwindcss"`, with `@config` kept for the JS config.
  - The PostCSS plugin becomes `@tailwindcss/postcss`; `autoprefixer` is no longer needed.
  - `tailwind.config.js` mixes `export default` with `require(...)`. v4's loader cannot read it; it needs an `import`.
- **Renamed or rescaled utilities** (v3 name → what it means in v4):
  - `shadow-sm` → `shadow-xs`, and `shadow` → `shadow-sm`;
  - `rounded-sm` → `rounded-xs`, and `rounded` → `rounded-sm`;
  - `blur-sm`/`blur` shift the same way;
  - `ring` is 1px by default (it was 3px);
  - `outline-none` → `outline-hidden`.

  The class names stay valid in v4 but change meaning, so the same markup looks different.
- **Removed utilities**:
  - `bg-opacity-*`, `text-opacity-*` and `border-opacity-*` (used in 13 views); `bg-black/50` replaces them.
  - `flex-shrink-*` and `flex-grow-*` (23 views); `shrink-*` and `grow-*` replace them.
- **Changed defaults**:
  - border colour is `currentColor`; it was `gray-200`;
  - the default placeholder colour changed;
  - the palette is in `oklch` (slightly different shades);
  - buttons default to `cursor: default`.
- **Browser floor**: Safari 16.4+, Chrome 111+, Firefox 128+.

## The official upgrade tool on this repo (scratch run)

- It first refused: "Could not determine configuration file". After the config import fix and `@config` it ran.
- It rewrote **107 Blade files**:
  - 244 `shrink-0`, 183 `shadow-sm`, 183 `rounded-sm`, 73 `shadow-xs`, 73 `outline-hidden`, 52 `bg-linear-to-*`;
  - the config moved into CSS;
  - `@tailwindcss/postcss` was installed and `autoprefixer` removed.
- **Only 15 of those 107 files directly reference the operator or portal layout.** The other 92 are partials, components, or pages served by the CDN v3 script or the prebuilt v3 CSS.
- On v3, some renamed classes do not exist (`shadow-xs`, `rounded-xs`, `outline-hidden`, `bg-linear-to-*`). Others change meaning: v3 `rounded-sm` is v4 `rounded-xs`. Applying the tool's output wholesale would therefore break or shift the CDN and static-CSS pages.

## Consequence

A safe upgrade must change only the Vite-built pipeline and must keep pages on the other two sources unchanged. Each class change must either:
- be valid with the same meaning in v3 and v4 (`shrink-0`, `grow`, `bg-black/50`), or
- be avoided by restoring v3 scales and defaults in the v4 theme.

The result needs a visual check of the operator and portal pages.
