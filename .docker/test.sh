#!/usr/bin/env bash
# Run the PHPUnit suites (Unit + Integration) inside the Joomla container of the test stack.
#
# Unit tests need nothing but PHP and also run on the host (`composer run test`). The Integration
# suite exercises CuterWeblinksHelper::getLinks() against the real database, so it needs the
# Joomla install, com_weblinks and the seed data: see .docker/README.md ("Installing the
# extensions", "Seeding test data").
#
# The working tree is mounted read-only at /repo, so PHPUnit's cache goes to /tmp. Run
# `composer install` on the host once first - vendor/ is shared through that mount.
#
#   .docker/test.sh                              # everything
#   .docker/test.sh --testsuite Unit             # only the unit tests
#   .docker/test.sh --filter OrderBy             # any PHPUnit argument is passed through
#   CONTAINER=cuterweblinks62_dev_web .docker/test.sh   # against the Joomla 6.2 stack (joomla62/)
set -euo pipefail
cd "$(dirname "$0")"

# Git Bash on Windows rewrites arguments that look like Unix paths (/repo -> C:/Program Files/Git/repo)
# before Docker ever sees them. Harmless everywhere else.
export MSYS_NO_PATHCONV=1

CONTAINER="${CONTAINER:-cuterweblinks_dev_web}"

if [ ! -f ../vendor/bin/phpunit ]; then
  echo "vendor/bin/phpunit is missing - run 'composer install' in the repo root first." >&2
  exit 1
fi

docker exec -e JOOMLA_ROOT=/var/www/html -w /repo "$CONTAINER" \
  php vendor/bin/phpunit \
  --configuration phpunit.xml.dist \
  --cache-directory /tmp/phpunit \
  "$@"
