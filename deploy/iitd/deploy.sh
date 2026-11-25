#!/usr/bin/env bash
# Deploy the ANCC app to the IITD CSC server (shared Apache + PHP hosting, no sudo).
#
# Run it either ON THE SERVER from a clone of the "deploy" branch (built by GitHub Actions, so no
# Node is needed there), or from your own machine (Linux or macOS) while on the IITD network or VPN.
# On the server it notices /var/www/ancc and works locally, without SSH.
#
#   deploy/iitd/deploy.sh check      # test PHP, .htaccess and folder permissions on the server
#   deploy/iitd/deploy.sh setup      # first time only: create the server config
#   deploy/iitd/deploy.sh            # build and upload the app (same as "deploy")
#   deploy/iitd/deploy.sh update     # on the server: git pull the deploy branch, then deploy
#   deploy/iitd/deploy.sh logs       # show the last lines of Apache's error log
#   deploy/iitd/deploy.sh reset-db   # drop ALL tables and recreate them (asks first)
#
# Layout on the server (see deploy/iitd/README.md):
#   https://ancc.iitd.ac.in/new/        -> /var/www/ancc/https/html/new/  (React build, .htaccess, api.php)
#   https://ancc.iitd.ac.in/new/api/... -> html/new/api.php -> app code in /var/www/ancc/https/app/
#   /var/www/ancc/https/app/            -> backend code, config.php (not reachable from the web)
set -euo pipefail
set -f # no filename globbing; keeps options like "--noproxy *" intact

if [ -z "${ANCC_HOST:-}" ] && [ -d /var/www/ancc/https ]; then
  HOST=local                                      # we are on the server itself
else
  HOST="${ANCC_HOST:-ancc}"                       # ssh target, e.g. an alias from ~/.ssh/config; "local" = this machine
fi
URL="${ANCC_URL:-https://ancc.iitd.ac.in}"        # public URL of the site
SUBDIR="${ANCC_SUBDIR:-new}"                      # app lives at $URL/$SUBDIR/
REMOTE_PHP="${ANCC_REMOTE_PHP:-php}"              # PHP command-line binary on the server
SITE=/var/www/ancc/https
APP="$SITE/app"
BASE="/$SUBDIR"

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
KIT="$ROOT/deploy/iitd"
BUILD="$ROOT/build/iitd"
read -r -a CURL_EXTRA <<<"${ANCC_CURL_OPTS:-}"
LOCAL=0
if [ "$HOST" = local ]; then
  LOCAL=1
  # The server's http(s)_proxy is for the outside world; reach our own site directly
  # (unless ANCC_CURL_OPTS says otherwise, e.g. "--proxy http://henna.iitd.ac.in:3128").
  if [[ "${ANCC_CURL_OPTS:-}" != *proxy* ]]; then
    url_host="${URL#*://}"; url_host="${url_host%%/*}"
    CURL_EXTRA+=(--noproxy "$url_host")
  fi
fi

# One SSH connection for the whole run, so you type your Kerberos password at most once.
# If your ~/.ssh/config already shares connections for this host (ControlPath + ControlPersist),
# reuse that, so an already-open connection means no password at all.
USER_CTL=""
[ "$LOCAL" = 1 ] || USER_CTL="$(ssh -G "$HOST" 2>/dev/null | awk '$1 == "controlpath" { print $2 }')" || true
if [ "$LOCAL" = 1 ]; then
  SOCK_DIR=""
  SSH_OPTS=()
elif [ -n "$USER_CTL" ] && [ "$USER_CTL" != "none" ]; then
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
# In local mode the same command strings run in a local bash instead.
if [ "$LOCAL" = 1 ]; then
  remote() { bash -c "$*" </dev/null; }
  remote_stdin() { bash -c "$*"; }
  push() { rsync -rlt -O "$@"; }
  at() { printf '%s' "$1"; }                     # a path on the server, as an rsync target
else
  remote() { ssh -n ${SSH_OPTS[@]+"${SSH_OPTS[@]}"} "$HOST" "$@"; }
  remote_stdin() { ssh ${SSH_OPTS[@]+"${SSH_OPTS[@]}"} "$HOST" "$@"; }
  push() { rsync -rlt -O -e "ssh ${SSH_OPTS[*]}" "$@"; }
  at() { printf '%s:%s' "$HOST" "$1"; }
fi
# Run a bash script (from stdin) on the server with positional arguments.
remote_script() { remote_stdin "bash -s -- $(printf '%q ' "$@")"; }

# The web root must be writable by us to create the app folder in it. Prints the fix if not.
HTML_CHECK='
if [ ! -w "$SITE/html" ]; then
  owner=$(stat -c %U "$SITE/html")
  echo "   $SITE/html is not writable by you (owner $owner, group $(stat -c %G "$SITE/html"), mode $(stat -c %a "$SITE/html"))."
  echo "   Ask $owner to run on the server:"
  echo "     chgrp ancc $SITE/html && chmod 2775 $SITE/html"
  exit 1
fi'
fetch() { curl -sS ${CURL_EXTRA[@]+"${CURL_EXTRA[@]}"} "$@"; }
http_code() { local c; c="$(fetch -o /dev/null -w '%{http_code}' "$@" 2>/dev/null)" || true; echo "${c:-000}"; }

connect() {
  need rsync "Install it with: sudo apt install rsync"
  need curl "Install it with: sudo apt install curl"
  if [ "$LOCAL" = 1 ]; then
    say "Running on the server itself ($(hostname))"
    umask 002
    return
  fi
  need ssh "Install OpenSSH."
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
    'ENV' => 'staging',          // dev login on until IITD OAuth is approved
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
    . "// IITD_OAUTH_CLIENT_ID and IITD_OAUTH_CLIENT_SECRET (from the IITD OAuth administrator, who\n"
    . "// registers our redirect URI " . $url . $base . "/api/v1/auth/callback).\n"
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
  remote_script "$SITE" <<<"SITE=\$1$HTML_CHECK" || die "Fix the web root's permissions first (see above)."

  say "Testing PHP under Apache and .htaccess rewrites"
  local tmp; tmp="$(mktemp -d)"
  mkdir -p "$tmp/check"
  cat >"$tmp/check/index.php" <<'PHP'
<?php
header('Content-Type: text/plain');
echo 'ancc-check-ok ', PHP_VERSION, ' pdo_mysql=', extension_loaded('pdo_mysql') ? 'yes' : 'no', ' uri=', $_SERVER['REQUEST_URI'];
PHP
  printf 'RewriteEngine On\nRewriteCond %%{REQUEST_FILENAME} !-f\nRewriteRule ^ index.php [L]\n' >"$tmp/check/.htaccess"
  push "$tmp/check/" "$(at "$SITE/html/ancc-check/")"
  remote "chgrp -R ancc $SITE/html/ancc-check 2>/dev/null; chmod -R g+rX $SITE/html/ancc-check"
  local out
  out="$(fetch "$URL/ancc-check/some/deep/path" || true)"
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
  [ "$ok" = 1 ] || die "Something is blocked. See: deploy/iitd/deploy.sh logs"
  say "All good. Next: deploy/iitd/deploy.sh setup"
}

# ---------------------------------------------------------------------------------------------
cmd_setup() {
  connect
  say "Creating folders and the server config ($APP/config.php)"
  remote_script "$SITE" <<<"SITE=\$1$HTML_CHECK" || die "Fix the web root's permissions first (see above)."
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
  say "Setup done. Now run: deploy/iitd/deploy.sh"
}

# ---------------------------------------------------------------------------------------------
build() {
  rm -rf "$BUILD" && mkdir -p "$BUILD"/{html,backend,logs}

  local dist="$ROOT/frontend/dist"
  if [ -f "$dist/.ancc-base" ]; then
    # A clone of the "deploy" branch: GitHub Actions already built the frontend.
    local built_for; built_for="$(cat "$dist/.ancc-base")"
    [ "$built_for" = "$BASE/" ] ||
      die "The prebuilt frontend is for $built_for, not $BASE/. Change the build in .github/workflows/ci.yml."
    say "Using the frontend built by GitHub Actions (main@$(cut -c1-7 "$dist/.ancc-source" 2>/dev/null || echo '?'))"
    rsync -a --exclude='.ancc-*' "$dist/" "$BUILD/html/"
  else
    need npm "Install Node 20+ (see README)."
    local major; major="$(node -p 'process.versions.node.split(".")[0]')"
    [ "$major" -ge 20 ] || die "Node $(node -v) is too old to build the frontend (needs 20+). On the IITD server, use a clone of the deploy branch (see deploy/iitd/README.md)."
    say "Building the frontend for $URL$BASE/"
    if ! (cd "$ROOT/frontend" && npm ci --no-audit --no-fund --loglevel=error &&
      VITE_BASE="$BASE/" npm run build -- --outDir "$BUILD/html" --emptyOutDir) >"$BUILD/logs/frontend.log" 2>&1; then
      tail -30 "$BUILD/logs/frontend.log" >&2
      die "Frontend build failed (full log: $BUILD/logs/frontend.log)"
    fi
  fi

  say "Packing the backend"
  rsync -a "$ROOT/backend/src" "$ROOT/backend/migrations" "$ROOT/backend/bin" "$BUILD/backend/"
  sed -e "s|@APP_DIR@|$APP|g" "$KIT/api.php.in" >"$BUILD/html/api.php"
  sed -e "s|@BASE@|$BASE|g" "$KIT/htaccess-app.in" >"$BUILD/html/.htaccess"
}

cmd_deploy() {
  connect
  remote "test -f $APP/config.php" || die "Run 'deploy/iitd/deploy.sh setup' first."
  build

  say "Uploading (only changed files are sent)"
  push --delete "$BUILD/backend/" "$(at "$APP/backend/")"
  push --delete "$BUILD/html/" "$(at "$SITE/html$BASE/")"

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
# The old preview password file (that feature was removed).
if [ -O "$APP/.htpasswd" ]; then rm -f "$APP/.htpasswd"; fi
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
    200) say "Deployed. $URL$BASE/ is up (HTTP 200)."
      if remote "grep -q \"'AUTH_MODE' => 'dev'\" $APP/config.php"; then
        warn "Dev login is on: anyone who can open $URL$BASE/ can log in as any user, including admins. Test data only."
      fi ;;
    *) warn "Uploaded, but $URL$BASE/ returned HTTP $code. Check: deploy/iitd/deploy.sh logs" ;;
  esac
}

cmd_update() {
  [ "$LOCAL" = 1 ] || die "'update' is for running on the server. From your machine: git pull, then deploy/iitd/deploy.sh"
  local branch; branch="$(git -C "$ROOT" rev-parse --abbrev-ref HEAD)"
  [ "$branch" = deploy ] || die "This clone is on '$branch'; the server clone should be on the deploy branch."
  say "Getting the latest build from GitHub"
  git -C "$ROOT" pull --ff-only --quiet || die "git pull failed (see above)."
  say "Now at: $(git -C "$ROOT" log -1 --format='%s (%cr)')"
  exec "$0" deploy  # run the freshly pulled version of this script
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
  update) cmd_update ;;
  logs) cmd_logs "${2:-40}" ;;
  reset-db) cmd_reset_db ;;
  -h | --help | help) sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//' ;;
  *) die "Unknown command '$1'. Try: check, setup, deploy, update, logs, reset-db" ;;
esac
