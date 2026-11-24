# Deploying to ancc.iitd.ac.in (IITD CSC server)

The CSC server is shared Apache hosting with PHP and MySQL 8, with no sudo. The app runs like this:

| URL | Served from | What it is |
|---|---|---|
| `https://ancc.iitd.ac.in/` | `/var/www/ancc/https/html/` | The old static site (untouched) |
| `https://ancc.iitd.ac.in/new/` | `/var/www/ancc/https/html/new/` | The React app (built files + `.htaccess`) |
| `https://ancc.iitd.ac.in/new/api/...` | `html/new/api.php` | Tiny entry file that loads the PHP app |
| *(not on the web)* | `/var/www/ancc/https/app/` | Backend code, `config.php` (DB password), preview password |

API calls take a few milliseconds, because Apache runs PHP directly.

While IITD login (OAuth) isn't approved yet, the app runs in **preview mode**: dev login is on, so the
whole of `/new/` is behind a **preview password** that only editors know.

Everything is done by one script, run **from your own machine** (Linux, WSL or macOS) while on the
**IITD network or VPN**. Nothing has to be installed on the server.

## One-time setup on your machine

1. Tools: `ssh`, `rsync`, `curl`, `openssl` (usually already there), plus **Node 20+** to build the frontend.
   ```bash
   sudo apt install -y rsync curl openssl     # Ubuntu / WSL
   ```
2. Add the server to `~/.ssh/config` (replace `<kerberos-id>`). The `Control*` lines keep one login
   open for 8 hours, so you type your password once a day (the server has no home folders, so SSH keys
   don't work there):
   ```
   Host ancc
       HostName ancc.iitd.ac.in
       User <kerberos-id>
       ControlMaster auto
       ControlPath ~/.ssh/cm-%C
       ControlPersist 8h
       ServerAliveInterval 60
   ```
   The first time you `ssh ancc`, check the host key fingerprint against the one in the server notes:
   `SHA256:u8O0h+vvEVrQaWiiBp94MdH5razmBUcnsmDamPsXDCc`. If it's different, stop.

## First deployment

Run these from the repository root:

```bash
deploy/iitd/deploy.sh check    # tests login, PHP, .htaccess rewrites and password protection
deploy/iitd/deploy.sh setup    # creates the server config and asks you for a preview password
deploy/iitd/deploy.sh          # builds and uploads the app, sets up the database
```

You'll type your Kerberos password once per command.

- **`setup`** writes `/var/www/ancc/https/app/config.php`. It reads the MySQL credentials from CSC's
  `/var/www/ancc/README` on the server and generates a random JWT secret. The password never leaves the
  server and never goes into git. It also asks for a **preview username and password**; share these
  with editors only.
- **`deploy`** builds the frontend for `/new/`, uploads only what changed, fixes group permissions (so
  other editors can deploy too), applies new database migrations, and runs a health check.

Then open **https://ancc.iitd.ac.in/new/**, enter the preview username/password, and log in with the
dev login buttons.

> **If you deployed the earlier Python version:** `deploy` will stop at the migrations and say so. Run
> `deploy/iitd/deploy.sh reset-db` once (it only deletes preview data), then `deploy/iitd/deploy.sh` again.
> The script removes the old CGI files by itself.

## Updating the site

```bash
git pull
deploy/iitd/deploy.sh
```

Other commands:

| Command | What it does |
|---|---|
| `deploy/iitd/deploy.sh password` | Change the preview password |
| `deploy/iitd/deploy.sh logs` | Show the end of Apache's error log (start here when something breaks) |
| `deploy/iitd/deploy.sh check` | Re-test the server setup |
| `deploy/iitd/deploy.sh reset-db` | Delete all tables and recreate them (asks you to type RESET) |

## Troubleshooting

| Symptom | Likely cause / fix |
|---|---|
| `Can't log in to ancc` | Not on the IITD network/VPN, or your ID isn't in the CSC editors list |
| `check` says rewrites or password protection don't work | Run `deploy/iitd/deploy.sh logs`. "not allowed here" means CSC's Apache config doesn't allow that `.htaccess` directive. Ask CSC to allow `AllowOverride FileInfo AuthConfig` for our site |
| API calls return 500 | `deploy/iitd/deploy.sh logs` shows the PHP error (lines start with `ANCC API error`) |
| "Server misconfigured" | `config.php` is missing or invalid. Run `setup`, or check the error log |
| `rsync: Permission denied` on some file | Another editor's file without group write. They should run `deploy/iitd/deploy.sh` once (it fixes permissions on their own files) |

## Going public (later)

When CSC approves IITD OAuth:

1. Edit `/var/www/ancc/https/app/config.php` on the server: set `'ENV' => 'production'`, `'AUTH_MODE' => 'iitd'`
   and add the three `IITD_OAUTH_*` values (the comment at the top of the file lists them).
2. Deploy without the preview password: `ANCC_PREVIEW=0 deploy/iitd/deploy.sh`.
3. Make the first admin: `ssh ancc`, then
   `cd /var/www/ancc/https/app/backend && ANCC_CONFIG=../config.php php bin/create-superadmin.php <kerberos-id>`.
4. When all phases are built, the app can move from `/new/` to the domain root (`ANCC_SUBDIR` and
   `BASE_PATH`); decide then what happens to the old static site.

## Settings the script understands

| Variable | Default | Meaning |
|---|---|---|
| `ANCC_HOST` | `ancc` | SSH host (alias from `~/.ssh/config`) |
| `ANCC_URL` | `https://ancc.iitd.ac.in` | Public URL |
| `ANCC_SUBDIR` | `new` | App path under the URL |
| `ANCC_PREVIEW` | `1` | `1` = password-protect the app |
| `ANCC_REMOTE_PHP` | `php` | PHP command on the server |
| `ANCC_CURL_OPTS` | *(empty)* | Extra curl options for the checks (used for testing) |
