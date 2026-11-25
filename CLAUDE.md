# CLAUDE.md: context for working on the ANCC website

Read this first. It sums up everything decided so far (Nov 2026) so a new session can continue.
The full product spec is `docs/plan.md`; current build status is the table in `README.md`.

## What this is

Website for **ANCC (Algorithm and Coding Club), IIT Delhi**: a public face (events, results, team,
sponsor Jump Trading), a member portal (profile, ECA records, CAIC score, prizes and reimbursements,
ICPC team), and admin tooling. Maintainer: **Proelectro** (GitHub), an IITD student (kerberos `me1230384`).

## Stack and why

- **Backend: PHP 8.2+ JSON API, no framework, no runtime Composer deps, MySQL 8.** It was first built in
  FastAPI, then rewritten because the IITD server is shared Apache+PHP hosting: Python could only run as
  CGI (~0.7 s/request) versus ~7 ms for PHP. The models, endpoints and features in `docs/plan.md` still apply;
  the table at the top of that file maps the planned Python pieces to PHP ones.
- **Frontend:** React 19 + TypeScript + Vite 6 + Tailwind v4 + TanStack Query + React Hook Form/Zod + sonner + lucide.
  API types are hand-written in `frontend/src/api/types.ts` (keep them in sync with `Users::out()` and the controllers).
- **Brand (25 Nov):** ANCC palette from the logo: slate `#373D4B`, orange `#DF6C36` (`--brand`), white; deeper orange `#C2541F`
  (`--accent`) for text/buttons on light backgrounds (AA contrast). Tokens in `frontend/src/index.css`; logo as SVG in
  `components/brand/AnccMark.tsx`; original files in `frontend/public/brand/`.
- **No Docker.** Local dev uses PHP's built-in server plus Vite (`make dev`).

## Layout

```
backend/   public/index.php (local entry) · src/{App,Config,Db,Migrator,Users,Audit}.php
           src/Auth (Session: JWT access + rotating refresh cookies, Jwt, IitdOAuth)
           src/Controllers (Auth, User, Dashboard, AdminUser, Health) · src/Http (Request/Response/Router/HttpError)
           src/Support (Kerberos, Roles, Lists=hostels, Validate, Uuid) · migrations/NNN_*.sql · bin/*.php · tests/ (PHPUnit)
frontend/  src/{api,hooks/queries.ts,components,layouts,pages,lib} · router.tsx (basename from VITE_BASE)
deploy/iitd/  deploy.sh (check|setup|deploy|update|logs|reset-db) + templates + README.md
docs/plan.md  full spec · .github/ CI + CODEOWNERS
```

Conventions:
- **Adding an endpoint:** a controller method, one line in `App::routes()`, a PHPUnit test, and the TS type.
- **Schema change:** add the next `backend/migrations/NNN_name.sql` (forward-only), then `make migrate`.
- DB times are **UTC** (`DATETIME(6)`; the session sets `time_zone = '+00:00'`); JSON shows ISO 8601. IDs are UUID v4 `CHAR(36)`.
- **Identifier is `kerberos_id`** (for example `cs1230007`); the user dropped entry numbers. Department and year are derived in `Support/Kerberos.php`.
- Roles: `member` (implicit), `coordinator`, `icpc_admin`, `finance`, `super_admin` (passes every check). There is no coach role.
- Every admin write goes to `audit_logs`. Error JSON is `{"detail": "msg"}` or `{"detail": [{"loc": [...], "msg": "..."}]}` (422).
- Validation messages are user-facing; keep them short and plain.

## Run, test

```bash
make install && make migrate && make seed && make dev   # needs PHP 8.2+, Composer, Node 20+, MySQL 8
make test     # PHPUnit on the ancc_test DB (tests/bootstrap.php resets the schema)
make lint     # php -l + eslint + tsc
```

Local DB: databases `ancc` and `ancc_test`, user `ancc`/`ancc`. The config is `backend/config.php` (git-ignored, copied
from `config.example.php`); `ANCC_*` env vars override it. In the Claude sandbox, Packagist was blocked:
install PHPUnit with `apt-get install phpunit` and run `phpunit` from `backend/`.

## The IITD server (from the club's server-access notes)

- `ancc.iitd.ac.in` → 10.10.211.213 (`vweb213`). **IITD network or VPN only**; Claude's sandbox and GitHub Actions can't reach it.
- Shared CSC hosting (~125 sites), Ubuntu 22.04, Apache + PHP (Apache 8.4, CLI 8.5), MySQL 8.0, Node 18, git. **No sudo, no home dirs, no long-running processes.**
- Login: `ssh <kerberos>@ancc.iitd.ac.in` with the Kerberos password. Editors are in Unix group `ancc`. No home folder means
  no `authorized_keys`; use SSH `ControlPersist` from Linux (Windows OpenSSH can't). The Kerberos/GSSAPI login option is still to be checked.
- `/var/www/ancc/` (root:ancc, 750) holds `README` (CSC's notes with the MySQL credentials; never copy them anywhere), `apache-logs/{access,error}.log`,
  and `https/` (group-writable, not web-served) containing `html/` (web root) and `cgi-bin/`.
- The **old static site** (from GitHub Pages) lives in `https/html/` (index.html, team*.html, etc.). Don't touch it.
- New app: `https://ancc.iitd.ac.in/new/` → `https/html/new/` (React build + `.htaccess` + `api.php`).
  Code goes in `https/app/backend/`, config in `https/app/config.php` (written by `deploy.sh setup` from the README; mode 640).
  ENV=staging with dev login on. **No preview password**: the user had the feature removed entirely (25 Nov), since there's
  no real data yet. Anyone on the IITD network can use dev login as any user, so keep only test data until IITD OAuth is live.
- **Deploys run on the server itself.** CI (`.github/workflows/ci.yml`, job `deploy-branch`) builds every push to `main`
  into the **`deploy` branch** (repo + `frontend/dist/` built for `/new/`), because the server's Node 18 can't build
  Tailwind 4. Each editor clones that branch to `https/app/src-<kerberos>` with a read-only fine-grained token (stored in
  `https/app/.git-credentials-<kerberos>`, mode 600) and runs `deploy/iitd/deploy.sh update`. On the server `deploy.sh`
  auto-detects `/var/www/ancc` and runs locally (no SSH); `--noproxy` is used to reach the site itself. Tested on a replica.
- The server has `https_proxy=http://henna.iitd.ac.in:3128` set, and reaches GitHub through it. rsync, openssl, htpasswd exist.
- `https/html/` is owned by editor **cs1240924**; on 24 Nov they made it `ancc` group, mode 2775, so other editors can write in it.
  If an editor's `check` ever says html isn't writable, the owner runs `chgrp ancc .../html && chmod 2775 .../html`.
- **Verified on the real box (24 Nov):** PHP 8.4.27 under Apache, `.htaccess` RewriteRule (and Basic Auth) work,
  MySQL socket at `/var/run/mysqld/mysqld.sock`, PHP CLI 8.5.12.
- **Live since 24 Nov 2026:** `https://ancc.iitd.ac.in/new/` (open, no password; see above).
  Deployed from `/var/www/ancc/https/app/src-me1230384`. To update: push to `main`, wait for CI, then `deploy/iitd/deploy.sh update` there.
- If the earlier Python version was ever deployed there, `deploy.sh reset-db` clears the old tables (preview data only).
- **IITD OAuth** (documented at https://oauth2.iitd.ac.in/client_config.php): email **webgroup@cc.iitd.ac.in** (Mr. Gopal Krishan /
  Dr. Manish Agarwal). **Requested 25 Nov 2026** (by Pritesh) with Client Name "ANCC website", Server URI `https://ancc.iitd.ac.in`
  and Redirect URI **`https://ancc.iitd.ac.in/api/v1/auth/callback`** (the root address, not `/new/`). Until the app moves to the
  root, `deploy.sh` writes `html/api/.htaccess` that 302-forwards only that path to `/new/api/v1/auth/callback` (the state
  cookie is scoped to `/new`, so the callback must land there). Remove `html/api/` when launching at the root. They return
  `client_id` + `client_secret`. Flow: `authorize.php` → callback `?code` → `token.php` (POST client_id,
  client_secret, grant_type, code) → `resource.php` (POST access_token) → `user_id` (= kerberos), `mail`, `name`,
  `uniqueiitdid`, `category`, `department`. `IitdOAuth.php` follows this; `redirect_uri` is not sent unless configured.
  **Open question:** one docs page says `oauth2.iitd.ac.in`, another `oauth.iitd.ac.in`; the code defaults to `oauth2`. Asked in the
  request email; if it's `oauth`, set the three `IITD_OAUTH_*_URL` values in the server's config.php (no code change).
- Going public later: in config.php set `ENV=production`, `AUTH_MODE=iitd`, `IITD_OAUTH_CLIENT_ID/SECRET`, deploy, then run
  `bin/create-superadmin.php`. The registered redirect URI already points at the root, so moving there needs no change at IITD.
- Shared hosting has **no background jobs**: do "scheduled" work lazily on request, via admin buttons, or with cron if CSC allows it.

## Git workflow (current)

- Repo: **github.com/Proelectro/ancc-dynamic-website**. For now **push directly to `main`** (the user's choice for the
  first few commits). Only "block force pushes" and "restrict deletions" are planned on `main` meanwhile.
- Later: a ruleset requiring a PR plus Code Owner review. Caveat: Claude's GitHub identity is **Proelectro**, the only code
  owner, and nobody can approve their own PR. So add a second code owner, or give proelectro "pull requests only" bypass.
- Commit messages end with the attribution lines the session provides (Co-Authored-By / Claude-Session).
- Never commit `backend/config.php`, the server's config, or anything from `/var/www/ancc/README`.

## Product decisions (user's comments on the plan; details in docs/plan.md)

- **Scope change (24 Nov 2026):** event **registration** and **ECA/CAIC** already exist elsewhere, so we don't build them
  (plan §1.3 registration, §1.4 ECA/CAIC, §1.7 form builder are dropped; GitHub issue #3 removes their placeholders).
  We keep an **event highlights** page (write-up, results, Drive photos, link to external registration), **prizes and
  reimbursements** (prizes now added by finance, not from our event results), ICPC, About, and a new **alumni outreach** page.
  The ECA/CAIC bullets below are kept only as history.

- **Dashboard order:** Profile on top, then Prizes & reimbursements, ICPC (ECA, CAIC and My events removed 25 Nov).
- **ECA** = a list of achievements (bullet records like "Secured 1st in Code Decode"), **no numbers**.
- **CAIC** = numeric score toward the CAIC Trophy (inter-hostel, year-long). One entry per student per event, with a hostel snapshot.
  The points table is per event (values come from the CAIC team). Fix mistakes by revoking with a reason, never by deleting.
- **Prizes and reimbursements share one "payout" flow:** (0 submitted, reimbursements only) → 1 bank details required (mail
  passbook or cancelled cheque to ancclub@iitd.ac.in; never stored on the site) → 2 awaiting professor approval → 3 accounts
  processing (~21 days) → 4 done. A `bank_details_on_file` flag skips step 1.
- **ICPC:** the captain creates the team and invites 2 others by kerberos ID; anyone can leave until the team deadline, after which rosters freeze.
  Exactly 2 regional sites, **hard-coded** for now. icpc.global email can be any email (it must match the one on icpc.global).
  No DOB is collected, so the eligibility check uses the studies start year and shows "needs check" otherwise. There's an issues
  loop ("email not found", etc.) and a registration worksheet with copy buttons for the ICPC admin. No coach role.
- **Event photos:** from the ANCC Google Drive folder link (synced via the Drive API), not uploaded to the server.
- **Merging:** only proelectro may merge to `main` (CODEOWNERS `* @proelectro`) once PR rules are on.

## Status and next steps

- **Done:** Phase 0 (setup, CI, layouts) and Phase 1 (dev login + IITD OAuth skeleton, JWT/refresh cookies, profile and
  onboarding, dashboard shell, admin users list/detail with roles and deactivate, audit log). 26 PHPUnit tests. Deploy kit tested on
  a local replica of the server (Apache mod_php, .htaccess, MySQL 8).
- **Pushed to GitHub and deployed** to `https://ancc.iitd.ac.in/new/` on 24 Nov 2026.
  **Waiting on:** IITD's reply to the OAuth request (sent 25 Nov) with client_id/secret and oauth vs oauth2.
- **Roadmap = GitHub issue #15** (checklist of issues #1–#14): IITD OAuth, go public, scope cleanup, event highlights,
  prizes and reimbursements, ICPC, About + sponsor, alumni outreach, polish, launch at root, branch protection, DB backups,
  editor deploy access, the old GitHub Pages site. Check the issues for current status.

## Working with the user

- Keep replies concise; render math in LaTeX if any comes up.
- The user is on Windows and setting up an **Ubuntu dual boot** (WSL failed with `E_UNEXPECTED`/`REGDB_E_CLASSNOTREG`).
  So deploys run on the server over SSH (Windows OpenSSH is enough), not from the laptop.
- Ask before big direction changes. **Don't run tests, replica checks or screenshots after every change; only when the user asks** (said 25 Nov).
