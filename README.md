# WP Red Pen

A logged-in review layer for WordPress. Editors and admins flip on **Dev Mode** and drop typed notes - ideas, problems, questions - on any post or page from a floating button. Every note collects on the post's edit screen and in a single shared to-do repository under Tools > Red Pen.

Part of the [Tracy Digital Media](https://tracydigitalmedia.com/) free dev-plugin family. Free and open, every feature included - white-label client reports and the multi-site Hub board too. No account, no subscription, no third-party services, no phone-home.

> WP Red Pen is the one deliberate exception to the TDM "no warm/red" palette rule: the editorial red-pen branding gets a red accent (#D32F2F) on otherwise TDM-neutral (near-black / gray / white) chrome.

## Why

Reviewing a site usually means a messy spreadsheet, a thread of "the about page has a typo," or comments scattered across email. WP Red Pen puts the note where you found the problem - on the page itself - and collects every note in one place your whole team can see.

Good for editorial review, client hand-offs, content audits, QA passes, and shared to-do lists.

## What it does

- **Floating note button** on the front end, shown only to logged-in users who can edit posts. Per-user **Dev Mode** toggle in the admin bar. Works on singular posts/pages **and** archives, taxonomy pages, the home/front page, search, and 404 - controlled by a global **"Where Red Pen appears"** setting (defaults to everywhere).
- **Reporting level** per note: target **This page** (the exact post or archive you're on), **This template** (the view type, so the note covers every page rendered the same way), or **Site-wide** (every page of the site). A page shows its own notes plus the template and site-wide notes that apply to it.
- **Snip a screenshot** - click Screenshot, drag a box around the area, done. The region is captured client-side (vendored html2canvas) and stored as a small WebP in `uploads/wp-red-pen/`, attached to the note. A markup editor (freehand pen, arrow, rectangle, undo) opens after the snip so you can circle exactly what you mean.
- **Typed notes** - Note, Idea, Problem, or Question - plus your own custom note types with their own colours, defined in Display settings.
- **Edit any note after adding it** - change the text, type, priority, or assignee; swap, remove, or add a screenshot; re-pin, move, or clear the attached element. Text and type are also editable inline from the repository table.
- **Element-level pinning** - a "Pin to element" picker attaches a note to a specific element; anchored notes show as numbered markers on the page that jump to the note when clicked, and a Locate button scrolls to and highlights the pinned element.
- **Three-state status** - every note is Open, In Progress, or Resolved; the panel has a tab per status and each note has a status dropdown (with Undo).
- **Threaded replies** - reply to any note from the panel to build a discussion under it.
- **Priority, severity + assignment** - tag notes Low / Normal / High, optionally rate impact (Blocker to Trivial), and assign them to any editor/admin; the repository adds Priority and Assigned columns and an "Assigned to me" filter.
- **Client reviewer links** - generate a private, token-gated link and a client can leave notes on the live site with no WordPress login. Revocable, optionally expiring, enforced server-side.
- **White-label client reports** - one click turns the notes into a printable client report; set your own title, logo, and accent colour and hide the Red Pen credit. Free, like everything else.
- **Red Pen Hub connection** - optionally push (or let the Hub pull) your notes onto a local cross-project board. Outbound-only, no external service; the Hub is a separate free app.
- **CSV export** of the repository (honouring the active filters) - local, no external service.
- **Auto-context** - each note records the browser, OS, and viewport it was created in.
- **Per-post meta box** on the edit screen listing that page's notes.
- **Shared repository** under **Tools > Red Pen**: every note across the site, filterable by Open / In Progress / Resolved / All, with per-note status actions, inline quick-edits, bulk actions (reopen / mark in progress / resolve / delete), and delete.
- **Jump to next open note** - an admin-bar item (and the J key) cycles through the open notes pinned on the current page.
- **Dark mode** - a Display-settings toggle darkens the front-end panel and the repository page.
- **Open-note badge** on the admin bar and the floating button.
- **Clean uninstall** - deleting the plugin removes every note, screenshot, option, and preference it created and nothing else.

## Architecture

Single self-contained `wp-red-pen.php` (inline CSS/JS, no build step), in keeping with the TDM family's containment approach.

- **Storage:** a private `wprp_note` custom post type. Note body is the post content; author and date are native; status is a custom post status (`wprp_open` / `wprp_progress` / `wprp_resolved`); the target post id and note type are post meta. No custom tables.
- **REST namespace `wprp/v1`:** `GET/POST /notes`, `POST /notes/{id}` (edit), `POST /notes/{id}/status`, `POST /notes/{id}/replies`, gated by an `edit_posts` capability check and the `wp_rest` nonce. The front-end panel is the main REST client; a connected Red Pen Hub can also pull notes over the same namespace.
- **Replies** are child `wprp_note` posts (`post_parent` = the note id); priority, assignee, context, and the element anchor are all post meta. Still no custom tables.
- **Targeting context:** each note stores a `_wprp_ctx_key` (e.g. `post:12`, `pt_archive:composer`, `term:genre:5`, `tpl:single-track`, `search`, `404`), `_wprp_ctx_label`, and `_wprp_level` (page/template). The front end fetches by the current view's page + template keys. A one-time `init` migration backfills legacy notes as `post:` page notes (schema tracked in the `wprp_db_version` option). Visibility is the global `wprp_show_on` option.
- **Capability:** everything is gated on `edit_posts` (editors + admins). Logged-out visitors never receive the assets.

## Install

1. Copy the `wp-red-pen` folder to `wp-content/plugins/` (or upload the zip via Plugins -> Add New -> Upload).
2. Activate.
3. Click **Red Pen: Off** in the admin bar to turn Dev Mode on for your account.
4. Open any post/page on the front end and use the floating button (bottom right).
5. Review everything under **Tools > Red Pen**.

## Scope

Everything above ships in the current release, free. Deliberately out of scope: email/notification alerts, external SaaS sync, push to Jira/Slack/Trello, real-time collaboration, license servers, per-seat billing. Those are the things competitors paywall; leaving them out keeps Red Pen local, private, and simple.

## Support

Red Pen is free and open, every feature, no gate. If it saves you time, you can back the next release:

- Buy Me a Coffee: https://www.buymeacoffee.com/lincolntracy
- GitHub Sponsors: https://github.com/sponsors/LTracy86

Supporters are listed at https://redpen.tools/supporters.html.

## License

GPLv2 or later. Author: Lincoln Tracy / Tracy Digital Media LLC.
