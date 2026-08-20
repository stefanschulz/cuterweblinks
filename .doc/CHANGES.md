# Cuter Weblinks – Changelog

For full commit-level history use `git log`; this file summarizes what changed and, where it matters, *why*.

---

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
