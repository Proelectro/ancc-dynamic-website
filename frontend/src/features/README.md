# Frontend features

One folder per feature. Each has a `module.tsx` (default export: `defineFeature({ ... })`) that declares
its routes, menu links and dashboard cards. `registry.ts` finds every `*/module.tsx` automatically, so a
feature never needs changes in `router.tsx`, the menus or the dashboard. Folders starting with `_` are
ignored; copy `_template/` to start a new one.

Keep everything for a feature in its folder (`pages/`, `components/`, `api.ts`, `hooks.ts`, `types.ts`).
See CONTRIBUTING.md at the repo root for the full workflow.
