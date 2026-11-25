# Backend features

Each feature lives in its own folder here and plugs itself in, so two people working on different
features never edit the same file.

```
src/Features/Events/
  Module.php       class Ancc\Features\Events\Module implements Ancc\FeatureModule (routes)
  Controller.php   optional: request handlers
  Events.php       optional: database queries for this feature
  seed.php         optional: sample data for `php bin/seed.php`
```

Start by copying `_Template/` (folders starting with `_` are ignored). Database tables go in
`backend/migrations/` with a **timestamp** name, e.g. `20261126_1430_events_create.sql`, so two
branches never pick the same number. Tests go in `backend/tests/Features/EventsTest.php`.
See CONTRIBUTING.md at the repo root for the full workflow.
