=== WP Red Pen ===
Contributors: ltracy
Donate link: https://buymeacoffee.com/lincolntracy
Tags: editorial, review, notes, annotations, workflow
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.23.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A logged-in review layer. Flag posts and pages with notes and suggested edits from a floating button, then track them all in one shared repository.

== Description ==

WP Red Pen turns any WordPress site into a markup surface for the people who run it. Switch on Dev Mode and a floating button appears on the front end of every post and page. Click it to leave a note, flag a problem, or suggest an edit - right where you spotted it.

Every note is stored in WordPress and shows up in two places: a panel on that post's edit screen, and a single repository page that all editors and admins share, so nothing falls through the cracks.

Built for editorial review, client hand-offs, content audits, and team to-do lists - without a single third-party service.

= What it does =

* **Floating note button** on the front end for logged-in editors and admins (Dev Mode toggle in the admin bar, per user)
* **Snip a screenshot** - click Screenshot, drag a box around the area, done. The region is captured client-side and saved as a small WebP attached to the note (shown on the note, the edit-screen panel, and the repository)
* **Typed notes** - plain note, suggested edit, bug/problem, or question
* **Per-post panel** - see and resolve a page's notes from its edit screen
* **Shared repository** - one admin page listing every note across the site, filterable by Open / Resolved / All
* **Open-note badge** in the admin bar and on the floating button
* **Resolve / reopen** any note in a click
* Clean uninstall - deletes every note and preference it created, touches nothing else

= The free-tier promise =

WP Red Pen's core is free forever, with an optional tip jar. It deliberately does NOT bolt on the things competitors paywall - no email alerts, no external SaaS sync, no per-seat fees. Your notes live in your database and nowhere else. An optional one-time PRO purchase (offline-verified key, never phones home) adds white-label client reports and the multi-site Hub board.

== Installation ==

1. Upload the `wp-red-pen` folder to `/wp-content/plugins/`, or install the zip through Plugins -> Add New -> Upload.
2. Activate the plugin.
3. In the admin bar, click **Red Pen: Off** to switch Dev Mode on for your account.
4. Visit any post or page on the front end and use the floating button (bottom right) to add a note.
5. Review everything under the **Red Pen** menu in the dashboard.

== Frequently Asked Questions ==

= Who can see and add notes? =

Any logged-in user who can edit posts (editors and administrators by default). Visitors and subscribers never see the button or the notes.

= Where are the notes stored? =

As a private custom post type in your own WordPress database. They are never sent anywhere else.

= Does it slow down my site for visitors? =

No. The floating button and its code only load for logged-in editors/admins with Dev Mode on. Logged-out visitors get nothing extra.

= What happens if I delete the plugin? =

Deleting (not just deactivating) removes every note and the per-user Dev Mode preference. Nothing else on your site is touched.

== Changelog ==

= 0.20.1 =
* Sites connected to the Red Pen Hub by pushing (the no-Application-Password option) now send each note's severity, resolved date, resolver, and assignee too - so the board shows the same detail for push-connected sites as for the ones the Hub pulls from.

= 0.20.0 =
* Added an optional Severity field to notes (Blocker / Critical / Major / Minor / Trivial) - a separate impact axis from Priority, for teams that triage by how bad a bug is as well as how soon to fix it. Set it in the note form under More; it shows on the note, in the CSV export, and on the Red Pen Hub board.

= 0.19.0 =
* The Red Pen Hub can now pull every note on the site into the combined board, not just site-wide notes - page and post-attached notes were previously missing from the Hub view.
* Notes now carry a precise creation timestamp and, once resolved, a resolved date and the name of whoever resolved them, so the Hub board can show and sort by when each note was created and closed.

= 0.18.1 =
* Security fix: closed a gap where a client review-link holder could read notes they were not meant to see (site-wide dev notes, notes on draft or private pages, and agent-queue notes) by replying to them. Replies from a review link are now limited to the same open, public-page notes the reviewer can already read.
* Fixed CSV export so In Progress notes are included in the "All" export and each row shows its real status (they were dropped and mislabelled before).

= 0.18.0 =
* Security hardening for client review links. A review-link holder can now only read notes on public, published pages (never drafts, private posts, or site-wide dev notes), and their own feedback always attaches to the page they are actually on - it can no longer be aimed at other pages or made site-wide. Agent brief files now use an unguessable filename so they cannot be fetched directly on servers that ignore the folder's access rules. Uninstalling the plugin now also removes every stored option, including the Hub connect token and review-link secrets.

= 0.17.0 =
* New "Site-wide" reporting level: a note can now apply to every page of the site, not just this page or this template. Choose "Site-wide (every page)" from the Reporting level dropdown - the note then shows on the front-end panel everywhere and carries a Site-wide badge in the repository. Free, core feature.

= 0.16.0 =
* Edit a note's text and type right in the repository table (Tools > Red Pen): an Edit button on each row opens an inline panel, so you no longer have to open the note on the front end to fix a typo or change its type. (Priority and assignee were already editable in the table.)

= 0.15.0 =
* Jump to the next open note: a new admin-bar item - and the J key - cycles through the open notes pinned on the current page, scrolling each into view and highlighting it, so you can walk a page's flags without opening the panel.
* The "No open notes on this page" message now stands out with a red background.

= 0.14.0 =
* Draw on your screenshots: after you snip a screenshot, a markup window opens with freehand pen, arrow, and rectangle tools (plus undo) so you can circle and point at exactly what you mean. The marks are saved into the image. Click an existing screenshot thumbnail to reopen markup and add more. Free, core feature.

= 0.13.0 =
* Client Reviewer Link: share a link that lets a client or outside reviewer leave notes on the front end without a WordPress login. Their feedback comes in attributed and scoped - they only see open notes on the page they are viewing, and none of the dev-only controls.

= 0.12.1 =
* Fixed: connecting to Red Pen Hub silently failed when the Hub URL was entered without http:// or https://. Schemeless URLs are now handled.

= 0.12.0 =
* Connect to Red Pen Hub: point your site at a local Red Pen Hub (URL + token under Display settings) and your notes push to the shared cross-project board automatically whenever they change. Outbound only, no external service - your site stays the source of truth. (Red Pen Hub is a separate, optional app.)

= 0.11.0 =
* Custom note types: define your own note types in Tools > Red Pen > Display settings (one "Label" or "Label|#hexcolor" per line). They appear in every note-type dropdown alongside the built-ins (Note / Suggested edit / Bug / Question) and render their colour as the note's flag on the front end and in the repository. Free, core feature.

= 0.10.8 =
* Dark mode fixes: the front-end Open / In Progress / Resolved tab bar, the per-note status dropdown, and the More/screenshot controls now darken too (they had stayed light). In the dashboard repository, the Display settings box (and its text, labels, and dividers) is now properly dark and legible.

= 0.10.7 =
* Dark mode now also applies to the notes repository page in the WordPress dashboard (Tools > Red Pen), not just the front-end panel. The same Display-settings toggle controls both; the styling is scoped to Red Pen's own page and does not touch the rest of wp-admin.

= 0.10.6 =
* Dark mode: a new toggle in Tools > Red Pen > Display settings switches the front-end notes panel to a dark theme. Off by default; the red accent is unchanged.

= 0.10.5 =
* Fixed: the custom pin colour had no effect on the front end, and pins used a brighter red than the app accent. The numbered element pins live in a layer attached to the page body, outside the widget root where the colour variable was being set, so it never reached them. The colour is now applied to the pin layer directly. Pins default to the app red (#D32F2F) and honour a custom colour when one is set.

= 0.10.4 =
* Agent consumption layer: an agent can now pull its own queue two ways, both local with no keys. (1) Live REST - GET the notes endpoint with `?agent=<slug>` returns every open/in-progress note delegated to that agent, site-wide, read with the caller's own credentials. (2) JSON brief - a plain-data file (note bodies, code scope, where each lives, replies) is written to the deny-protected uploads folder and kept current automatically as agent notes change; a local agent reads it straight off disk. The repository's per-agent "Audience" view now shows both paths.

= 0.10.3 =
* Added a "?" help tooltip next to Agent feedback in the settings, explaining how to turn it on and delegate notes to an AI agent.

= 0.10.2 =
* Agents now appear directly in the "Assigned" dropdown (under an "Agents" group), in the panel and in the repository table - so you can assign any note, including ones created earlier, to an agent the same way you assign it to a person. Assigning to an agent clears the human assignee and vice-versa. (The separate "Assign to agent" control is gone, replaced by this.) The optional code-scope field remains.

= 0.10.1 =
* Fixed: saving the Display settings (agent platforms, pin colour, where Red Pen appears) returned "Invalid request" - the form's security token was sent in the wrong place. Settings now save correctly.

= 0.10.0 =
* Agent Feedback (new): leave notes specifically for an AI coding agent. Enable the platforms you use (Claude, Codex, Cursor, GitHub Copilot, Gemini, LM Studio, or a custom name) under Tools > Red Pen > Display settings, then assign a note to an agent from the floating panel - with an optional "code scope" hint (e.g. a file path). Agent-targeted notes are kept in a separate "For agents" queue (filterable per agent in the repository) and stay out of the human worklist, so you can point a specific scope at a specific agent. The agent works on them like any note (open -> in progress -> resolved). Everything stays local - no AI keys, no external calls. (Consumption via a JSON brief + a REST filter lands next, in 0.10.1.)

= 0.9.2 =
* You can now pick a custom colour for the numbered element pins (Tools > Red Pen > Display settings). The pins default to the app red; choose a colour that stands out if red blends into a particular site. (In-progress pins stay amber so you can still tell statuses apart.)

= 0.9.1 =
* The notes repository (Tools > Red Pen) gained bulk actions: tick notes and Reopen / Mark In Progress / Resolve / Delete them all at once.
* Assignee and Priority can now be changed right in the repository table from a dropdown, no need to open each note.

= 0.9.0 =
* New three-state workflow: notes can now be Open, In Progress, or Resolved. The floating panel has an Open / In Progress / Resolved tab set, and each note has a status dropdown to move it between them (with Undo). In-progress notes keep their pin on the page (in amber) so work-in-progress stays visible.
* The notes repository (Tools > Red Pen) gained an In Progress filter and a Start / Resolve / Reopen action per note, and shows each note's status.

= 0.8.0 =
Performance (from the round-2 audit) - lighter on every editor page load.
* Dev Mode pages no longer fetch the whole notes list just to show the button badge and place pins. That data is now embedded in the page, and the full list (with replies and screenshots) loads only when you actually open the panel.
* Loading the panel now pulls all replies in a single query instead of one query per note, and warms the author cache - much fewer database queries on busy pages.
* The open-note count (admin bar) and the assignable-users list are cached briefly and refreshed on change, instead of running a query on every page.
* Element pins reuse their resolved page element while scrolling instead of re-finding it every frame.
* Two internal settings no longer load on every request.

= 0.7.7 =
Security + correctness hardening (from the round-2 audit).
* Note and reply text is now sanitized with a fixed safe-tag list instead of the default filter, so even an administrator cannot plant script that would run in another reviewer's browser when they open the panel or repository.
* Screenshots are no longer served from a public URL. They now stream through a permission-checked reader and the folder is locked down, so captures of private or draft pages can't be opened by anyone without access.
* Replies can no longer be accidentally flipped to resolved/open (only top-level notes have a status).
* Permanently deleting a note, and editing someone else's note, now require higher permissions; anyone can still add, resolve, and reply.
* CSV export hardening: the spreadsheet-formula guard now catches leading-space tricks and covers the URL column too.
* The notes panel ignores a stale server response that arrives out of order, so a slow request can't overwrite newer state.

= 0.7.6 =
* The Tools > Red Pen menu item now has a red pen icon (like Smooth Moves), and the pen icon in the admin bar and on the repository page is now the brand accent red.

= 0.7.5 =
* Red Pen now lives under the Tools menu instead of taking its own top-level spot in the admin sidebar, keeping the main dashboard menu uncluttered. (Same page, same permissions - just Tools > Red Pen.)

= 0.7.4 =
* Resolving, reopening, replying to, adding, and editing a note now update just that one note in the panel instead of reloading the whole list. A half-typed reply in another note is no longer wiped, and the panel no longer jumps back to the top after an action.

= 0.7.3 =
* The notes panel now has a button (top-right) that opens the full notes repository in the WordPress admin.
* Your report settings - type, priority, assignee, and reporting level - are remembered between page loads, so filing several similar notes (same type, same assignee) no longer means re-picking every time.
* The numbered pin markers now use the app's bright red so they stand out more clearly on the page.

= 0.7.2 =
* The notes panel now has Open and Resolved tabs (with live counts). Resolving a note moves it to the Resolved tab; the button there reads Reopen and moves it back.
* Resolving or reopening shows a brief "Undo" toast so a misclick is one click to fix.
* The add-note form is lighter: priority, assignee, and reporting level are tucked behind a "More" button, so jotting a quick note is just pick-a-type and type. (Editing a note expands them automatically.)
* Reply boxes are taller and grow as you type.
* If the screenshot tool fails to load, the button now says so instead of doing nothing.

= 0.7.1 =
* Resolved notes now drop off the active list in the floating panel (it shows open notes only), and their numbered pin marker no longer appears on the page. Resolved notes stay available - and reopenable - on the Resolved / All tabs of the admin Red Pen repository.

= 0.7.0 =
* Accessibility pass. The notes panel is now a labelled dialog with focus management: opening it moves focus inside, Tab and Shift+Tab stay within the panel, and closing it (button or Escape) returns focus to where you were.
* Error and notice messages are announced to screen readers (the toast is now a live alert region).
* The notes list announces when it is loading and when it updates, so screen-reader users hear new content arrive.
* The floating button's label now reflects how many notes are open (for example "Red Pen notes, 3 open").
* Element pins now have descriptive labels (note number, type, and a snippet) instead of an unlabelled number.
* The resize handle is keyboard-operable: focus it and use the arrow keys (Shift for larger steps, Home/End for min/max) to set the panel width.
* Respects the operating-system "reduce motion" setting - the note flash, button transitions, and smooth scrolling are turned off when you ask for less motion.
* The screenshot capture and element-pin tools now work with touch (drag to select or highlight, lift to confirm).

= 0.6.2 =
* Security: the front-end config blob is now escaped on output, closing a stored-XSS path where a crafted post title could break out of the widget's data attribute and run script in an editor or admin's browser.
* Deleting a note now removes all of its replies even past the 200 most recent, so no orphaned replies are left behind.

= 0.6.1 =
* Notes pinned to an element now have a magnifying-glass button (left of Resolve) that scrolls to the element and draws a red, padded highlight box around it.

= 0.6.0 =
* Resizable panel: drag the left edge of the notes panel to make it wider, and the custom width is remembered (per browser) across pages and visits.
* The panel is a little wider by default (420px).

= 0.5.3 =
* Performance: the repository page now counts each note's replies with a single grouped query instead of one query per row.
* Tested up to WordPress 7.0.

= 0.5.2 =
* Delete a note from the repository: a Delete button on each row permanently removes the note along with its replies and screenshot (with a confirmation prompt). Previously notes could only be resolved, never removed.

= 0.5.1 =
* Failed saves no longer fail silently: adding, editing, replying to, or resolving a note now shows a brief error toast if the request does not go through, so a dropped note is never mistaken for a saved one.
* Press Esc to close the open notes panel.
* Hardened CSV export against spreadsheet formula injection (cells starting with = + - @ are now exported as plain text).

= 0.5.0 =
* Red Pen now works on archives, taxonomy pages, the home/front page, search, and 404 - not just single posts and pages.
* New "reporting level" choice on every note: report it against This page (the exact post or archive you're on) or This template (the view type, so it covers every page rendered the same way). A page shows its own notes plus the template notes that apply to it.
* New "Where Red Pen appears" setting on the Red Pen admin page: choose which kinds of views show the floating button (defaults to everywhere).
* Repository gains a Where column and a Page/Template badge; CSV export gains Level and Where columns. Existing notes are migrated automatically.

= 0.4.0 =
* Edit a note after adding it: an Edit button on each note loads it back into the form so you can change the text, type, priority, and assignee, swap or remove the screenshot, and re-pin, move, or clear the attached element. Cancel backs out without saving.

= 0.3.0 =
* Element-level pinning: a "Pin to element" picker attaches a note to a specific element on the page; anchored notes show as numbered markers you can click to jump straight to the note.
* Threaded replies: reply to any note from the front-end panel, building a discussion under each one.
* Priority + assignment: tag notes Low / Normal / High and assign them to any editor or admin; the repository gains Priority and Assigned columns plus an "Assigned to me" filter.
* CSV export: download the repository (honouring the current filters) as a CSV - no external service.
* Auto-context: each note records the browser, OS, and viewport it was created in.
* Fix: the floating toggle button could appear unresponsive because a stronger CSS rule overrode the panel's hidden state; the panel now toggles reliably.

= 0.2.0 =
* Screenshots: click Screenshot, drag a box around any area, and the region is captured client-side (html2canvas) and saved as a small WebP attached to the note. Shown on the note, the edit-screen panel, and the repository; cleaned up on note delete and uninstall.
* Red accent: the chrome now uses the editorial red-pen colour.

= 0.1.0 =
* Initial release: floating note button, typed notes, per-post meta box, shared repository page with status filtering, admin-bar Dev Mode toggle and open-note badge, clean uninstall.
