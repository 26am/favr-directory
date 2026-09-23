# ADR 0002: Elementor and block editor integration

- **Status:** Accepted, 2026-09-23
- **Applies to:** Favr Directory, Favr Members, Favr Events (shared code in favr/core ≥ 0.4.1)

## Context

Favr Sites are built with Elementor, while WordPress itself (and some clients) use the block
editor. Front-end output was reachable only through shortcodes plus a few blocks. Shortcodes
work everywhere but are invisible in both builders: no settings panel, no preview, no styling,
no dynamic data. Neither builder may become a requirement: every plugin must run on a plain
WordPress install.

## Decision

**One renderer per feature, three ways to place it.** Each feature (directory, profile, events
calendar, login, account, My Listing, My Events…) has a single PHP renderer. The shortcode, the
block and the Elementor widget are thin adapters over it, so output, caching behaviour and
security checks never diverge.

**Block editor (always available)**
- A block for every feature (server-rendered, no build step). New: Member Login, Member
  Account, Membership Application, My Events.
- **Block bindings** sources `favr-directory/business` and `favr-events/event` let core
  Paragraph, Heading, Button and Image blocks show business or event data in single templates.

**Elementor (only when Elementor is active)**
- Native widgets in a shared **Favr** panel category, with the same settings as the blocks and
  an accent color control. The base class lives in favr/core; it is referenced only inside
  Elementor's `elementor/widgets/register` hook, so nothing loads without Elementor.
- **Dynamic tags** (used by Elementor Pro's Theme Builder): business text/links/images and
  event text/links, from the same value providers as block bindings (`Integration\FieldValues`).
- **Favr Members visibility** on every section, container and widget (members, non-members,
  logged in, logged out), plus a **Members only** page setting that drives the same page flag as
  the block editor's toggle.
- All Favr widgets, and any element with a visibility rule, are marked dynamic so Elementor's
  element cache renders them per visitor. Whole-page gating also filters Elementor's own content
  output (`elementor/frontend/the_content`), which replaces `the_content` after our gate runs.

**Theming:** stylesheets read `--favr-brand` (set per widget or block wrapper), then the plugin's
accent setting (a `:root` variable), then the theme's primary color.

## Not doing (for now)

- Elementor v4 "atomic" elements: still experimental; revisit when stable.
- Per-widget style controls beyond the accent color (typography, spacing): the plugins inherit
  the theme, and Elementor's own Advanced tab already covers spacing and custom CSS.
- Other builders (Bricks, Divi, Beaver): shortcodes remain the universal fallback.

## Consequences

- Adding a feature means one renderer plus three small adapters. The widget base and value
  providers keep each adapter to a few lines.
- Elementor Pro users can build fully custom single-business and single-event templates. Free
  Elementor users get the widgets (including Business Field) and visibility controls.
