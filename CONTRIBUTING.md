# Contributing to the ANCC website

Several people build features at the same time. The setup below keeps everyone's work apart so that
merging is easy. Please read it once before you start, and discuss your plan with Proelectro first.

## The one rule: stay inside your feature folder

Every feature has its own folder on the frontend (and on the backend, when it needs an API).
The app finds these folders automatically, so **you never need to edit the router, the menus or the
dashboard to add a page**. Put everything for your feature in its folder.

| Feature | Frontend folder | Backend folder | Issue |
|---|---|---|---|
| Event highlights | `frontend/src/features/events/` | `backend/src/Features/Events/` | #4 |
| Resources | `frontend/src/features/resources/` | `backend/src/Features/Resources/` | #17 |
| About + Meet the team + sponsor | `frontend/src/features/about/` | `backend/src/Features/About/` | #7 |
| ICPC flow | `frontend/src/features/icpc/` | `backend/src/Features/Icpc/` | #6 |
| Prizes & reimbursements | `frontend/src/features/payouts/` | `backend/src/Features/Payouts/` | #5 |
| Alumni | `frontend/src/features/alumni/` | `backend/src/Features/Alumni/` | #8 |
| Audit log page | `frontend/src/features/audit/` | `backend/src/Features/Audit/` | #9 |
| General UI improvements | shared files (see below) | — | #18 |

The frontend folders already exist with a placeholder `module.tsx`. Backend folders don't exist yet:
create yours by copying `backend/src/Features/_Template/` when you need an API.
A new feature not in this table? Copy `frontend/src/features/_template/` and ask in the group first.

### Frontend: what goes in a feature folder

```
frontend/src/features/events/
  module.tsx      routes, menu links and dashboard cards for this feature (the only "wiring")
  pages/          EventsPage.tsx, EventDetailPage.tsx, ...
  components/     pieces used only by this feature
  api.ts          calls to the backend, using `api` from "@/api/client"
  hooks.ts        TanStack Query hooks (query keys start with the feature name: ["events", ...])
  types.ts        TypeScript types for this feature's API
```

`module.tsx` declares everything with `defineFeature({ ... })`: `publicRoutes`, `memberRoutes`
(login required), `adminRoutes`, `topNav`, `memberNav`, `adminNav` and `dashboardCards`.
The `order` number on menu items decides where they appear. See `src/features/registry.ts` for
all options and `src/features/_template/module.tsx` for an example.

Reuse these shared pieces; don't copy them into your folder:

- `@/components/ui`: `Button`, `Card`, `CardHeader`, `Input`, `Select`, `Field`, `Badge`, `Skeleton`, `Spinner`
- `@/layouts/Layouts`: `PageHeader`
- `@/api/client`: `api` (axios, already points at `/api/v1` with cookies), `errorMessage`
- `@/hooks/queries`: `useMe()` for the logged-in user and their roles
- `@/components/auth/guards`: `RequireRole` for pages only some roles may open
- Colours: Tailwind classes `bg-surface`, `text-muted`, `text-accent`, `border-border`, `bg-brand-slate`, `text-brand`
  (defined in `src/index.css`; they work in light and dark mode). Don't hard-code colours.

### Backend: what goes in a feature folder

```
backend/src/Features/Events/
  Module.php      class Ancc\Features\Events\Module: registers this feature's API routes
  Controller.php  request handlers
  Events.php      database queries for this feature
  seed.php        sample data for local development (optional)
```

Routes go under `/api/v1/<feature>/...` and `/api/v1/admin/<feature>/...`. Use `$app->requireUser($request)`
and `$app->requireRoles($request, ['coordinator'])` for access checks, and log every admin change with
`$app->audit`. Errors are JSON: `throw new HttpError(422, 'Short message for the user')`.
Tests go in `backend/tests/Features/EventsTest.php` (see `tests/UsersTest.php` for how API tests look).

### Database changes

- Add a new file in `backend/migrations/` named with the **date and time** you create it, plus your feature:
  `20261126_1430_events_create.sql`. Never use the next number (`002_…`): two people would pick the same one.
- **Never edit a migration that is already merged.** Add a new one instead.
- Start table names with your feature (`event_highlights`, `payout_steps`) so they don't clash.
- Follow the existing style in `001_users_roles_tokens_audit.sql`: `CHAR(36)` UUID ids, `DATETIME(6)` in UTC,
  `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`.

### Shared files: change only if you must, and say so

These are used by everyone. If you need to change one, keep the change small, mention it in your PR,
and tell the group: `frontend/src/router.tsx`, `layouts/`, `components/`, `index.css`, `api/client.ts`,
`api/types.ts`, `hooks/queries.ts`, `package.json`; `backend/src/App.php`, `Http/`, `Users.php`, `Config.php`;
`.github/`, `deploy/`, `README.md`, `CLAUDE.md`, `docs/`.
The **General UI** person works in `components/`, `layouts/`, `index.css` and the home page, and keeps
those PRs small so feature branches can pick them up quickly.

## Git workflow

1. Pick a feature in the group chat (first come, first served) and assign yourself the GitHub issue.
2. Create your branch from the latest `main`:
   ```bash
   git checkout main
   git pull
   git checkout -b feat/events
   ```
   Branch names: `feat/<feature>`, `fix/<what>`, `ui/<what>`.
3. Commit small steps with clear messages and push often: `git push -u origin feat/events`.
   Open a **draft pull request** early, so everyone can see what you're touching.
4. Bring in other people's merged work every few days, and always before asking for review:
   ```bash
   git fetch origin
   git merge origin/main
   ```
5. When it's ready, mark the PR "Ready for review" and post in the group. Proelectro reviews, merges and
   deploys to https://ancc.iitd.ac.in/new/.

Nobody can push to `main` directly; everything goes through a pull request, and CI (backend tests,
frontend lint, format check and build) must pass. Don't force-push a branch someone else is using.
Don't deploy from the CSC server yourself; only Proelectro deploys for now.

## Never commit secrets

The repository is **public**. Never commit passwords, tokens, OAuth client secrets, `backend/config.php`,
`.env` files, or personal data (phone numbers, real students' details). Sample data in `seed.php`
must be made up. If you commit a secret by mistake, tell Proelectro immediately: deleting it in a new
commit is not enough.

## Formatting and dependencies

- Frontend code is formatted by **Prettier** with the repo's settings. Run `npm run format` in `frontend/`
  before committing; CI fails if a file isn't formatted. In VS Code, install the Prettier and EditorConfig
  extensions and turn on "Format on Save".
- Line endings are handled by `.gitattributes`. Don't change them.
- **Ask before adding an npm or Composer package.** If you add one, use `npm install <package>`, never
  delete `package-lock.json`. If `package-lock.json` conflicts when merging `main`, take `main`'s version
  (`git checkout origin/main -- frontend/package-lock.json`) and run `npm install` again.

## Running it on your machine

You need **PHP 8.2+** (with the `pdo_mysql`, `curl` and `mbstring` extensions), **MySQL 8**, **Node 20+**,
and Composer only if you want to run the PHP tests.

- **Linux / macOS / WSL:** follow "Run it locally" in `README.md` (`make install`, `make migrate`, `make seed`, `make dev`).
- **Windows:** install PHP 8.2+, MySQL 8 and Node 20+ (for example Laragon, or the official PHP zip plus
  MySQL Installer), make sure `php` and `mysql` work in a terminal, then run the commands below.

**Once:** create the databases (in the MySQL shell as root):

```sql
CREATE DATABASE ancc CHARACTER SET utf8mb4;
CREATE DATABASE ancc_test CHARACTER SET utf8mb4;
CREATE USER 'ancc'@'localhost' IDENTIFIED BY 'ancc';
GRANT ALL ON ancc.* TO 'ancc'@'localhost';
GRANT ALL ON ancc_test.* TO 'ancc'@'localhost';
```

**Once:** set up and fill the database (from the repo root):

```bash
cd backend
cp config.example.php config.php        # Windows: copy config.example.php config.php
php bin/migrate.php
php bin/seed.php
```

**Every time:** run the API and the app in two terminals:

```bash
# terminal 1
cd backend
php -S 127.0.0.1:8000 -t public public/index.php

# terminal 2
cd frontend
npm install        # first time, and after package.json changes
npm run dev
```

Open http://localhost:5173 and use the dev login buttons to log in as any role. After merging `main`,
run `php bin/migrate.php` again if someone added a migration.

Checks before a PR: in `frontend/`, `npm run format`, `npm run lint` and `npm run build`. Backend tests:
`cd backend && composer install && vendor/bin/phpunit` (they use the `ancc_test` database).
