# WP Red Pen

A logged-in review layer for WordPress. Editors and admins flip on **Dev Mode** and drop notes, flags, and suggested edits on any post or page from a floating button. Every note collects on the post's edit screen and in a single shared to-do repository.

Part of the [Tracy Digital Media](https://tracydigitalmedia.com/) free dev-plugin family. Free, with an optional [tip jar](https://buymeacoffee.com/lincolntracy) - no license keys, no premium tier, no third-party services.

> "Red Pen" is the editorial metaphor only. The UI follows the locked TDM palette (deep blue / cyan), not red.

## Why

Reviewing a site usually means a messy spreadsheet, a thread of "the about page has a typo," or comments scattered across email. WP Red Pen puts the note where you found the problem - on the page itself - and collects every note in one place your whole team can see.

Good for editorial review, client hand-offs, content audits, QA passes, and shared to-do lists.

## What it does

- **Floating note button** on the front end, shown only to logged-in users who can edit posts. Per-user **Dev Mode** toggle in the admin bar.
- **Typed notes** - note, suggested edit, bug/problem, or question - attached to the current post/page.
- **Per-post meta box** on the edit screen listing that page's notes.
- **Shared repository** admin page: every note across the site, filterable by Open / Resolved / All, with one-click resolve/reopen.
- **Open-note badge** on the admin bar and the floating button.
- **Clean uninstall** - deleting the plugin removes every note and preference it created and nothing else.

## Architecture

Single self-contained `wp-red-pen.php` (inline CSS/JS, no build step), in keeping with the TDM family's containment approach.

- **Storage:** a private `wprp_note` custom post type. Note body is the post content; author and date are native; status is a custom post status (`wprp_open` / `wprp_resolved`); the target post id and note type are post meta. No custom tables.
- **REST namespace `wprp/v1`:** `GET/POST /notes`, `POST /notes/{id}/status`, gated by an `edit_posts` capability check and the `wp_rest` nonce. The front-end button is the only REST client.
- **Capability:** everything is gated on `edit_posts` (editors + admins). Logged-out visitors never receive the assets.

## Install

1. Copy the `wp-red-pen` folder to `wp-content/plugins/` (or upload the zip via Plugins -> Add New -> Upload).
2. Activate.
3. Click **Red Pen: Off** in the admin bar to turn Dev Mode on for your account.
4. Open any post/page on the front end and use the floating button (bottom right).
5. Review everything under the **Red Pen** dashboard menu.

## Roadmap

- v1.1 candidates: element-level click-to-pin (attach a note to a specific element), threaded replies, note assignment, CSV export of the repository.
- Deliberately out of scope (the free-tier wedge): email/notification alerts, external SaaS sync, license servers, per-seat billing.

## License

GPLv2 or later. Author: Lincoln Tracy / Tracy Digital Media.
