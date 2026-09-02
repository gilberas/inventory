# Build Error Pre-Fix Review

## Audit Date: 2026-06-11

---

## The Error

```
npm run build
✗ Build failed in 771ms
[plugin vite:css] resources/css/app.css
Error: [postcss] It looks like you're trying to use `tailwindcss` directly
as a PostCSS plugin. The PostCSS plugin has moved to a separate package, so
to continue using Tailwind CSS with PostCSS you'll need to install
`@tailwindcss/postcss` and update your PostCSS configuration.
```

Confirmed reproducible.

---

## Environment

| Tool | Version |
|---|---|
| node | v24.14.1 |
| npm | 11.11.0 |
| tailwindcss | 4.3.0 (v4) |
| @tailwindcss/vite | 4.3.0 (installed ✓) |
| @tailwindcss/postcss | **not installed** |
| postcss | 8.5.14 |
| vite | 8.0.13 |
| autoprefixer | 10.5.0 |

`node_modules/@tailwindcss/` contains: `node`, `oxide`, `oxide-win32-x64-msvc`, `vite`
(no `postcss` subpackage).

---

## Config Files (current state)

**`vite.config.js`** — already correct for v4:
```js
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
    plugins: [
        laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true, fonts: [...] }),
        tailwindcss(),   // ← official v4 Vite plugin, the modern approach
    ],
});
```

**`postcss.config.cjs`** — STALE v3 syntax, this is the ROOT CAUSE:
```js
module.exports = {
  plugins: {
    tailwindcss: {},      // ← v4 forbids using bare `tailwindcss` as a PostCSS plugin
    autoprefixer: {},
  },
}
```

**`tailwind.config.js`** — legacy v3 content-paths config (harmless in v4, optional):
```js
module.exports = {
    content: ["./resources/**/*.blade.php", "./resources/**/*.js", "./resources/**/*.vue"],
    theme: { extend: {} },
    plugins: [],
}
```

**`resources/css/app.css`** — MIXED v3 + v4 syntax (broken):
```css
@import "tailwindcss";       /* ← correct v4 */

@tailwind base;              /* ← leftover v3 directives, invalid in v4 */
@tailwind components;
@tailwind utilities;

@layer base { body { @apply bg-gray-100 text-gray-900; } }
@layer components { .btn-primary { @apply ...; } }
@layer utilities { .card { @apply ...; } }
```

---

## Root Cause

The project is **already configured the correct v4 way** via `@tailwindcss/vite` in
`vite.config.js`. However, a stale `postcss.config.cjs` left over from a v3-style setup
is still present. Vite auto-detects any PostCSS config file and runs it — so it tries to
load the bare `tailwindcss` package as a PostCSS plugin, which Tailwind v4 explicitly
refuses. Hence the error.

There are two valid v4 setups, and the project is straddling both:
1. **Vite plugin** (`@tailwindcss/vite`) — what `vite.config.js` already uses. ✅
2. **PostCSS plugin** (`@tailwindcss/postcss` + `postcss.config`) — what the stale `.cjs` half-implements.

Running both simultaneously is the conflict.

---

## Recommended Fix (deviates from the spec's primary suggestion — see note)

Because the Vite plugin is already wired up and is the cleaner, Laravel-current approach,
the correct fix is to **remove the stale PostCSS layer**, NOT to install
`@tailwindcss/postcss`.

| Step | Action | File |
|---|---|---|
| 1 | **Delete** the stale config | `postcss.config.cjs` |
| 2 | Remove leftover v3 `@tailwind` directives, keep `@import "tailwindcss"` | `resources/css/app.css` |
| 3 | (optional) Remove now-unused `autoprefixer` dep — v4 prefixes via Lightning CSS internally | `package.json` |

`tailwind.config.js` can stay (v4 still reads it if present); leaving it avoids churn.

> **Note on the spec:** The spec's main branch said to install `@tailwindcss/postcss` and
> rewrite `postcss.config`. That is one valid v4 setup, but it would be **redundant and
> conflicting here** because `@tailwindcss/vite` is already doing the work — having both
> would double-process the CSS. The spec also explicitly said "ensure vite.config.js does
> NOT manually import tailwindcss as a postcss plugin"; here it imports the legitimate
> `@tailwindcss/vite` plugin, which is correct and stays. So I'm following the spec's
> *goal* (make `npm run build` pass) with the approach that fits this project's actual wiring.

---

## Phase 3 — POS Layout

After the build compiles, the POS view (`resources/views/pos/terminal.blade.php`) will be
restructured into a proper 2-column layout with a compact empty-cart state, keyboard
shortcut bar, totals/payment right panel, and an Open Session modal, per spec.
