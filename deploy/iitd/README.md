# Deploying to ancc.iitd.ac.in (IITD CSC server)

The CSC server is shared Apache hosting with PHP and MySQL 8, with no sudo. The app runs like this:

| URL | Served from | What it is |
|---|---|---|
| `https://ancc.iitd.ac.in/` | `/var/www/ancc/https/html/` | The old static site (untouched) |
| `https://ancc.iitd.ac.in/new/` | `/var/www/ancc/https/html/new/` | The React app (built files + `.htaccess`) |
| `https://ancc.iitd.ac.in/new/api/...` | `html/new/api.php` | Tiny entry file that loads the PHP app |
| *(not on the web)* | `/var/www/ancc/https/app/` | Backend code, `config.php` (DB password) |

API calls take a few milliseconds, because Apache runs PHP directly.

While IITD login (OAuth) isn't approved yet, dev login is on: anyone who can reach `/new/` (IITD network
only) can log in as any user. Keep only test data there until IITD login works.

## How a change reaches the server

1. You push to `main`. CI runs the tests, then builds the frontend for `/new/` and commits the repo plus
   the built files (`frontend/dist/`) to the **`deploy` branch**. This takes 2–3 minutes; watch the Actions tab.
2. On the server, `deploy/iitd/deploy.sh update` pulls the `deploy` branch and installs it.

The server only needs `git`, PHP and rsync. Its Node (18) is too old for the Tailwind 4 build, and that's
why CI builds instead. The server reaches GitHub through the IITD proxy (`https_proxy` is already set there).
`deploy.sh` notices that it's running on the server and works without SSH.

## First time, on the server

**0. The web root must be writable by the `ancc` group.** `check` tests this. If it says
`html is not writable by you`, the editor who owns `html/` must run the one-line fix that `check` prints
(`chgrp ancc ... && chmod 2775 ...`). Only a folder's owner can change it.

**1. Make a read-only GitHub token** (the repo is private). On GitHub: Settings → Developer settings →
Personal access tokens → Fine-grained tokens → Generate. Repository access: *Only select repositories* →
`ancc-dynamic-website`. Permissions: **Contents: Read-only**, nothing else. Pick an expiry (e.g. 1 year).

**2. Clone the `deploy` branch** (log in with `ssh <kerberos-id>@ancc.iitd.ac.in` first):

```bash
umask 002
mkdir -p /var/www/ancc/https/app && cd /var/www/ancc/https/app
git clone --depth 1 --branch deploy \
  -c credential.helper="store --file=/var/www/ancc/https/app/.git-credentials-$USER" \
  https://github.com/Proelectro/ancc-dynamic-website.git src-$USER
cd src-$USER
```

Username: your GitHub username. Password: **paste the token** (nothing shows while you paste). Git saves it
in `app/.git-credentials-<you>`, readable only by you, so later updates don't ask. `app/` is not on the web.

**3. Check, set up, deploy:**

```bash
deploy/iitd/deploy.sh check    # tests PHP, .htaccess rewrites and folder permissions
deploy/iitd/deploy.sh setup    # creates the server config
deploy/iitd/deploy.sh          # installs the app and sets up the database
```

- **`setup`** writes `/var/www/ancc/https/app/config.php`. It reads the MySQL credentials from CSC's
  `/var/www/ancc/README` and generates a random JWT secret. The password never goes into git.
- **`deploy`** copies the backend to `app/backend/` and the built frontend to `html/new/`, fixes group
  permissions, applies new database migrations, and runs a health check.

Then open **https://ancc.iitd.ac.in/new/** and log in with the dev login buttons.

## Updating the site

After CI has finished on GitHub (green tick on the latest commit):

```bash
ssh <kerberos-id>@ancc.iitd.ac.in
cd /var/www/ancc/https/app/src-$USER && deploy/iitd/deploy.sh update
```

Other editors make their own clone (`src-<their id>`) with their own token, the same way.

Other commands (run from the clone):

| Command | What it does |
|---|---|
| `deploy/iitd/deploy.sh logs` | Show the end of Apache's error log (start here when something breaks) |
| `deploy/iitd/deploy.sh check` | Re-test the server setup |
| `deploy/iitd/deploy.sh reset-db` | Delete all tables and recreate them (asks you to type RESET) |

## Deploying from your own machine instead

The same script works from Linux or macOS on the IITD network/VPN, over SSH, building the frontend locally
(needs Node 20+, `rsync`, `curl`, `openssl`). Add the server to `~/.ssh/config` so you type your password
once (the server has no home folders, so SSH keys don't work there):

```
Host ancc
    HostName ancc.iitd.ac.in
    User <kerberos-id>
    ControlMaster auto
    ControlPath ~/.ssh/cm-%C
    ControlPersist 8h
    ServerAliveInterval 60
```

Check the host key the first time: `SHA256:u8O0h+vvEVrQaWiiBp94MdH5razmBUcnsmDamPsXDCc`. Then, from the
repo root on `main`: `deploy/iitd/deploy.sh check`, `setup`, and `deploy/iitd/deploy.sh` for each update.

> **If you deployed the earlier Python version:** `deploy` stops at the migrations and says so. Run
> `deploy/iitd/deploy.sh reset-db` once (it only deletes preview data), then deploy again.

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| `html is not writable by you` | See step 0: the owner of `html/` runs the `chgrp`/`chmod` line that `check` prints |
| `git clone`/`update` asks for a password again or says `Authentication failed` | The token expired or lacks *Contents: Read-only*. Make a new one; delete `app/.git-credentials-<you>` and run `update` again |
| `update` says it isn't on the deploy branch | That clone is of `main`. Clone again with `--branch deploy` (step 2) |
| `check` gets HTTP 000 from the site | The server can't reach itself directly. Retry with `ANCC_CURL_OPTS="--proxy http://henna.iitd.ac.in:3128" deploy/iitd/deploy.sh check` |
| `check` says rewrites don't work | Run `deploy/iitd/deploy.sh logs`. "not allowed here" means CSC's Apache config doesn't allow that `.htaccess` directive. Ask CSC to allow `AllowOverride FileInfo AuthConfig` for our site |
| API calls return 500 | `deploy/iitd/deploy.sh logs` shows the PHP error (lines start with `ANCC API error`) |
| "Server misconfigured" | `config.php` is missing or invalid. Run `setup`, or check the error log |
| `rsync: Permission denied` on some file | Another editor's file without group write. They should run `deploy/iitd/deploy.sh` once (it fixes permissions on their own files) |

## Going public (later)

When the IITD OAuth administrator (Mr. Gopal Krishan, see https://oauth2.iitd.ac.in/client_config.php) has
registered our app with redirect URI `https://ancc.iitd.ac.in/new/api/v1/auth/callback`:

1. Edit `/var/www/ancc/https/app/config.php` on the server: set `'ENV' => 'production'`, `'AUTH_MODE' => 'iitd'`,
   `'IITD_OAUTH_CLIENT_ID' => '...'` and `'IITD_OAUTH_CLIENT_SECRET' => '...'`.
2. Deploy: `deploy/iitd/deploy.sh update` (from the server clone). Dev login is now off.
3. Make the first admin: `ssh ancc`, then
   `cd /var/www/ancc/https/app/backend && ANCC_CONFIG=../config.php php bin/create-superadmin.php <kerberos-id>`.
4. When all phases are built, the app can move from `/new/` to the domain root (`ANCC_SUBDIR` and
   `BASE_PATH`); decide then what happens to the old static site.

## Settings the script understands

| Variable | Default | Meaning |
|---|---|---|
| `ANCC_HOST` | `local` on the server, else `ancc` | SSH host (alias from `~/.ssh/config`), or `local` |
| `ANCC_URL` | `https://ancc.iitd.ac.in` | Public URL |
| `ANCC_SUBDIR` | `new` | App path under the URL |
| `ANCC_REMOTE_PHP` | `php` | PHP command on the server |
| `ANCC_CURL_OPTS` | *(empty)* | Extra curl options for the checks (used for testing) |
