#!/usr/bin/env bash
#
# Build an upload package for shared hosting (document root = public_html).
# Needs the Docker containers running (docker compose up -d).
#
#   ./deploy.sh fresh  New empty site: code only, no data — open the site and the installer
#                      sets up .env, the database tables and the first admin
#   ./deploy.sh full   First deploy of a site built locally: code + public/uploads + database export
#                      + server .env template
#   ./deploy.sh code   Updates after go-live: code only (the server's content and uploads are left alone)
#
# Output in deploy/:
#   surexcore-{fresh|full|code}-YYYYmmdd-HHMM.zip   extract into public_html/
#   database-YYYYmmdd-HHMM.sql            (full) import with phpMyAdmin on the server
#   env-server-YYYYmmdd-HHMM.txt          (full) fill in, upload as public_html/.env
#
set -euo pipefail
cd "$(dirname "$0")"

MODE="${1:-}"
if [[ "$MODE" != "fresh" && "$MODE" != "full" && "$MODE" != "code" ]]; then
    echo "Usage: ./deploy.sh fresh  (new empty site: the installer runs on the server)"
    echo "       ./deploy.sh full   (first deploy of a local site: code + uploads + database)"
    echo "       ./deploy.sh code   (updates: code only)"
    exit 1
fi

STAMP="$(date +%Y%m%d-%H%M)"
OUT="deploy"
BUILD="$OUT/.build"
ZIP="$OUT/surexcore-$MODE-$STAMP.zip"

echo "==> Copying files ($MODE)"
rm -rf "$BUILD"
mkdir -p "$BUILD"

EXCLUDES=(
    # local only
    --exclude='.git' --exclude='.gitignore' --exclude='.env' --exclude='.DS_Store'
    --exclude='/deploy/' --exclude='/deploy.sh' --exclude='/docker/' --exclude='/docker-compose.yml'
    --exclude='/tests/' --exclude='/phpunit.dist.xml' --exclude='/builds' --exclude='/README.md'
    # installed fresh without dev packages below
    --exclude='/vendor/'
    # leftovers
    --exclude='/View/frontend/contact/oldwp.blade.php'
    # runtime files: keep the folders (and their index.html / .htaccess), not the contents
    --include='/writable/*/index.html' --include='/writable/*/.htaccess'
    --exclude='/writable/cache/*' --exclude='/writable/logs/*' --exclude='/writable/session/*'
    --exclude='/writable/debugbar/*' --exclude='/writable/uploads/*' --exclude='/writable/backups/'
)
if [[ "$MODE" == "code" ]]; then
    # After go-live the server owns the content: never overwrite its uploads.
    EXCLUDES+=(--exclude='/public/uploads/')
fi
if [[ "$MODE" == "fresh" ]]; then
    # No local data: without installed.lock the site opens the installer.
    EXCLUDES+=(--include='/public/uploads/index.html' --exclude='/public/uploads/*' --exclude='/writable/installed.lock')
fi

rsync -a "${EXCLUDES[@]}" ./ "$BUILD/"

echo "==> Installing Composer packages (no dev)"
docker compose exec -T app composer install --no-dev --optimize-autoloader --no-interaction --quiet \
    --working-dir="/var/www/html/$BUILD"

echo "==> Creating $ZIP"
(cd "$BUILD" && zip -qr "../$(basename "$ZIP")" . -x '*.DS_Store')
rm -rf "$BUILD"

if [[ "$MODE" == "full" ]]; then
    SQL="$OUT/database-$STAMP.sql"
    echo "==> Exporting the database to $SQL"
    # Same dump as 設定 → バックアップ: importing it replaces every table with the local data,
    # except the activity log (the server keeps its own). Tables are dropped in foreign-key order.
    docker compose exec -T app php spark db:export "/var/www/html/$SQL" >/dev/null

    ENVFILE="$OUT/env-server-$STAMP.txt"
    echo "==> Writing the server .env template to $ENVFILE"
    KEY="$(docker compose exec -T app php spark key:generate --show 2>/dev/null | grep -o 'hex2bin:[0-9a-f]*')"
    cat > "$ENVFILE" <<EOF
# Server .env — fill in the ●● values, then upload as public_html/.env
# (A new encryption key was generated for this site. Keep this file private.)

CI_ENVIRONMENT = production

app.baseURL = 'https://●●example.com/'
app.appTimezone = 'Asia/Tokyo'
app.forceGlobalSecureRequests = true

cms.appName = '$(grep -E "^cms.appName" .env | cut -d= -f2- | xargs)'
cms.adminEmail = '●●'
cms.adminPath = '●●'

database.default.hostname = 'localhost'
database.default.database = '●●'
database.default.username = '●●'
database.default.password = '●●'
database.default.DBDriver = MySQLi
database.default.DBPrefix = '$(grep -E "^database.default.DBPrefix" .env | cut -d= -f2- | tr -d " '\"")'
database.default.port = 3306

encryption.key = $KEY

email.protocol = 'smtp'
email.fromEmail = '●●'
email.fromName = '●●'
email.SMTPHost = 'smtp.hostinger.com'
email.SMTPUser = '●●'
email.SMTPPass = '●●'
email.SMTPPort = 465
email.SMTPCrypto = 'ssl'
email.mailType = 'html'
EOF
fi

echo
echo "Done:"
ls -lh "$OUT" | grep "$STAMP"
