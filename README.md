# WP Red Pen

A logged-in review layer for WordPress. Editors and admins flip on **Dev Mode** and drop notes, flags, and suggested edits on any post or page from a floating button. Every note collects on the post's edit screen and in a single shared to-do repository.

Part of the [Tracy Digital Media](https://tracydigitalmedia.com/) free dev-plugin family. Free, with an optional [tip jar](https://buymeacoffee.com/lincolntracy) - no license keys, no premium tier, no third-party services.

> WP Red Pen is the one deliberate exception to the TDM "no warm/red" palette rule: the editorial red-pen branding gets a red accent (#D32F2F) on otherwise TDM-neutral (near-black / gray / white) chrome.

## Why

Reviewing a site usually means a messy spreadsheet, a thread of "the about page has a typo," or comments scattered across email. WP Red Pen puts the note where you found the problem - on the page itself - and collects every note in one place your whole team can see.

Good for editorial review, client hand-offs, content audits, QA passes, and shared to-do lists.

## What it does

- **Floating note button** on the front end, shown only to logged-in users who can edit posts. Per-user **Dev Mode** toggle in the admin bar. Works on singular posts/pages **and** archives, taxonomy pages, the home/front page, search, and 404 - controlled by a global **"Where Red Pen appears"** setting (defaults to everywhere).
- **Reporting level** per note: target **This page** (the exact post or archive you're on) or **This template** (the view type, so the note covers every page rendered the same way). A page shows its own notes plus the template notes that apply to it.
- **Snip a screenshot** - click Screenshot, drag a box around the area, done. The region is captured client-side (vendored html2canvas) and stored as a small WebP in `uploads/wp-red-pen/`, attached to the note.
- **Typed notes** - note, suggested edit, bug/problem, or question - attached to the current post/page.
- **Edit any note after adding it** - change the text, type, priority, or assignee; swap, remove, or add a screenshot; re-pin, move, or clear the attached element.
- **Element-level pinning** - a "Pin to element" picker attaches a note to a specific element; anchored notes show as numbered markers on the page that jump to the note when clicked.
- **Threaded replies** - reply to any note from the panel to build a discussion under it.
- **Priority + assignment** - tag notes Low / Normal / High and assign them to any editor/admin; the repository adds Priority and Assigned columns and an "Assigned to me" filter.
- **CSV export** of the repository (honouring the active filters) - local, no external service.
- **Auto-context** - each note records the browser, OS, and viewport it was created in.
- **Per-post meta box** on the edit screen listing that page's notes.
- **Shared repository** admin page: every note across the site, filterable by Open / Resolved / All, with one-click resolve/reopen.
- **Open-note badge** on the admin bar and the floating button.
- **Clean uninstall** - deleting the plugin removes every note and preference it created and nothing else.

## Architecture

Single self-contained `wp-red-pen.php` (inline CSS/JS, no build step), in keeping with the TDM family's containment approach.

- **Storage:** a private `wprp_note` custom post type. Note body is the post content; author and date are native; status is a custom post status (`wprp_open` / `wprp_resolved`); the target post id and note type are post meta. No custom tables.
- **REST namespace `wprp/v1`:** `GET/POST /notes`, `POST /notes/{id}` (edit), `POST /notes/{id}/status`, `POST /notes/{id}/replies`, gated by an `edit_posts` capability check and the `wp_rest` nonce. The front-end button is the only REST client.
- **Replies** are child `wprp_note` posts (`post_parent` = the note id); priority, assignee, context, and the element anchor are all post meta. Still no custom tables.
- **Targeting context:** each note stores a `_wprp_ctx_key` (e.g. `post:12`, `pt_archive:composer`, `term:genre:5`, `tpl:single-track`, `search`, `404`), `_wprp_ctx_label`, and `_wprp_level` (page/template). The front end fetches by the current view's page + template keys. A one-time `init` migration backfills legacy notes as `post:` page notes (schema tracked in the `wprp_db_version` option). Visibility is the global `wprp_show_on` option.
- **Capability:** everything is gated on `edit_posts` (editors + admins). Logged-out visitors never receive the assets.

## Install

1. Copy the `wp-red-pen` folder to `wp-content/plugins/` (or upload the zip via Plugins -> Add New -> Upload).
2. Activate.
3. Click **Red Pen: Off** in the admin bar to turn Dev Mode on for your account.
4. Open any post/page on the front end and use the floating button (bottom right).
5. Review everything under the **Red Pen** dashboard menu.

## Roadmap

- Shipped in 0.3.0: element-level click-to-pin, threaded replies, priority + assignment, CSV export, auto-context.
- Next candidates: edit/reassign from the repository table too (the front-end panel now has full editing; the repo is still view + resolve), drawing/markup over a screenshot, an "In Progress" status between Open and Resolved, a "jump to next open note" helper.
- Deliberately out of scope (the free-tier wedge): email/notification alerts, external SaaS sync, push to Jira/Slack/Trello, real-time collaboration, license servers, per-seat billing.

## License

GPLv2 or later. Author: Lincoln Tracy / Tracy Digital Media.
