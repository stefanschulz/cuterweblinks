#!/usr/bin/env bash
# Import weblinks + module seed data into the running cuterweblinks-dev instance.
#
# Reads the live table prefix from Joomla's configuration.php and rewrites the
# #__ placeholder accordingly, then pipes both dumps into MariaDB.
#
# Run from Git Bash:  ./.docker/import-seed.sh
set -euo pipefail
cd "$(dirname "$0")"

WEB="${WEB:-cuterweblinks_dev_web}"
DB="${DB:-cuterweblinks_dev_db}"

# shellcheck disable=SC1091
[ -f .env ] && set -a && . ./.env && set +a
DBNAME="${DB_NAME:-joomla}"
DBUSER="${DB_USER:-joomla}"
DBPASS="${DB_PASSWORD:-joomla}"

prefix="$(docker exec "$WEB" sh -c "grep -oP \"public .dbprefix\\s*=\\s*'\\K[^']+\" configuration.php" || true)"
if [ -z "${prefix:-}" ]; then
  echo "!! could not read \$dbprefix from configuration.php inside $WEB." >&2
  echo "   Is Joomla finished installing? Try again in a minute." >&2
  exit 1
fi
echo "live table prefix: $prefix"

sed "s/\`#__/\`${prefix}/g" seed/weblinks-seed.sql \
  | docker exec -i "$DB" mariadb -u"$DBUSER" -p"$DBPASS" "$DBNAME"
echo "Weblinks seed imported (4 categories, 9 weblinks)."

sed "s/\`#__/\`${prefix}/g" seed/module-seed.sql \
  | docker exec -i "$DB" mariadb -u"$DBUSER" -p"$DBPASS" "$DBNAME"
echo "Module seed imported - published 'Cuter Weblinks - Defaults' and 'Cuter Weblinks - Full Featured'."

echo
echo "Done. Visit http://cuterweblinks.localhost/ (or the HTTP_PORT fallback)."
