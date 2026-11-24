# ANCC Website

Website for the **Algorithm and Coding Club (ANCC), IIT Delhi**: events, ECA/CAIC records, prizes and reimbursements, and the ICPC team pipeline.

- `backend/`: PHP 8.2+ JSON API (no framework, no runtime dependencies) on MySQL 8
- `frontend/`: React + TypeScript + Vite + Tailwind + TanStack Query
- `deploy/iitd/`: one script to publish to `ancc.iitd.ac.in`
- The full design is in [`docs/plan.md`](docs/plan.md); context for AI-assisted work is in [`CLAUDE.md`](CLAUDE.md).

Why PHP: the IITD CSC server is shared Apache hosting with PHP built in. A PHP API answers in a few
milliseconds there, while a Python app could only run as a CGI script (about 0.7 s per request).

## Status

| Phase | What | State |
|---|---|---|
| 0 | Setup: repo, Makefile, CI, layouts | ✅ done |
| 1 | Auth (dev login + IITD OAuth), profile + onboarding, dashboard shell, admin users & roles | ✅ done |
| — | Hosting on ancc.iitd.ac.in (preview at `/new/`) | ✅ ready |
| 2 | Events: CRUD, form builder, registration | next |
| 3 | ECA records, CAIC scores | |
| 4 | Prizes & reimbursements | |
| 5 | ICPC | |
| 6 | Results, Drive photos, About page | |
| 7 | Notifications, audit log UI | |

## Run it locally

You need **PHP 8.2+** (with `pdo_mysql`, `curl`, `mbstring`), **Composer**, **Node 20+** and **MySQL 8**.

```bash
# Ubuntu / WSL: tools (once)
sudo apt install -y php-cli php-mysql php-curl php-mbstring php-xml composer mysql-server

# Database (once)
sudo mysql -e "CREATE DATABASE ancc CHARACTER SET utf8mb4; CREATE DATABASE ancc_test CHARACTER SET utf8mb4;
  CREATE USER 'ancc'@'localhost' IDENTIFIED BY 'ancc';
  GRANT ALL ON ancc.* TO 'ancc'@'localhost'; GRANT ALL ON ancc_test.* TO 'ancc'@'localhost';"

# Install, migrate, seed (once)
make install      # composer + npm, creates backend/config.php
make migrate
make seed

# Run (API on :8000, app on :5173)
make dev
```

Open http://localhost:5173 and use the **dev login** quick buttons (Member, New student, Coordinator,
ICPC admin, Finance, Super admin). Settings live in `backend/config.php` (copied from `config.example.php`).

## Useful commands

| Command | What it does |
|---|---|
| `make dev` | PHP built-in server for the API + Vite dev server (proxies `/api` to it) |
| `make test` | Backend tests (PHPUnit, uses the `ancc_test` database) |
| `make lint` | PHP syntax check + eslint + TypeScript type-check |
| `make migrate` | Apply new files from `backend/migrations/` |
| `make deploy` | Publish to the IITD server (see below) |

## Backend layout

```
backend/
├── public/index.php        # local entry point (php -S); on the server html/new/api.php does this
├── src/
│   ├── App.php             # routes + error handling
│   ├── Config.php          # settings (config.php, overridable by ANCC_* env vars)
│   ├── Db.php, Migrator.php
│   ├── Users.php, Audit.php
│   ├── Auth/               # Session (JWT access + rotating refresh cookies), Jwt, IitdOAuth
│   ├── Controllers/        # Auth, User, Dashboard, AdminUser, Health
│   ├── Http/               # Request, Response, Router, HttpError
│   └── Support/            # Kerberos parsing, Roles, Lists (hostels), Validate, Uuid
├── migrations/             # 001_*.sql, 002_*.sql ... applied in order
├── bin/                    # migrate.php, seed.php, create-superadmin.php, health.php
└── tests/                  # PHPUnit; calls the app in-process
```

- Add a schema change: create the next `migrations/NNN_description.sql`, then `make migrate`.
- Add an endpoint: a method on a controller + one line in `App::routes()`, a test, and the response type in `frontend/src/api/types.ts`.

## Deploying to ancc.iitd.ac.in

The app runs on the IITD CSC server as a password-protected preview at **https://ancc.iitd.ac.in/new/**.
CI builds every push to `main` into the `deploy` branch; the server pulls that branch. On the server, from
its clone (`/var/www/ancc/https/app/src-<kerberos>`):

```bash
deploy/iitd/deploy.sh check    # once
deploy/iitd/deploy.sh setup    # once
deploy/iitd/deploy.sh update   # every update: pull the latest build and deploy it
```

Full guide: [`deploy/iitd/README.md`](deploy/iitd/README.md).

## Auth modes

- `AUTH_MODE=dev` (local, preview): `POST /api/v1/auth/dev-login` lets you log in as any kerberos ID with any roles.
- `AUTH_MODE=iitd` (production): "Login with IITD" uses IITD OAuth. The dev-login route is **not registered**, and the app **refuses to start** with `ENV=production` and `AUTH_MODE=dev`.
- The IITD OAuth endpoint URLs and response field names are in `backend/src/Config.php` and `backend/src/Auth/IitdOAuth.php`. **Confirm them with CSC** when registering the OAuth client.
- First super admin in production: log in once with IITD, then on the server `php bin/create-superadmin.php <kerberos_id>`.

## Contributing

- Branch from `main`, open a PR. CI must pass. **Only `proelectro` can merge to `main`** (branch protection + `CODEOWNERS`).
- Controllers stay thin; every admin write goes to the audit log.
