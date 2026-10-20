# Cuter Weblinks – Changelog

For full commit-level history use `git log`; this file summarizes what changed and, where it matters, *why*.

---

## Unreleased

No change in behaviour; nothing here is a release by itself, the refactor ships with the next one.

- **Tests**: PHPUnit suites added (`tests/Unit`, `tests/Integration`, `composer.json`, `phpunit.xml.dist`, `.docker/test.sh`). The integration suite checks `getLinks()` against the real database of the `.docker/` stack: access levels, state/published, language, ordering, category filter, count, field selection, image URL, `description_trim`, and the broken-table guard.
- **Refactor**: the `description_trim` logic moved unchanged from the `getLinks()` loop into `CuterWeblinksHelper::trimDescription()` so it can be unit-tested without Joomla.
- **Build**: `build.xml` now also keeps `vendor/`, `tests/`, `composer.json`, `composer.lock` and `phpunit.xml.dist` out of the release zip (verified: the zip content is unchanged).
- **Seed**: `.docker/seed/weblinks-seed.sql` gained an unpublished link and a link in an unpublished category (now 4 categories, 9 links), because the state/published filters were not covered by any fixture row.
- **CI**: GitHub Actions workflow (`unit`, `package`, `integration` jobs, see ARCHITECTURE.md "Testing") and `.docker/setup.sh`, which provisions the Docker stack from scratch (pinned `com_weblinks`, seed) for CI and for local use.
- **Known quirk (pinned by a test, not changed)**: `description_trim` drops a word that ends exactly at the limit (`alpha beta gamma` at 10 → `alpha…`), since it cuts at the last space *inside* the first N characters.

## 1.6.1 (October 2026)

Patch release — bug fix only.

- **Fix**: `description_trim` glued adjacent paragraphs together without a space (`<p>…end.</p><p>Next…</p>` became `…end.Next…`) because `strip_tags()` removes tags without a replacement. Block-level tags (`p`, `div`, `br`, `hr`, `li`, `ul`, `ol`, `h1`–`h6`, `table`, `tr`, `td`, `th`, `blockquote`, `pre`) are now replaced by a space before stripping; inline tags (`b`, `a`, `span`, …) are still removed without one so words like `Joom<b>la</b>` stay whole. Found during Joomla 6.2.0 testing. Output with `description_trim` = `0` is unchanged.

## 1.6.0 (October 2026)

Minor release — new optional param, fully backward compatible (default preserves prior behavior byte-for-byte).

- **New**: `description_trim` param (number, default `0`, only shown when "Show description" is on). When set to a positive value, the description is stripped of HTML and cut to plain text of at most that many characters, breaking at the last whole word and appending `…` only if something was actually cut. `0` keeps today's behavior unchanged: raw HTML, untouched. Driven by the `empulsiv` site template's need for equal-height cards, where unbounded rich-text HTML breaks CSS line-clamping.
- Implemented without any Joomla string-helper dependency (plain `strip_tags`/`mb_substr`), to avoid coupling to a helper that has moved between Joomla versions in the past.

## 1.5.1 (August 2026)

Patch release — bug fixes only, no new features or config changes.

- **Fix**: `CuterWeblinksHelper::getLinks()` no longer crashes with `SQL error 1054` when a module instance's saved params lack `ordering`/`direction` (e.g. rows created outside the normal admin form). Both now default to `title`/`ASC`, matching the manifest.
- **Fix (access control)**: the query now filters `weblinks.access` and `categories.access` against the current visitor's authorised view levels. Previously, Weblinks/categories restricted to e.g. "Registered" or "Special" were shown to every visitor regardless of access level — titles/descriptions leaked even though the destination link itself might still have enforced access.
- **Fix (missing dependency)**: if the Weblinks component (`com_weblinks`) is not installed or disabled, the frontend now checks `ComponentHelper::isEnabled()` and skips rendering entirely instead of letting an unhandled DB exception crash the whole page with HTTP 500. The module edit form shows a warning (new `WeblinksstatusField`) whenever the dependency is missing/disabled, and stays silent otherwise.
- **Fix (defense in depth)**: the query itself is now wrapped in `try`/`catch` against `\RuntimeException`, covering the remaining case where `com_weblinks` is marked enabled but its tables are missing or broken (e.g. an interrupted uninstall) — the `ComponentHelper` check alone doesn't catch that. Note: the concrete exception thrown on a missing table varied between Joomla's own `PrepareStatementFailureException` and PHP's native `mysqli_sql_exception` (PHP 8.1+ default mysqli error mode) during testing — both are unrelated sibling classes, hence catching the common `\RuntimeException` ancestor rather than enumerating types.
- **Fix (i18n)**: `language/de-DE/mod_cuterweblinks.ini` had a mismatched key (`..._FILTER_LANGUAGE` instead of `..._RESPECT_LANGUAGE`) for the language-filter option, so German admins saw the raw language key instead of translated text.
- **Compatibility**: `build.xml`'s `targetplatform` extended to include Joomla 6.1 and 6.2.
- **Verification**: all fixes were tested against a disposable Joomla **6.2.0-beta1** instance (Docker: PHP 8.3-apache + MySQL 8). Covered scenarios: SQL-error-on-missing-ordering no longer occurs; anonymous visitor vs. Super User see correctly access-filtered link sets; component missing/disabled → HTTP 200 with module skipped and backend warning shown; component enabled but table missing → HTTP 200 (previously 500); normal rendering with real data unaffected by any of the above.
- Copyright year in file headers updated to 2026.

## 1.5.0 and earlier

See `git log` for full detail; notable milestones:

- **1.5.0** — "Respect site language" option (`languages` param): filter links by the current site language.
- Support added incrementally for Joomla 5.2, 6.0, 6.1 (`targetplatform` updates as new Joomla versions released).
- Only show published, active links (state/published filter) — early correctness fix.
- Link target option (open in same window/tab vs. new one).
- 1.2 — initial public release on GitHub.

---

## How to decide patch vs. minor for the next release

- **Patch**: bug fixes, compatibility bumps, copyright/doc updates — no new module params, no behavior change a site owner would need to configure.
- **Minor**: new module param(s), new layout, new optional feature — backward compatible, existing configs keep working unchanged.
- **Major**: not used so far; would imply a breaking change (e.g. a renamed/removed param, a changed DB dependency).
