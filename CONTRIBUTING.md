# Contributing to WP Red Pen

Thanks for looking. Bug reports, reproductions and pull requests are all welcome.
Red Pen is free and stays free, so contributions are the only currency here.

## The one hard rule: containment

**The plugin is one file.** All of the plugin's PHP, CSS and JavaScript live in
`wp-red-pen.php`. There is no build step, no bundler, no `src/` directory, and no
sibling include files. CSS is printed in a `<style id="wprp-css">` block and the
JavaScript in a `<script id="wprp-js">` block, both inside
`wprp_print_frontend_assets()`.

This is deliberate. A single file means a Red Pen install can be dropped onto any
site, read end to end, diffed, and deleted, with nothing hiding elsewhere. A pull
request that splits the plugin into modules will be declined however clean it is.

The only files that ship alongside it are `uninstall.php`, `readme.txt`,
`assets/vendor/html2canvas.min.js` (vendored, MIT), and `docs/`.

## Getting set up

1. Clone the repo into `wp-content/plugins/wp-red-pen` on a local WordPress
   install (XAMPP, Local, wp-env, whatever you use).
2. Activate the plugin.
3. Click **Red Pen: Off** in the admin bar to turn Dev Mode on for your account.
4. The repository lives at **Tools > Red Pen**.

Minimum supported runtime is **PHP 7.4** and **WordPress 5.5**. Do not use syntax
or APIs newer than that: no arrow-function-only refactors that need 7.4+ features
we do not already use, no `match`, no named arguments, no enums. The front-end
JavaScript is plain ES5-compatible script with no transpiler, so no arrow
functions, template literals, `const`/`let`, or optional chaining in the inline
`<script>` block.

## Before you open a pull request

- **Lint it.** `php -l wp-red-pen.php` must pass.
- **Static analysis.** `composer install` then `composer analyze` runs Psalm
  (config in `psalm.xml`, error level 4, pinned to PHP 7.4). Do not add new
  findings.
- **Escape everything on output** and sanitize everything on input. The file
  follows WordPress coding standards; match the surrounding style, including the
  tab indentation and Yoda conditions.
- **Test the reviewer boundary if you touched it.** See below.
- **Update the docs you changed.** Anything user-visible needs an entry in
  `CHANGELOG.md` under `## [Unreleased]`, and usually a line in `readme.txt`.
- **One logical change per commit**, present tense, no emoji. Example:
  `Fix pin coordinates when the page is zoomed`.

## Touching the client reviewer link

This is the part of the plugin where a mistake leaks a client's private notes, so
it gets extra care.

A **reviewer** is an anonymous holder of an unguessable bearer token. Every read
they make must pass through the single gate, `wprp_reviewer_can_see_note()`. If
you add a code path that returns note data to a reviewer, it goes through that
function too - do not re-derive the rule, and do not scope it with a query alone.

The gate fails closed. A note reaches a reviewer only when all of these hold:

- it is a top-level note, not a reply post
- it is not aimed at an agent
- its context key survives `wprp_reviewer_safe_keys()` (public published pages and
  public archive templates only, never the site-wide key)
- and either it carries the explicit `_wprp_client_visible` flag while open, or it
  was filed through the same reviewer link token (`_wprp_review_token`)

If you change any of that, prove the boundary rather than reasoning about it.
Create an internal note and a shared note, then confirm a reviewer token reads
exactly one of them through every path: the scoped `GET /notes`, the priming
payload in the page config, the create echo, and the reply endpoint.

## Scope

Red Pen stays local, private and simple. Deliberately out of scope, and not worth
opening a PR for: email or push notification alerts, external SaaS sync, Jira /
Slack / Trello integrations, real-time collaboration, licence servers, per-seat
billing, and anything that phones home. Those are what competitors charge for;
leaving them out is the point.

## Reporting a security issue

Do not open a public issue for a vulnerability, particularly anything that lets a
reviewer link read notes it should not. Email lincolnmtracy@gmail.com with the
details and a reproduction, and give it a reasonable window before disclosure.

## Licence

WP Red Pen is GPLv2 or later. By contributing you agree your work ships under the
same licence.
