=== WP Red Pen ===
Contributors: ltracy
Donate link: https://buymeacoffee.com/lincolntracy
Tags: editorial, review, notes, annotations, workflow
Requires at least: 5.5
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A logged-in review layer. Flag posts and pages with notes and suggested edits from a floating button, then track them all in one shared repository.

== Description ==

WP Red Pen turns any WordPress site into a markup surface for the people who run it. Switch on Dev Mode and a floating button appears on the front end of every post and page. Click it to leave a note, flag a problem, or suggest an edit - right where you spotted it.

Every note is stored in WordPress and shows up in two places: a panel on that post's edit screen, and a single repository page that all editors and admins share, so nothing falls through the cracks.

Built for editorial review, client hand-offs, content audits, and team to-do lists - without a single third-party service.

= What it does =

* **Floating note button** on the front end for logged-in editors and admins (Dev Mode toggle in the admin bar, per user)
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

= 0.1.0 =
* Initial release: floating note button, typed notes, per-post meta box, shared repository page with status filtering, admin-bar Dev Mode toggle and open-note badge, clean uninstall.
