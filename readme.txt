=== Favr Directory ===
Contributors: favrsites
Tags: business directory, chamber of commerce, member directory, association, listings
Requires at least: 6.7
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A friendly, searchable business directory for Chambers of Commerce and associations.

== Description ==

Favr Directory lets chamber and association staff manage member businesses from a clean, native WordPress screen, and gives visitors a fast directory with rich business profiles.

* Tabbed business profile: contact, location, weekly hours, social, photos and video, membership, member deals and more.
* Business categories and membership levels (featured and higher tiers are listed first).
* Instant search, category filter, A–Z browsing, grid and list layouts.
* Profiles with an "Open now" badge, map, directions, photo lightbox and schema.org structured data.
* CSV import and export, WP-CLI commands and a Directory Manager role.
* Front-end editing for business representatives: live edits or staff-approved suggestions per field, photo uploads, and "Claim this listing".
* Directory and Listing profile blocks, shortcodes for page builders, and overridable templates.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Directory → Add listing.
3. Visit /directory/ on your site, or add the Directory block to any page.

== Changelog ==

= 1.2.1 =
* Staff screens say "listing" in sentence case, whatever the directory lists: Directory → All listings, Add listing, Categories; "Edit listing", "View listing", "Listing published."; the list screen is titled "Directory" (on a directory of people it was "Members", like the Members menu). Categories are "Directory categories"; membership levels follow the same style. Public pages, REST and the front-end forms keep the directory's own noun.
* Fields and tabs can carry an `admin_label` / `admin_description` for the staff edit screen.
* Fixed "$(...).sortable is not a function" on the listings list, category, level and settings screens.
* Fixed: updating the plugin no longer removes capabilities other plugins gave the Directory Manager role (Favr Members lets Directory Managers manage members).
* The Level column of the Directory list shows each level with its own color; it is still the link to the list filtered by that level.
* Membership levels use word for word the labels Favr Members gives them, so the Membership levels screen reads the same whichever of the two plugins a site runs.
* Fixed: "Edit" on the permalink under a listing's name now saves. The plugin used to remove WordPress's Slug box, which holds the field that edit is saved through, so a new address was dropped on Update.
* The Directory list's search also finds a listing by company, e-mail, phone or city.
* The Listing health checklist ticks an item off as soon as its field is filled.
* Page builders say "listing" too: the Elementor widgets ("Directory", "Listing profile", "Listing field", "My listing (edit form)"), their settings, the dynamic tags, the blocks and the block templates. Internal names are unchanged, so existing pages keep working.
* Membership levels are listed in their own order with the color before the name; the Contact column shows the e-mail first, with the full address on hover.

= 1.2.0 =
* Directory card and "Add listing" button on the Favr dashboard (Favr Sites plugin).

= 1.1.0 =
* People directories (bar associations, professional societies): Organization / firm and Languages spoken fields, custom listing nouns ("members", "attorneys"), Person structured data, and A–Z by last name (with an optional "Sort as").
* Elementor widgets and dynamic tags, block bindings, and front-end editing for representatives.

= 1.0.0 =
* First release.
