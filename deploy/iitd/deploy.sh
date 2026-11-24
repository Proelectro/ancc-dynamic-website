#!/usr/bin/env bash
# Deploy the ANCC app to the IITD CSC server (shared Apache + PHP hosting, no sudo).
#
# Run from your own machine (Linux, WSL or macOS) while on the IITD network or VPN:
#
#   deploy/iitd/deploy.sh check      # test SSH, PHP and .htaccess support on the server
#   deploy/iitd/deploy.sh setup      # first time only: create the server config + preview password
#   deploy/iitd/deploy.sh            # build and upload the app (same as "deploy")
#   deploy/iitd/deploy.sh password   # change the preview password
#   deploy/iitd/deploy.sh logs       # show the last lines of Apache's error log
#   deploy/iitd/deploy.sh reset-db   # drop ALL tables and recreate them (asks first)
#
# Layout on the server (see deploy/iitd/README.md):
#   https://ancc.iitd.ac.in/new/        -> /var/www/ancc/https/html/new/  (React build, .htaccess, api.php)
#   https://ancc.iitd.ac.in/new/api/... -> html/new/api.php -> app code in /var/www/ancc/https/app/
#   /var/www/ancc/https/app/            -> backend code, config.php, .htpasswd (not reachable from the web)
set -euo pipefail
set -f # no filename globbing; keeps options like "--noproxy *" intact

HOST="${ANCC_HOST:-ancc}"                         # ssh target, e.g. an alias from ~/.ssh/config
URL="${ANCC_URL:-https://ancc.iitd.ac.in}"        # public URL of the site
SUBDIR="${ANCC_SUBDIR:-new}"                      # app lives at $URL/$SUBDIR/
REMOTE_PHP="${ANCC_REMOTE_PHP:-php}"              # PHP command-line binary on the server
PREVIEW="${ANCC_PREVIEW:-1}"                      # 1 = password-protect the app (dev login is on)
SITE=/var/www/ancc/https
APP="$SITE/app"
BASE="/$SUBDIR"

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
KIT="$ROOT/deploy/iitd"
BUILD="$ROOT/build/iitd"
read -r -a CURL_EXTRA <<<"${ANCC_CURL_OPTS:-}"

# One SSH connection for the whole run, so you type your Kerberos password at most once.
# If your ~/.ssh/config already shares connections for this host (ControlPath + ControlPersist),
# reuse that, so an already-open connection means no password at all.
USER_CTL="$(ssh -G "$HOST" 2>/dev/null | awk '$1 == "controlpath" { print $2 }')"
if [ -n "$USER_CTL" ] && [ "$USER_CTL" != "none" ]; then
  SOCK_DIR=""
  SSH_OPTS=(-o ControlMaster=auto)
else
  # /tmp, not $TMPDIR: macOS temp paths are too long for SSH control sockets.
  SOCK_DIR="$(mktemp -d /tmp/ancc-ssh.XXXXXX)"
  SSH_OPTS=(-o ControlMaster=auto -o "ControlPath=$SOCK_DIR/%C" -o ControlPersist=120)
fi
cleanup() {
  if [ -n "$SOCK_DIR" ]; then
    ssh "${SSH_OPTS[@]}" -O exit "$HOST" 2>/dev/null || true
    rm -rf "$SOCK_DIR"
  fi
}
trap cleanup EXIT

say() { printf '\033[1;32m==>\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m!!\033[0m %s\n' "$*" >&2; }
die() { printf '\033[1;31mxx\033[0m %s\n' "$*" >&2; exit 1; }
need() { command -v "$1" >/dev/null || die "'$1' is not installed on this machine. $2"; }

# -n: don't let ssh swallow keyboard/stdin input meant for later prompts.
remote() { ssh -n "${SSH_OPTS[@]}" "$HOST" "$@"; }
remote_stdin() { ssh "${SSH_OPTS[@]}" "$HOST" "$@"; }
# Run a bash script (from stdin) on the server with positional arguments.
remote_script() { remote_stdin "bash -s -- $(printf '%q ' "$@")"; }
push() { rsync -rlt -O -e "ssh ${SSH_OPTS[*]}" "$@"; }
fetch() { curl -sS ${CURL_EXTRA[@]+"${CURL_EXTRA[@]}"} "$@"; }
http_code() { fetch -o /dev/null -w '%{http_code}' "$@" || echo "000"; }

connect() {
  need ssh "Install OpenSSH."
  need rsync "Install it with: sudo apt install rsync"
  need curl "Install it with: sudo apt install curl"
  say "Connecting to $HOST (enter your Kerberos password if asked)"
  remote true || die "Can't log in to $HOST. Are you on the IITD network/VPN, and is your ~/.ssh/config set up?"
}

# Writes config.php on the server from CSC's README. Runs there, under the server's PHP.
SETUP_PHP="$(base64 <<'PHP' | tr -d '\n'
<?php
[, $path, $url, $base] = $argv;
$readme = (string) file_get_contents('/var/www/ancc/README');
$field = static function (string $key) use ($readme): ?string {
    return preg_match('/^' . $key . '\s*=\s*(.*?)\s*$/m', $readme, $m) ? $m[1] : null;
};
$password = $field('PASSWORD');
if ($password === null) {
    fwrite(STDERR, "No PASSWORD= line in /var/www/ancc/README\n");
    exit(1);
}
$socket = '/var/run/mysqld/mysqld.sock';
$config = [
    'ENV' => 'staging',          // preview: dev login on, whole app behind the preview password
    'AUTH_MODE' => 'dev',
    'DB_SOCKET' => file_exists($socket) ? $socket : '',
    'DB_HOST' => '127.0.0.1',
    'DB_NAME' => $field('DATABASE_NAME') ?? 'ancc',
    'DB_USER' => $field('USERNAME') ?? 'ancc',
    'DB_PASSWORD' => $password,
    'BASE_PATH' => $base,
    'FRONTEND_URL' => $url . $base,
    'COOKIE_SECURE' => true,
    'JWT_SECRET' => bin2hex(random_bytes(32)),
];
$out = "<?php\n// ANCC settings on the IITD server. Keep private: holds the database password.\n"
    . "// When IITD OAuth is approved: ENV => 'production', AUTH_MODE => 'iitd', and add\n"
    . "// IITD_OAUTH_CLIENT_ID, IITD_OAUTH_CLIENT_SECRET and\n"
    . "// IITD_OAUTH_REDIRECT_URI => '" . $url . $base . "/api/v1/auth/callback'.\n"
    . 'return ' . var_export($config, true) . ";\n";
file_put_contents($path, $out);
PHP
)"

# ---------------------------------------------------------------------------------------------
cmd_check() {
  connect
  remote_script "$SITE" "$REMOTE_PHP" <<'REMOTE'
set -e
SITE=$1; PHP=$2
echo "user:    $(whoami)   groups: $(id -Gn)"
echo "php cli: $($PHP -r 'echo PHP_VERSION;')  pdo_mysql: $($PHP -r 'echo extension_loaded("pdo_mysql") ? "yes" : "NO";')"
[ -w "$SITE" ] && echo "write:   $SITE is writable" || { echo "write:   $SITE is NOT writable"; exit 1; }
id -Gn | tr ' ' '\n' | grep -qx ancc && echo "group:   in group ancc" || echo "group:   NOT in group ancc (ask the faculty in-charge to add you via CSC)"
[ -S /var/run/mysqld/mysqld.sock ] && echo "mysql:   socket /var/run/mysqld/mysqld.sock found" || echo "mysql:   no socket found; will use TCP 127.0.0.1:3306"
REMOTE

  say "Testing PHP under Apache, .htaccess rewrites and password protection"
  local tmp; tmp="$(mktemp -d)"
  mkdir -p "$tmp/check/locked"
  cat >"$tmp/check/index.php" <<'PHP'
<?php
header('Content-Type: text/plain');
echo 'ancc-check-ok ', PHP_VERSION, ' pdo_mysql=', extension_loaded('pdo_mysql') ? 'yes' : 'no', ' uri=', $_SERVER['REQUEST_URI'];
PHP
  printf 'RewriteEngine On\nRewriteCond %%{REQUEST_FILENAME} !-f\nRewriteRule ^ index.php [L]\n' >"$tmp/check/.htaccess"
  printf 'AuthType Basic\nAuthName "check"\nAuthUserFile %s/ancc-check/locked/.htpasswd\nRequire valid-user\n' "$SITE/html" >"$tmp/check/locked/.htaccess"
  printf 'nobody:$apr1$x$invalid\n' >"$tmp/check/locked/.htpasswd"
  push "$tmp/check/" "$HOST:$SITE/html/ancc-check/"
  remote "chgrp -R ancc $SITE/html/ancc-check 2>/dev/null; chmod -R g+rX $SITE/html/ancc-check"
  local out locked
  out="$(fetch "$URL/ancc-check/some/deep/path" || true)"
  locked="$(http_code "$URL/ancc-check/locked/")"
  remote "rm -rf $SITE/html/ancc-check"
  rm -rf "$tmp"
  local ok=1
  if [[ "$out" == "ancc-check-ok "*"uri=/ancc-check/some/deep/path"* ]]; then
    say "PHP + rewrites work (${out#ancc-check-ok })"
  else
    ok=0
    warn "Expected 'ancc-check-ok ...' from $URL/ancc-check/some/deep/path but got:"
    printf '%s\n' "$out" | head -20 >&2
  fi
  if [ "$locked" = "401" ]; then
    say "Password protection (.htaccess Basic Auth) works"
  else
    ok=0
    warn "Expected HTTP 401 from a password-protected folder but got $locked"
  fi
  [ "$ok" = 1 ] || die "Something is blocked. See: deploy/iitd/deploy.sh logs"
  say "All good. Next: deploy/iitd/deploy.sh setup"
}

# ---------------------------------------------------------------------------------------------
set_password() {
  need openssl "Install it with: sudo apt install openssl"
  local user pw pw2 hash
  read -r -p "Preview username (e.g. ancc): " user
  [[ "$user" =~ ^[A-Za-z0-9._-]+$ ]] || die "Use letters, digits, . _ - only"
  read -r -s -p "Preview password: " pw; echo
  read -r -s -p "Repeat password: " pw2; echo
  [ -n "$pw" ] && [ "$pw" = "$pw2" ] || die "Passwords are empty or don't match"
  hash="$(printf '%s' "$pw" | openssl passwd -apr1 -stdin)"
  printf '%s:%s\n' "$user" "$hash" | remote_stdin "umask 027 && cat > $APP/.htpasswd && chgrp ancc $APP/.htpasswd 2>/dev/null; true"
  say "Preview password saved. Share it only with club editors."
}

cmd_setup() {
  connect
  say "Creating folders and the server config ($APP/config.php)"
  remote_script "$SITE" "$APP" "$REMOTE_PHP" "$URL" "$BASE" "$SETUP_PHP" <<'REMOTE'
set -e
SITE=$1; APP=$2; PHP=$3; URL=$4; BASE=$5; SETUP_PHP=$6
umask 002
mkdir -p "$APP/backend" "$SITE/html${BASE}"
chgrp ancc "$APP" "$APP/backend" 2>/dev/null || true
chmod 2770 "$APP" 2>/dev/null || true
if [ -f "$APP/config.php" ]; then
  echo "   $APP/config.php already exists; leaving it as is."
  exit 0
fi
umask 027
printf '%s' "$SETUP_PHP" | base64 -d >"$APP/.setup-tmp.php"
"$PHP" "$APP/.setup-tmp.php" "$APP/config.php" "$URL" "$BASE" || { rm -f "$APP/.setup-tmp.php"; exit 1; }
rm -f "$APP/.setup-tmp.php"
chgrp ancc "$APP/config.php" 2>/dev/null || true
chmod 640 "$APP/config.php"
echo "   created $APP/config.php (mode 640, group ancc)"
REMOTE
  if [ "$PREVIEW" = "1" ]; then
    if remote "test -f $APP/.htpasswd"; then
      say "Preview password already set (change it with: deploy/iitd/deploy.sh password)"
    else
      say "Choose the preview username/password editors will use to open $URL$BASE/"
      set_password
    fi
  fi
  say "Setup done. Now run: deploy/iitd/deploy.sh"
}

# ---------------------------------------------------------------------------------------------
build() {
  need npm "Install Node 20+ (see README)."
  rm -rf "$BUILD" && mkdir -p "$BUILD"/{html,backend,logs}

  say "Building the frontend for $URL$BASE/"
  mkdir -p "$BUILD/logs"
  if ! (cd "$ROOT/frontend" && npm ci --no-audit --no-fund --loglevel=error &&
    VITE_BASE="$BASE/" npm run build -- --outDir "$BUILD/html" --emptyOutDir) >"$BUILD/logs/frontend.log" 2>&1; then
    tail -30 "$BUILD/logs/frontend.log" >&2
    die "Frontend build failed (full log: $BUILD/logs/frontend.log)"
  fi

  say "Packing the backend"
  rsync -a "$ROOT/backend/src" "$ROOT/backend/migrations" "$ROOT/backend/bin" "$BUILD/backend/"
  sed -e "s|@APP_DIR@|$APP|g" "$KIT/api.php.in" >"$BUILD/html/api.php"

  local auth=""
  if [ "$PREVIEW" = "1" ]; then
    auth="$(sed -e "s|@APP_DIR@|$APP|" "$KIT/auth-block.in")"
  fi
  AUTH="$auth" BASE="$BASE" awk '
    { gsub(/@BASE@/, ENVIRON["BASE"]) }
    /^@AUTH@$/ { print ENVIRON["AUTH"]; next }
    { print }
  ' "$KIT/htaccess-app.in" >"$BUILD/html/.htaccess"
}

cmd_deploy() {
  connect
  remote "test -f $APP/config.php" || die "Run 'deploy/iitd/deploy.sh setup' first."
  if [ "$PREVIEW" = "1" ]; then
    remote "test -f $APP/.htpasswd" || die "No preview password yet. Run: deploy/iitd/deploy.sh password"
  fi
  build

  say "Uploading (only changed files are sent)"
  push --delete "$BUILD/backend/" "$HOST:$APP/backend/"
  push --delete "$BUILD/html/" "$HOST:$SITE/html$BASE/"

  say "Fixing permissions and migrating the database"
  remote_script "$SITE" "$APP" "$REMOTE_PHP" "$BASE" <<'REMOTE'
set -e
SITE=$1; APP=$2; PHP=$3; BASE=$4
umask 002
for dir in "$APP/backend" "$SITE/html$BASE"; do
  find "$dir" -user "$USER" -type d -exec chmod 2775 {} + 2>/dev/null || true
  find "$dir" -user "$USER" -type f -exec chmod 664 {} + 2>/dev/null || true
  find "$dir" -user "$USER" -exec chgrp ancc {} + 2>/dev/null || true
done
# Leftovers from the first (Python/CGI) version, if they were ever deployed.
rm -rf "$APP/vendor" 2>/dev/null || true
for f in "$SITE/cgi-bin/api.cgi" "$SITE/cgi-bin/.htaccess"; do
  if [ -O "$f" ]; then rm -f "$f"; fi
done
export ANCC_CONFIG="$APP/config.php"
cd "$APP/backend"
echo "   database migrations:"
"$PHP" bin/migrate.php | sed 's/^/   /'
[ "${PIPESTATUS[0]}" -eq 0 ] || { echo "   migration FAILED (see above)"; exit 1; }
echo "   health check: $("$PHP" bin/health.php)"
REMOTE

  local code; code="$(http_code "$URL$BASE/")"
  case "$code" in
    401) say "Deployed. $URL$BASE/ is up and asks for the preview password (HTTP 401, as expected)." ;;
    200) say "Deployed. $URL$BASE/ is up (HTTP 200)." ;;
    *) warn "Uploaded, but $URL$BASE/ returned HTTP $code. Check: deploy/iitd/deploy.sh logs" ;;
  esac
}

cmd_reset_db() {
  connect
  warn "This deletes ALL data in the ancc database on the server (users, roles, everything)."
  local answer
  read -r -p "Type RESET to continue: " answer
  [ "$answer" = "RESET" ] || die "Cancelled."
  remote "cd $APP/backend && ANCC_CONFIG=$APP/config.php $REMOTE_PHP bin/migrate.php --reset"
  say "Database reset."
}

cmd_logs() {
  connect
  remote "tail -n ${1:-40} /var/www/ancc/apache-logs/error.log"
}

case "${1:-deploy}" in
  check) cmd_check ;;
  setup) cmd_setup ;;
  deploy) cmd_deploy ;;
  password) connect && set_password ;;
  logs) cmd_logs "${2:-40}" ;;
  reset-db) cmd_reset_db ;;
  -h | --help | help) sed -n '2,17p' "$0" | sed 's/^# \{0,1\}//' ;;
  *) die "Unknown command '$1'. Try: check, setup, deploy, password, logs, reset-db" ;;
esac
