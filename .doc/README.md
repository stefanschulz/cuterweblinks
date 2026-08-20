# Cuter Weblinks – Documentation

Internal documentation for developers and AI agents working on this Joomla module. For the user-facing feature overview, see the [project README](../README.md).

This is a small project (one helper class, one template, one XML manifest) — the documentation is kept deliberately small to match. Two files:

| File | Read this for |
|---|---|
| **[ARCHITECTURE.md](./ARCHITECTURE.md)** | Everything technical: file structure, the one DB query and its filtering logic, config param reference, compatibility notes, dev patterns for adding params/layouts. **Start here for any code change.** |
| **[CHANGES.md](./CHANGES.md)** | What changed release by release, and why. |

---

## 60-second orientation

- Joomla **site module**, no admin pages of its own beyond the standard module-config form, no AJAX, no JS, no own DB tables.
- Reads from the **Weblinks component** (`com_weblinks`), which is a **separate, non-core extension** — must be installed independently for this module to have anything to query. See "Compatibility" in ARCHITECTURE.md.
- All logic is in `src/Helper/CuterWeblinksHelper.php` (one query + a small CSS-path resolver). `tmpl/default.php` only renders what the helper already prepared.
- Version and Joomla-compatibility range live in `mod_cuterweblinks.xml`; `.releases/` is generated build output, not source — never hand-edit it, and check `git status` before committing since some IDEs auto-rebuild it in the background.

## Working on this project (human or agent)

1. Read ARCHITECTURE.md's "Development Patterns" section before adding a param or layout — it's a short checklist (manifest field → language keys → helper → template) and it's easy to miss the language file step.
2. This module has no automated tests. Verify behavior changes against a real Joomla instance — a disposable Docker stack (PHP-apache + MySQL, official Joomla zip, `cli/joomla.php extension:install`) is the pattern used so far; see CHANGES.md 1.5.1 for a worked example of what was checked and how.
3. When a change is bug-fix-only and backward compatible, that's a patch version; a new param/layout/feature is a minor version. Bump `<version>` in `mod_cuterweblinks.xml` accordingly and add an entry to CHANGES.md.
4. `build.xml`'s `targetplatform` regex must be updated by hand whenever a new Joomla release should be advertised as compatible — nothing does this automatically.

---

**Last Updated**: August 2026 · **Plugin Version**: 1.5.1
