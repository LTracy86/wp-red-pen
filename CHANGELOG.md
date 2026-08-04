# Changelog

All notable changes to WP Red Pen are recorded here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Versions below 1.0.0 are pre-release: 1.0.0 marks the first build considered
ready for a public wp.org-style release.

Entries from 0.12.0 onward were reconstructed from `readme.txt` and the git
history. Releases before 0.12.0 were never written down, so they are left out
rather than guessed at. Release dates come from the tag or commit that shipped
each version; 0.12.0 and 0.12.1 predate the surviving history in this
repository, so they carry no date.

## [Unreleased]

## [0.26.0] - 2026-08-04

### Security

- **Client reviewer links no longer expose internal notes.** Any note that was
  open, on a public page and not aimed at an agent was readable by anyone
  holding a reviewer link, including a developer's own working notes. Notes are
  now internal by default and reach a reviewer only when the author explicitly
  ticks **Visible to client reviewers** on the note. The check fails closed: no
  flag means internal, so every note that predates this release is invisible to
  reviewer links until it is shared deliberately.
- Every reviewer read path - the scoped GET, the page priming payload, the
  create echo and the reply echo - now runs through one gate,
  `wprp_reviewer_can_see_note()`, instead of each scoping itself. The reply
  endpoint re-checks the gate before echoing a note back.
- A one-time migration backfills the client-visible flag on notes that carry the
  via-review stamp, so feedback a client filed before this release stays visible
  to the link that filed it. The scope is only those notes: `_wprp_via_review` is
  written in a single place, the reviewer branch of `wprp_create_note()`, so no
  note written by a developer can carry it. Legacy notes predate the token stamp,
  so they remain open-only rather than gaining the new see-your-own-at-any-status
  behaviour, which is exactly what they had before.
- Notes filed through a reviewer link now record which link they came from, so
  one client's link cannot surface another client's history.

### Added

- **Visible to client reviewers** checkbox on the note form (front-end panel and
  the repository edit panel), off by default. Shared notes carry a badge in the
  front-end list, the edit-screen meta box and the repository table, and a
  **Client visible** column in the CSV export.
- Reviewers can now see their own reports at any status, with the date the
  report was marked fixed. Previously reviewer reads were pinned to open notes,
  so a client's Resolved tab could never fill and a fixed report looked
  identical to one that never saved.
- A visible **Cancel** control in the element-pin picker, for devices with no
  Esc key.

### Changed

- Element pins are labelled by the element's own visible text (falling back to
  its alt / aria-label / title, then a plain-language name for the tag) instead
  of reporting "Pinned to div". Editing a note re-resolves the saved selector,
  so a pin whose target has gone reads as gone.
- Pin mode no longer blocks touch scrolling. The picker used to call
  `preventDefault()` on every touchmove, which meant a phone reviewer could only
  pin what happened to already be on screen. The page now scrolls normally and a
  pin is placed with a deliberate tap; a drag is treated as a scroll. The tap's
  synthetic click is swallowed, so dismissing the picker no longer follows a
  link on the page underneath.
- The floating button, the toast and the pin-mode Cancel control respect
  `env(safe-area-inset-*)`, so the button is no longer parked under the iOS
  Safari bottom bar. On touch devices form controls are floated to 16px, which
  stops iOS zooming the page when a field takes focus.

### Removed

- The remains of the PRO tier: the embedded Ed25519 licence public key, the
  licence verifier, the legacy `wprp_pro_key` option read, and every
  `wprp_is_pro()` branch. The 2026-07-14 pivot made every feature free
  permanently; the branded client report is now simply what the plugin does.
  The `wprp_is_pro` filter is gone with it. Uninstall still deletes the legacy
  `wprp_pro_key` option so old installs are cleaned up.

### Fixed

- `Plugin URI` pointed at `tracydigitalmedia.com/wp-red-pen/`, which returns 404.
  It now points at the product site, `redpen.tools`.
- `readme.txt` pointed donors at a GitHub Sponsors profile that is not live. It
  now points at Buy Me a Coffee only, matching `FUNDING.yml`.
- README.md no longer claims logged-out visitors never receive plugin assets
  (reviewer links have been a thing since 0.13.0) or that the REST routes are
  nonce-only.

### Documentation

- Added this changelog, `CONTRIBUTING.md`, and GitHub issue templates.
- The single plugin file gained a table of contents and numbered section
  banners.

## [0.25.5] - 2026-07-29

### Fixed

- The panel header, tab row and add form compressed when a page had enough
  notes to overflow the panel. They inherited flex-shrink from the panel's flex
  column; they are now pinned at their content height so the note list scrolls
  instead.

## [0.25.4] - 2026-07-22

### Fixed

- Uninstall now removes every option the plugin created, including the
  client-report brand settings and the legacy licence key.

### Documentation

- Manual and readme refreshed after the open-source pivot - white-label client
  reports are free for everyone.

## [0.25.3] - 2026-07-21

### Fixed

- Theme-proofing, round two. Attribute-qualified theme rules (for example
  `button[type="submit"] { ... !important }`) out-rank plain class selectors and
  were still restyling the Add note and Reply buttons. The whole widget
  stylesheet now sits one ID tier up, with every component rule scoped under its
  mount-root ID, so no realistic site selector can out-rank it.

## [0.25.1] and [0.25.2] - 2026-07-21

### Fixed

- Follow-ups to the 0.25.0 theme-proofing pass: the whole front-end sheet was
  scoped one ID tier up because attribute-qualified theme rules were still
  winning, and `#wprp-fab` / `#wprp-panel` were re-anchored above the raised
  armor layer.

## [0.25.0] - 2026-07-21

### Changed

- Theme-proofing. The review panel, pins, capture overlay and markup editor hold
  their look on sites whose stylesheets restyle bare elements (`button`, `p`,
  `a`, focus rings) with `!important`. Every widget style is asserted at full
  strength, an armor layer pins the typography properties components inherit,
  and the last generic state classes (`is-active`, `is-resolved`,
  `is-resizing`) are namespaced to `wprp-*`.

## [0.24.0] - 2026-07-15

### Changed

- **Open source.** Every feature is free, including white-label client reports.
  No licence key, no PRO tier, no gate.

## [0.23.2] - 2026-07-09

### Changed

- The Red Pen nib mark now appears in the front-end panel header.

## [0.23.1] - 2026-07-09

### Changed

- The nib logo is used consistently across the admin bar, the floating button,
  the Tools menu entry and the repository header.

## [0.23.0] - 2026-07-08

### Added

- Licence keys could be pooled licence ids, not just buyer emails, for
  storefront auto-delivery. *(Superseded by 0.24.0, which removed the paid tier;
  the code was removed in 0.26.0.)*

## [0.22.0] - 2026-07-07

### Added

- Offline licence gate using an Ed25519-signed key, verified against a public
  key embedded in the plugin. *(Superseded by 0.24.0; removed in 0.26.0.)*

## [0.21.0] - 2026-07-07

### Added

- White-label client report: your own title, logo and accent colour on the
  printable client report, and an option to hide the Red Pen credit. The plain
  report was free; branding was gated at the time. *(Ungated in 0.24.0.)*

## [0.20.1] - 2026-07-04

### Fixed

- Sites connected to the Red Pen Hub by pushing (the no-Application-Password
  option) now send each note's severity, resolved date, resolver and assignee
  too, so the board shows the same detail for push-connected sites as for the
  ones the Hub pulls from.

## [0.20.0] - 2026-07-04

### Added

- Optional **Severity** field on notes (Blocker / Critical / Major / Minor /
  Trivial) - an impact axis separate from Priority. Set it in the note form
  under More; it shows on the note, in the CSV export, and on the Red Pen Hub
  board.

## [0.19.0] - 2026-07-03

### Added

- Notes carry a precise creation timestamp and, once resolved, a resolved date
  and the name of whoever resolved them, so the Hub board can show and sort by
  when each note was created and closed.

### Fixed

- The Red Pen Hub can pull every note on the site into the combined board, not
  just site-wide notes. Page and post-attached notes were missing from the Hub
  view.

## [0.18.1] - 2026-07-02

### Security

- Closed a gap where a client review-link holder could read notes they were not
  meant to see (site-wide dev notes, notes on draft or private pages, and
  agent-queue notes) by replying to them. Replies from a review link are limited
  to the same open, public-page notes the reviewer can already read.

### Fixed

- CSV export includes In Progress notes in the "All" export, and each row shows
  its real status. They were dropped and mislabelled before.

## [0.18.0] - 2026-07-01

### Security

- Hardening for client review links. A review-link holder can only read notes on
  public, published pages - never drafts, private posts, or site-wide dev notes
  - and their own feedback always attaches to the page they are actually on, so
  it can no longer be aimed at other pages or made site-wide.
- Agent brief files use an unguessable filename so they cannot be fetched
  directly on servers that ignore the folder's access rules.
- Uninstalling removes every stored option, including the Hub connect token and
  review-link secrets.

## [0.17.0] - 2026-07-01

### Added

- **Site-wide** reporting level: a note can apply to every page of the site, not
  just this page or this template. It shows on the front-end panel everywhere
  and carries a Site-wide badge in the repository.

## [0.16.0] - 2026-06-29

### Added

- Edit a note's text and type inline from the repository table (Tools > Red
  Pen). Priority and assignee were already editable there.

## [0.15.0] - 2026-06-29

### Added

- Jump to the next open note: an admin-bar item, and the `J` key, cycles through
  the open notes pinned on the current page, scrolling each into view and
  highlighting it.

### Changed

- The "No open notes on this page" message stands out with a red background.

## [0.14.0] - 2026-06-29

### Added

- Draw on your screenshots. After a snip, a markup window opens with freehand
  pen, arrow and rectangle tools plus undo, so you can circle exactly what you
  mean. The marks are saved into the image, and clicking an existing thumbnail
  reopens markup to add more.

## [0.13.0] - 2026-06-21

### Added

- **Client Reviewer Link.** Share a link that lets a client or outside reviewer
  leave notes on the front end without a WordPress login. Their feedback comes
  in attributed and scoped: at the time, they saw only open notes on the page
  they were viewing, and none of the dev-only controls. Includes an anti-abuse
  rate limiter on the anonymous create path, and a Client badge plus a source
  filter in the repository.

## [0.12.1]

### Fixed

- Connecting to Red Pen Hub silently failed when the Hub URL was entered without
  `http://` or `https://`. Schemeless URLs are handled.

## [0.12.0]

### Added

- Connect to Red Pen Hub: point the site at a local Hub (URL and token under
  Display settings) and notes push to the shared cross-project board whenever
  they change. Outbound only, no external service; the site stays the source of
  truth.
