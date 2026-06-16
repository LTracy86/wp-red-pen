=== WP Red Pen ===
Contributors: ltracy
Donate link: https://buymeacoffee.com/lincolntracy
Tags: editorial, review, notes, annotations, workflow
Requires at least: 5.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.9.2
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

WP Red Pen is free, with an optional tip jar. It deliberately does NOT bolt on the things competitors paywall - no email alerts, no external SaaS sync, no license keys, no per-seat fees. Your notes live in your database and nowhere else.

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
