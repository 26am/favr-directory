# ADR 0001 — Build a small native field framework instead of depending on ACF / CMB2

- **Status:** Accepted
- **Date:** 2026-09-23

## Context

Favr Directory needs an editing experience that feels native to WordPress but is as smooth as
ACF or CMB2: tabs, image and gallery pickers, repeaters, conditional fields, a weekly-hours
editor. It ships inside Favr Sites, a pre-built site product, so it has to install cleanly on
many sites that we don't fully control, and it lives in a **public** GitHub repo.

## Options considered

| Option | Pros | Cons |
| --- | --- | --- |
| **ACF (free)** | Best-known UX, great field types | No repeater or gallery in the free version. It's a separate plugin sites must keep installed and updated; field groups live in the DB or JSON sync. |
| **ACF Pro** | Repeater, gallery, options pages | Commercial license: can't be bundled in a public GPL repo or redistributed to every Favr site without a license per site. Vendor lock-in for our data model. |
| **CMB2** | GPL, bundle-able, declarative PHP | Dated UI (doesn't feel like modern WP). Adds ~1 MB of library code we'd carry forever. Hours/repeater UX still needs custom work. |
| **Carbon Fields / Meta Box** | Modern APIs | Composer or extra plugins needed. Meta Box's good bits are paid extensions. |
| **Own lightweight framework** (chosen) | Zero dependencies. The UI is designed for exactly this use case (hours grid, completeness meter, chips). One declarative schema drives admin UI, sanitizing, REST, CSV and front end. | We maintain ~1.5k lines of PHP/JS. |

## Decision

We build a small, declarative field layer inside the plugin:

- `src/Fields/FieldRegistry.php` — the **only** definition of the business profile (tabs + fields).
  Extensible with the `favr_directory_fields` and `favr_directory_tabs` filters.
- `src/Fields/Sanitizer.php` — pure, unit-tested sanitizing per field type. Every write path
  (edit screen, REST, CSV import) goes through it.
- `src/Admin/FieldRenderer.php` + `assets/admin/*` — native-looking inputs using only what WordPress
  ships (media modal, jQuery UI sortable, color picker, dashicons). There's no build step.

Businesses use the classic edit form with the profile as a tabbed panel under the title. A business
is a structured record, not an article, and meta boxes under Gutenberg give a worse experience.
Sites can opt back in with the `favr_directory_use_block_editor` filter.

## What would change this answer

- If Favr Sites standardizes on ACF Pro with an agency license across every site, a thin ACF adapter
  (registering the same schema as ACF field groups) becomes attractive. The registry makes that a
  contained change.
- If the profile grows beyond ~80 fields or needs relational fields (e.g. linking businesses to
  events), re-evaluate Meta Box or a custom table.
