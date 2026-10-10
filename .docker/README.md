# cuterweblinks-dev — disposable dev/test instance

Fresh Joomla + MariaDB for developing/testing `mod_cuterweblinks`, kept
separate from any real site so install/uninstall and schema-edge-case tests
(see `.doc/ARCHITECTURE.md` → "Missing-dependency handling") stay
non-destructive and the instance can be wiped freely.

- Front end: <http://cuterweblinks.localhost/>
- Admin: <http://cuterweblinks.localhost/administrator/> (see `.env`)
- Direct fallback (no `*.localhost` DNS, e.g. curl): <http://localhost:8092/>

All `docker compose` commands below are run from the repo root with
`--project-directory .docker`.

## Prerequisites

The shared reverse proxy must be up once (see `P:\sites\_proxy\`):

```bash
docker network create dev-proxy        # once per machine
cd P:/sites/_proxy && docker compose up -d
```

## Bring-up

```bash
cp .docker/.env.example .docker/.env       # then edit if you want
docker compose --project-directory .docker up -d
```

First boot: the official Joomla image runs an unattended install using the
`JOOMLA_*` env vars (takes ~30–60 s; watch
`docker compose --project-directory .docker logs -f joomla`).

## Installing the extensions

On Windows run the `docker exec`/`docker cp` commands from **Git Bash with
`MSYS_NO_PATHCONV=1`** (otherwise `/var/www/html` and `/tmp/...` get mangled
into Windows paths).

This module has a hard dependency on the separate **Weblinks** component
(`com_weblinks`) - it is not part of Joomla core and not vendored in this
repo (see `.doc/ARCHITECTURE.md`). Install it first:

1. Download a `pkg-weblinks-*.zip` release from
   <https://github.com/joomla-extensions/weblinks/releases> (tested against
   5.1.0) and install it:

   ```bash
   docker cp pkg-weblinks-5.1.0.zip cuterweblinks_dev_web:/tmp/weblinks.zip
   MSYS_NO_PATHCONV=1 docker exec -w /var/www/html cuterweblinks_dev_web \
     php cli/joomla.php extension:install --path=/tmp/weblinks.zip
   ```

   If `extension:install` on a package zip fails with a generic "Unable to
   install extension" (seen against Joomla 6.2 betas during this module's own
   1.5.1 testing - unrelated to this module's code), extract the zip first and
   install from the extracted folder instead, or use the admin UI's
   **Extensions → Manage → Install → Install from Folder** tab, which surfaces
   a more specific error:

   ```bash
   MSYS_NO_PATHCONV=1 docker exec cuterweblinks_dev_web mkdir -p /tmp/weblinks_pkg
   MSYS_NO_PATHCONV=1 docker exec cuterweblinks_dev_web unzip -oq /tmp/weblinks.zip -d /tmp/weblinks_pkg
   MSYS_NO_PATHCONV=1 docker exec -w /var/www/html cuterweblinks_dev_web \
     php cli/joomla.php extension:install --path=/tmp/weblinks_pkg
   ```

2. Build this module's package from the repo root and install it:

   ```bash
   phing package   # -> .releases/cuterweblinks-<version>.zip
   docker cp .releases/cuterweblinks-$(grep -oP '(?<=<version>)[^<]+' mod_cuterweblinks.xml).zip \
     cuterweblinks_dev_web:/tmp/mod.zip
   MSYS_NO_PATHCONV=1 docker exec -w /var/www/html cuterweblinks_dev_web \
     php cli/joomla.php extension:install --path=/tmp/mod.zip
   ```

   Re-run after every manifest/code change to upgrade in place (the installer
   runs in `upgrade` mode) - or see "Iterating on the code" below for a faster
   loop once the extension is installed once.

## Seeding test data

```bash
MSYS_NO_PATHCONV=1 bash .docker/import-seed.sh
```

Imports `.docker/seed/weblinks-seed.sql` (3 categories at different access
levels + 7 weblinks deliberately covering: category- vs. weblink-level access
restriction, a long multi-paragraph description for `description_trim`
testing, an empty description, a non-default-language entry, and a featured
entry) and `.docker/seed/module-seed.sql`, which publishes two module
instances to `sidebar-right` on every page:

- **Cuter Weblinks - Defaults** — no params set at all. This is the
  regression guard: its rendered output must never change when a new param
  with a sensible default is added (see `.doc/ARCHITECTURE.md` → "The 1.5.1
  default-value bug").
- **Cuter Weblinks - Full Featured** — every display option on, including
  `description_trim: 140` and `languages: 1` (respect site language).

Both scripts are idempotent (`DELETE` then re-`INSERT`), safe to re-run after
any seed-file edit without a full `down -v`.

### What to check after seeding

- Anonymous visit to `http://cuterweblinks.localhost/`: "Registered Only
  Link" and "Special Access Link" must **not** appear in either module
  instance (access-level filtering, 1.5.1). "German Only Link" only appears
  in "Full Featured" if the site's current language is `de-DE`.
- "Defaults" instance: "Long Description Example" shows its full raw HTML
  (multiple `<p>` tags, no truncation).
- "Full Featured" instance: same entry shows as a single plain-text line,
  HTML-free, ≤ 141 characters, cut at a word boundary with a trailing `…`.
- "No Description Example": no description block in either instance, and
  specifically no stray `…` in "Full Featured" (nothing was there to cut).

## Iterating on the code

**Slow, faithful loop** (matches how it ships): edit the repo, `phing
package`, re-run the `extension:install` step above, refresh.

**Fast loop**: after the first install, uncomment the `../src`, `../tmpl`,
`../media/css` bind mounts in `docker-compose.yml` and re-run
`docker compose --project-directory .docker up -d`. Edits under those
directories then apply immediately, no rebuild/reinstall needed. Re-run
`extension:install` only when `mod_cuterweblinks.xml` or the language files
change (new/changed form fields, new language keys).

## Reset

```bash
docker compose --project-directory .docker down -v
docker compose --project-directory .docker up -d
```

## Second instance (e.g. to re-check a specific Joomla version)

Copy `.env` to `.env.<tag>`, set `JOOMLA_TAG` accordingly, and run with a
different project name + host so it doesn't collide with the primary
instance:

```bash
docker compose --project-directory .docker -p cuterweblinks-dev62 --env-file .docker/.env.62 up -d
```

Change the Traefik router/service name and `Host` rule in a copied compose
file (`cuterweblinks-dev62`, `cuterweblinks62.localhost`) to match.
