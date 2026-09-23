# CLAUDE.md

Guidance for Claude Code (and humans) working in this repository.

## What this is

**Favr Directory** is a standalone WordPress plugin (PHP namespace `FavrDirectory\`, data prefix
`favr_`). It's a business directory for Chambers of Commerce and associations, part of the Favr Sites
product. PHP 8.1+, WordPress 6.7+ (block-theme templates use `register_block_template`), GPL-2.0-or-later, no runtime dependencies and no JS build step.
README.md has the feature tour, and docs/adr/ records the architecture decisions.

## Commands

```bash
composer install     # dev tools only; the plugin itself runs from a plain zip (bundled PSR-4 autoloader)
composer test        # PHPUnit unit tests (Brain Monkey stubs WordPress; pure logic only)
composer lint        # PHPCS with WordPress Coding Standards — keep at 0 errors
```

The local test site is "Local by WP Engine" at `http://sermonator-test.local/`, with this repo
symlinked to `wp-content/plugins/favr-directory`. There's no global `wp` binary: download
`wp-cli.phar` and run it with `php -d mysqli.default_socket=~/Library/Application Support/Local/run/<site-id>/mysql/mysqld.sock wp-cli.phar --path=...`.
`wp favr-directory seed --images` creates demo data.

## Architecture

- **One schema:** `Fields\FieldRegistry` defines every profile field. The admin UI (`Admin\FieldRenderer`),
  sanitizing (`Fields\Sanitizer`), meta/REST registration (`Model\Registrar`), CSV (`Support\CsvFormat`,
  `ImportExport\*`) and front end (`Model\Business`, templates) all derive from it. To add a field,
  change the registry (or use the `favr_directory_fields` filter). Don't special-case fields elsewhere.
- **Every write path goes through `Sanitizer`.** An empty sanitized value means *delete the meta*
  (never store blanks). Toggles whose default is on store an explicit `'0'`.
- **Read model:** `Model\Business` returns raw values. Templates escape at output. Absent data renders
  nothing (no empty rows).
- **Ordering:** `_favr_rank` is denormalized (featured → level order → A–Z). `Model\Ranking` keeps it
  current from every path (save, meta change, level assignment, level reorder). Queries use an
  EXISTS/NOT EXISTS clause so a business without a rank never disappears.
- **Front end:** `Frontend\Directory` (listing, GET params `fd_q|fd_cat|fd_level|fd_letter|fd_page`) and
  `Frontend\Profile` render through `templates/` (theme-overridable). Block themes use `BlockTemplates`,
  classic themes use `ClassicTemplates`, and page builders get the `the_content` fallback ("content" context,
  guarded against recursion).
- **Identifiers:** never hardcode post type, taxonomy, meta, option, cap or query-var strings. Use
  `Schema\Identifiers`.

## Conventions

- Match the surrounding style: WPCS formatting, camelCase methods, PSR-4 classes in `src/`.
- Staff-only fields use `'private' => true`. They must never reach REST, public CSV, shortcodes or templates.
- Caching: "Open now" is computed server-side *and* recomputed client-side (pages may be cached). Keep
  `assets/public/directory.js::isOpen` in sync with `Support\Hours::isOpenAt`.
- Add unit tests for any pure logic you touch (`tests/Unit`).
