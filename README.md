# Favr Directory

A friendly business directory for **Chambers of Commerce** and **associations**, built for
[Favr Sites](https://github.com/26am). Staff add and manage member businesses in a native-feeling
WordPress admin, and visitors get a fast, searchable directory with rich business profiles.

- **Requires:** WordPress 6.7+, PHP 8.1+
- **License:** GPL-2.0-or-later
- **Dependencies:** no ACF and no build step. Shared field UI and moderation code come from
  [favr/core](https://github.com/26am/favr-core), bundled (namespace-prefixed) in `vendor-prefixed/`.
  See [ADR 0001](docs/adr/0001-custom-field-framework.md).

## Features

**For directory staff (wp-admin → Directory)**

- One tabbed **Business Profile** panel: Overview, Contact, Location, Hours, Social, Photos & Video,
  Membership, Deals & Extras. Fill in a little or a lot; empty sections never show on the site.
- Purpose-built inputs: logo picker, drag-to-reorder photo gallery, weekly hours grid ("copy Monday
  to all weekdays", overnight and 24-hour days), highlight chips, link repeater, conditional
  fields, and URL auto-fix (`example.com` → `https://example.com`).
- **Listing health** meter with one-click "Add logo / Add hours…" shortcuts.
- List table with logos, contact details, city, a one-click ⭐ featured toggle, a completeness bar,
  and category / level / featured filters.
- **Business Categories** (hierarchical) and **Membership Levels** (display order + badge color;
  Platinum/Gold/Silver/Member seeded on install).
- **Import / Export CSV** with a test-run mode. Existing businesses are matched by id, slug or
  name, and only the columns in the file are changed.
- A **Directory Manager** role that can manage listings without touching the rest of the site.
- Staff-only fields (member ID, renewal date, notes) that are never shown publicly or over REST.

**For visitors**

- Directory at `/directory/` (configurable) with search (name, description, tagline, city and
  category names), a category filter, A–Z browsing, and grid/list views. Results update instantly
  with JavaScript and still work fully without it (plain GET URLs you can share and crawl).
- Ordering: featured first, then higher membership tiers, then A–Z.
- Business profiles with cover and logo, action buttons (Call / Website / Directions / Email /
  Book), member deal with copyable promo code, highlights, photo lightbox, video embed, hours with
  a cache-safe **Open now** badge, a lazy-loaded map, social links and "at a glance" facts.
- **SEO:** schema.org `LocalBusiness` (linked to your chamber via `memberOf`) on business pages,
  `ItemList` on directory and category pages, and `BreadcrumbList` everywhere. With **Yoast SEO**
  or **Rank Math** active, everything is merged into their single JSON-LD graph and their
  breadcrumbs follow the directory trail. Filtered and search URLs are `noindex,follow`.
  Business and category pages appear in the sitemap automatically, and `/directory/` is added to
  the core sitemap.

**For business representatives (front-end editing)**

- **My Listing**: a representative updates their own listing from the front end: in the Favr
  Members dashboard, on any page with the **My Listing** block, or with `[favr_my_listing]`. The
  page is found automatically (or set under Settings → Member editing).
- Every item has an access level that staff can change in Settings: **Edit** (live immediately:
  phone, hours, social links, deals…), **Suggest** (staff approve first: name, description,
  categories, address, logo, cover, gallery, video…) or **Hidden** (featured, member since).
  Staff-only fields can never be exposed.
- Photos upload straight from the form (images only, size-limited). There's no wp-admin or media
  library access, and people can only use their own uploads.
- Suggestions land in the shared **Approvals** screen (with Favr Events and Favr Members):
  before/after comparison, approve all or selected fields, or reject with a note. The
  representative is emailed either way. Approvals refuse to act on suggestions that changed while
  a reviewer was looking.
- **Claim this listing:** logged-in visitors can claim a listing. Staff approve in Approvals, and the
  person becomes a listing manager, or a representative of the linked member with Favr Members.
- Staff see and manage **Listing managers** on the business screen (add by email; new people get
  an invitation), plus a log of recent changes by representatives.

## Displaying the directory

| Where | How |
| --- | --- |
| Automatic | `/directory/`, `/directory/category/{slug}/`, `/directory/{business}/` |
| Block editor | **Business Directory** block (filters, featured-only, layout, order…) and **Business Profile** block |
| Shortcodes | `[favr_directory]`, `[favr_directory featured="1" per_page="6" search="0" letters="0"]`, `[favr_directory category="restaurants" layout="list"]` |
| Representative editing | **My Listing** block or `[favr_my_listing]` (also a Favr Members dashboard tab) |
| Page builders (Elementor, etc.) | `[favr_business id="123"]` for a whole profile, `[favr_business_field field="phone"]` for one value (also `address`, `hours`, `map`, `social`, `logo`, `categories`, `level`, any field id) |

Theme support: block themes get registered templates (`single-favr_business`,
`archive-favr_business`, `taxonomy-favr_business_cat`) that stay editable in the Site Editor.
Classic themes get PHP templates. If a theme or page builder owns the single template, the profile
is injected through `the_content`.

## Customizing

- **Templates:** copy any file from `templates/` into `yourtheme/favr-directory/` (e.g.
  `favr-directory/parts/card.php`).
- **Styling:** everything is scoped under `.favr-dir` / `.favr-profile` and driven by CSS custom
  properties (`--favr-accent`, `--favr-radius`, …). The accent color is also a setting.
- **Fields:** add, change or remove fields with the `favr_directory_fields` filter. They then appear
  in the admin, REST, CSV and `[favr_business_field]` automatically.

```php
add_filter( 'favr_directory_fields', function ( array $fields ) {
	$fields['license_number'] = array(
		'id'    => 'license_number',
		'label' => 'License number',
		'type'  => 'text',
		'tab'   => 'membership',
		'width' => 'half',
	);
	return $fields;
} );
```

Other hooks: `favr_directory_schema_graph`, `favr_directory_tabs`, `favr_directory_highlight_options`, `favr_directory_query_args`,
`favr_directory_schema`, `favr_directory_template`, `favr_directory_filter_content`,
`favr_directory_use_block_editor`, `favr_directory_after_tab`, `favr_directory_after_profile`,
`favr_directory_business_saved`, `favr_directory_loaded`.

## Data model

| Thing | Identifier |
| --- | --- |
| Post type | `favr_business` (REST: `/wp/v2/businesses`) |
| Categories | `favr_business_cat` (REST: `/wp/v2/business-categories`) |
| Membership levels | `favr_member_level` (REST: `/wp/v2/membership-levels`) |
| Field meta | `favr_{field_id}` (typed, sanitized, registered for REST unless private) |
| Sort key | `_favr_rank` (denormalized; `wp favr-directory rerank` rebuilds it) |
| Settings | `favr_directory_settings` |

All identifiers live in `src/Schema/Identifiers.php`. Never hardcode them elsewhere.

## WP-CLI

```bash
wp favr-directory seed [--images]            # 14 sample chamber businesses (+ placeholder photos)
wp favr-directory import members.csv [--dry-run] [--download-images]
wp favr-directory export [--file=out.csv] [--public-only]
wp favr-directory rerank
```

## Development

```bash
composer install
composer test          # PHPUnit (Brain Monkey, pure logic)
composer lint          # PHPCS — WordPress Coding Standards
```

For local development, symlink the repo into a site's `wp-content/plugins/favr-directory`. There's
nothing to build: CSS and JS are plain files in `assets/`. With `WP_DEBUG` on, asset URLs are
cache-busted by file modification time.

```
favr-directory.php        bootstrap (constants, autoloader, hooks)
src/Plugin.php            composition root
src/Schema/               identifiers
src/Fields/               field registry + sanitizer (the profile's single source of truth)
src/Model/                post type/taxonomies/meta, Business read model, ranking, caps, activation
src/Admin/                edit screen, list table, levels, settings, import/export, assets
src/Frontend/             directory query + renderer, profile, blocks, templates, shortcodes, SEO
src/ImportExport/         CSV importer/exporter (shared by admin and CLI)
templates/                overridable PHP templates
blocks/                   block.json metadata (server-rendered blocks)
assets/                   admin, public and block-editor CSS/JS
tests/                    unit tests
data/                     sample businesses CSV (wp favr-directory seed)
```
