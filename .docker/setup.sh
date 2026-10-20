#!/usr/bin/env bash
# Bring the test stack up from nothing and leave it ready for .docker/test.sh:
#
#   1. .env from .env.example (if missing) and the external dev-proxy network
#   2. docker compose up, then wait for Joomla's unattended install to finish
#   3. install com_weblinks (pinned release, SHA-256 checked) - skipped if already there
#   4. import the seed data (weblinks + the two module instances)
#
# Idempotent: re-running on a provisioned stack only re-imports the seed, which is a safe reset.
# Used by CI (.github/workflows/ci.yml) and works the same locally.
#
#   .docker/setup.sh
#
# mod_cuterweblinks itself is NOT installed here: the integration tests load the classes straight
# from the working tree (see tests/bootstrap.php). To look at the rendered module in a browser,
# build and install it as described in .docker/README.md.
set -euo pipefail
cd "$(dirname "$0")"

# Git Bash on Windows rewrites arguments that look like Unix paths before Docker sees them.
export MSYS_NO_PATHCONV=1

# Pinned on purpose: a moving "latest" would make CI red for reasons unrelated to a change.
# To bump: download the new pkg-weblinks-<version>.zip, run sha256sum on it, update both lines.
WEBLINKS_VERSION="${WEBLINKS_VERSION:-5.2.0}"
WEBLINKS_SHA256="${WEBLINKS_SHA256:-3f5a0081d64b857729711107d54fab6613f0b1e8c5c211a301972c29e42639ad}"

COMPOSE_FILE="${COMPOSE_FILE:-docker-compose.yml}"
WEB="${WEB:-cuterweblinks_dev_web}"
DB="${DB:-cuterweblinks_dev_db}"
JOOMLA="docker exec -w /var/www/html $WEB php cli/joomla.php"

log() { echo "[setup] $*"; }

# 1. config + network ------------------------------------------------------------------------------
if [ ! -f .env ]; then
  cp .env.example .env
  log "created .env from .env.example"
fi

# docker-compose.yml joins the shared dev-proxy network as external; where no Traefik runs (CI, a
# fresh machine) it just has to exist.
if ! docker network inspect dev-proxy >/dev/null 2>&1; then
  docker network create dev-proxy >/dev/null
  log "created docker network dev-proxy"
fi

# 2. containers + Joomla install ----------------------------------------------------------------------
docker compose -f "$COMPOSE_FILE" up -d

log "waiting for Joomla's unattended install (first boot takes a minute or two) ..."
ready=0
for _ in $(seq 1 120); do
  # configuration.php is written last; the CLI answering proves code, config and database line up
  if docker exec "$WEB" test -f /var/www/html/configuration.php 2>/dev/null \
     && $JOOMLA --version >/dev/null 2>&1; then
    ready=1
    break
  fi
  sleep 3
done
if [ "$ready" -ne 1 ]; then
  echo "[setup] Joomla did not come up in time. Last container output:" >&2
  docker logs --tail 40 "$WEB" >&2 || true
  exit 1
fi
log "$($JOOMLA --version | head -1)"

# 3. com_weblinks ---------------------------------------------------------------------------------------
if $JOOMLA extension:list 2>/dev/null | grep -q "com_weblinks"; then
  log "com_weblinks already installed - skipping"
else
  cache=".cache"
  zip="$cache/pkg-weblinks-$WEBLINKS_VERSION.zip"
  mkdir -p "$cache"

  if [ ! -f "$zip" ]; then
    log "downloading pkg-weblinks-$WEBLINKS_VERSION.zip"
    curl -fsSL -o "$zip" \
      "https://github.com/joomla-extensions/weblinks/releases/download/$WEBLINKS_VERSION/pkg-weblinks-$WEBLINKS_VERSION.zip"
  fi

  actual="$(sha256sum "$zip" | cut -d' ' -f1)"
  if [ "$actual" != "$WEBLINKS_SHA256" ]; then
    rm -f "$zip"
    echo "[setup] SHA-256 mismatch for pkg-weblinks-$WEBLINKS_VERSION.zip" >&2
    echo "        expected $WEBLINKS_SHA256" >&2
    echo "        got      $actual" >&2
    exit 1
  fi

  # docker cp wants a path the Docker CLI understands; on Git Bash that is the Windows form.
  src="$zip"
  if command -v cygpath >/dev/null 2>&1; then src="$(cygpath -m "$PWD/$zip")"; fi
  docker cp "$src" "$WEB:/tmp/weblinks.zip"

  # The CLI installer on a package zip occasionally fails with a bare "Unable to install extension"
  # (seen on Joomla 6 betas); installing the unpacked folder gives the same result more reliably.
  installed=0
  for attempt in 1 2 3; do
    if $JOOMLA extension:install --path=/tmp/weblinks.zip >/dev/null 2>&1; then
      installed=1
      break
    fi
    log "extension:install on the zip failed (attempt $attempt), trying the unpacked folder"
    # (no unzip binary in the image - PHP's ZipArchive is always there, Joomla needs it)
    docker exec "$WEB" sh -c 'rm -rf /tmp/weblinks_pkg && mkdir -p /tmp/weblinks_pkg' || true
    docker exec -i "$WEB" php <<'PHP' || true
<?php
$zip = new ZipArchive();
$zip->open('/tmp/weblinks.zip') && $zip->extractTo('/tmp/weblinks_pkg');
PHP
    if $JOOMLA extension:install --path=/tmp/weblinks_pkg >/dev/null 2>&1; then
      installed=1
      break
    fi
    sleep 5
  done
  if [ "$installed" -ne 1 ]; then
    echo "[setup] could not install pkg-weblinks. Verbose attempt:" >&2
    $JOOMLA extension:install --path=/tmp/weblinks.zip >&2 || true
    exit 1
  fi
  log "installed com_weblinks $WEBLINKS_VERSION"
fi

# 4. seed ------------------------------------------------------------------------------------------------
WEB="$WEB" DB="$DB" bash ./import-seed.sh

log "stack ready - run .docker/test.sh"
