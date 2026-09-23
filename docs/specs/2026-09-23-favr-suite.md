# Favr Suite — Directory, Members, Events

- **Status:** Favr Directory ✅ built · Favr Members v1 ✅ built ([26am/favr-members](https://github.com/26am/favr-members)) · favr/core ✅ ([26am/favr-core](https://github.com/26am/favr-core)) · Directory front-end editing and Favr Events: next
- **Date:** 2026-09-23
- **Scope:** How the three Favr Sites plugins divide responsibilities and connect. It also covers member front-end editing of listings and member event submissions.
- **Out of scope:** Payments and dues collection. Membership status is set by staff; the chamber invoices outside the site.

## 1. The model: one Member record, two simple types

Favr Sites clients are a mix: chambers, where the member is a **business**, and associations, where the member is an **individual**. Most sites have both. Every membership is therefore one **Member** record (Favr Members, `favr_member`) with a type chosen once:

| | **Individual member** | **Business member** |
| --- | --- | --- |
| Who pays / holds the membership | The person | The business |
| Staff fill in | Name, email, phone, level, status, dates | Business name, level, status, dates, representatives |
| Login | The member themselves | One or more **representatives** (owner, office manager…) |
| Directory listing | None; they don't see listing features | Linked 1:1 to a Favr Directory business (created automatically) |
| Member dashboard shows | My Account, My Events | My Account, My Listing, My Events |

Membership fields (level, status, member since, renewal date, member ID, staff notes) live on the **Member** record, which is the single source of truth. The membership-level taxonomy (`favr_member_level`) is shared: whichever plugin loads first registers it, with identical arguments from `favr/core`.

**Keeping individuals simple.**
- Staff click Members → Add Member → *Individual*, fill in one short form, and optionally tick "Send login invite".
- The person logs in to a dashboard with just their account and events: no business fields, no listing tabs, no claim flow.
- An individual can also represent a business member (e.g. a consultant who owns a firm). They then see that business's tabs too, with nothing extra to set up.

**The directory still works alone.** Without Favr Members, the Directory keeps its own level and membership fields exactly as today. When Members is active, it **syncs** level, status and dates onto the linked listing, and those listing fields become read-only in the Directory with "Managed in Members →". Ranking, badges and filters keep working unchanged.

## 2. Who owns what

| Concern | Plugin | Notes |
| --- | --- | --- |
| Listings, categories, public profile display | **Favr Directory** | Already built. Adds §4. |
| Member records (individual or business), level, status, dates, staff notes | **Favr Members** | New. Source of truth when active; syncs onto listings. No payments. |
| Accounts: registration, login, password reset, profile, the "My Account" dashboard shell | **Favr Members** | New. |
| Linking representatives to business members (invite, claim, approve) | **Favr Members** | Directory's "Claim this listing" button hands off to Members. |
| Events, calendar, submissions, "Hosted by" business | **Favr Events** | New. |
| Field framework, sanitizer, settings, templates, approval queue | **favr/core** (shared package) | Extracted when Members starts (§6). |

Each plugin works alone. With only Directory installed, staff can still link listings to ordinary WordPress users by hand.

## 3. How the plugins connect (hooks, not hard dependencies)

- **Favr Members** renders the dashboard at a page chosen in its settings. Tabs come from `favr_members_dashboard_tabs`. Each tab has an id, label, capability and render callback.
- **Favr Directory** adds a **My Listing** tab, or **My Listings** when the person represents several businesses.
- **Favr Events** adds a **My Events** tab (submit, view status, edit before approval).
- **Membership status:** `favr_members_is_active( $user_id )` returns true when the person holds an active individual membership **or** represents an active business member. Any plugin can ask "is this person a current member" without knowing the member type.
- **Listing sync:** Favr Members fires `favr_members_member_saved`. Directory listens and copies level, status and dates onto the linked `favr_business`, then marks those fields read-only via `favr_directory_field_locked`.
- **Shared approval inbox:** Directory and Events each register their queues. Staff get one **Approvals** screen, with a count badge in the admin menu.

## 4. Favr Directory: front-end editing (next build)

**Field access policy.** Each field definition gains `member_access`:
- `edit`: saved immediately. Defaults: hours, phone, email, website, social, gallery, deal, links, highlights.
- `review`: saved as a proposed change. Defaults: business name, description, logo, cover, categories, address.
- `none`: never shown to members. Defaults: level, featured, member ID, renewal date, staff notes, and anything `private`.

Staff can override these defaults per field in Settings.

**Proposed changes.**
- Stored as `_favr_pending_changes` on the business: field id, old value, new value, the person, and the time. The live listing does not change.
- The queue shows before and after with **Approve all**, **Approve selected** and **Reject** (with an optional note).
- Every approved or immediate change is logged in `_favr_change_log`, capped at the last 100 entries.

**Front-end form.**
- Reuses `FieldRegistry`, `Sanitizer` and the same tabs, restyled for the front end with a front-end renderer (not the admin one).
- Fields that need review show a "Changes are reviewed by staff" note.
- Photos upload through a Directory REST endpoint:
  - images only, with a size limit
  - the attachment is owned by the uploading user
  - a person can only use their own uploads
  - no wp-admin media library access
- It's the `favr-directory/my-listing` block and `[favr_my_listing]` shortcode, and it also renders inside the Members dashboard tab.

**Claim and invite.**
- **Claim:** a logged-in person clicks "Is this your business? Claim it" on a profile. Favr Members records the request, and staff approve or reject it in the Approvals inbox, which adds the person as a representative.
- **Invite:** staff enter an email on the business screen. The person gets an invite link that creates their account through Favr Members, or through core registration if Members isn't installed, and links them.

**Security.** Every write checks: logged in, the person is a representative of this business, the field's access is not `none`, a nonce, and the `Sanitizer`. There's a rate limit on submissions and a honeypot on claim forms.

**Notifications.** Staff are emailed when changes are proposed or a claim arrives. The representative is emailed when a proposal is approved or rejected. The templates are filterable.

## 5. Favr Members (v1) and Favr Events (v1)

**Favr Members v1**
- **Member records** (`favr_member` CPT): type Individual or Business, level, status (*active*, *pending*, *lapsed*, *inactive*), member since, renewal date, member ID, staff notes, and representatives (Business type) or the linked user (Individual type).
- Staff list of all members with type, level, status and renewal date, plus filters and CSV import/export, using the same patterns as the Directory.
- `favr_member_person` role (read only, no wp-admin; people who open wp-admin are redirected to the dashboard).
- Front-end login, registration (**invite-only** by default; *open with staff approval* optional), password reset, and profile (name, title, phone, photo).
- The dashboard shell and tab API from §3.
- Member-only content: a "Members only" block wrapper and a page-level toggle, using `favr_members_is_active`.
- Staff screen listing people (logins), which membership(s) they hold or represent, and last login.
- **Not in v1:** payments, dues, invoices, automated renewal reminders. Reminders are a likely v1.1, since the renewal date already exists.

**Favr Events v1**
- **Hosted by** can be a business member's listing or an individual member (shown by name). An individual member's events never require a business.
- `favr_event` CPT with start and end date/time, all-day and multi-day events, and simple recurrence (weekly or monthly, stored as a rule; occurrences generated on read with a cap). Also venue or online link, cost text, registration URL, event categories, and a **Hosted by** link to a business.
- Calendar (month) and list views with filters, and an iCal feed plus per-event "Add to calendar". schema.org `Event` structured data, breadcrumbs, and sitemap, as in Directory.
- Submissions come from **My Events**. New events are created with WordPress's native `pending` status. Once approved, edits by the submitter follow the same `edit`/`review` field policy as listings.
- A business's profile shows its upcoming events, and an event shows its host's card.

## 6. Shared code: `favr/core`

The field registry, sanitizer, settings store, template loader, asset versioning and approval-queue primitives move into a Composer package that's copied into each plugin. [Strauss](https://github.com/BrianHenryIE/strauss) prefixes its namespace per plugin (e.g. `FavrDirectory\Vendor\FavrCore`). Two sites running different plugin versions then can't conflict, and each plugin stays installable on its own.

It's extracted when Favr Members starts, the first time a second plugin actually needs it, rather than designed up front.

## 7. Build order

1. **Favr Members v1**: accounts, dashboard shell and tab API; extract `favr/core`.
2. **Directory: front-end editing**: access policy, proposed changes, uploads, claim/invite, and the Approvals inbox.
3. **Favr Events v1**: including member submissions through the same inbox.

**Decided 2026-09-23:** clients have a mix of individual and business members, and individuals must stay simple (§1). Payments remain out of scope.
