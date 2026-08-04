<?php
/**
 * Plugin Name:       WP Red Pen
 * Plugin URI:        https://tracydigitalmedia.com/wp-red-pen/
 * Description:       A logged-in review layer. Editors and admins flip on Dev Mode and drop notes, flags, and suggested edits on any post or page from a floating button. Notes collect on the post's edit screen and in a shared to-do repository.
 * Version:           0.25.5
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            Lincoln Tracy
 * Author URI:        https://tracydigitalmedia.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wp-red-pen
 * Domain Path:       /languages
 *
 * DEPLOY:  Copy this folder to wp-content/plugins/ on any WordPress install and activate.
 *
 * Note: WP Red Pen is the one deliberate exception to the TDM "no warm/red"
 * palette rule - the editorial red-pen branding gets a red accent (#D32F2F) on
 * an otherwise TDM-neutral (near-black / gray / white) chrome.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPRP_VERSION',     '0.25.5' );
define( 'WPRP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WPRP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPRP_CPT',         'wprp_note' );      // private note CPT
define( 'WPRP_STATUS_OPEN', 'wprp_open' );      // custom post statuses
define( 'WPRP_STATUS_PROGRESS', 'wprp_progress' ); // 3-state workflow: open -> in progress -> resolved
define( 'WPRP_STATUS_DONE', 'wprp_resolved' );
define( 'WPRP_CAP',         'edit_posts' );     // who may use Red Pen
define( 'WPRP_USERMETA',    'wprp_devmode' );   // per-user Dev Mode toggle
define( 'WPRP_META_TARGET', '_wprp_target' );   // attached post/page id
define( 'WPRP_META_TYPE',   '_wprp_type' );     // note | suggestion | bug | question
define( 'WPRP_META_URL',    '_wprp_url' );      // context url where it was added
define( 'WPRP_META_SHOT',   '_wprp_shot' );     // attached screenshot filename (in uploads/wp-red-pen)
define( 'WPRP_META_CTX',    '_wprp_ctx' );      // browser/OS/viewport string captured at creation
define( 'WPRP_META_PRIORITY', '_wprp_priority' ); // low | normal | high
define( 'WPRP_META_SEVERITY', '_wprp_severity' ); // blocker|critical|major|minor|trivial ('' = unset); impact axis, distinct from priority
define( 'WPRP_META_ASSIGNEE', '_wprp_assignee' ); // assigned user id (0 = unassigned)
define( 'WPRP_META_ANCHOR', '_wprp_anchor' );   // element-pin anchor (JSON: selector + relative x/y)
define( 'WPRP_META_CTXKEY', '_wprp_ctx_key' );  // context key: post:ID | term:tax:ID | pt_archive:slug | tpl:* | home | search | 404 ...
define( 'WPRP_META_CTXLABEL', '_wprp_ctx_label' ); // human label for the context
define( 'WPRP_META_LEVEL',  '_wprp_level' );    // page | template | global
define( 'WPRP_META_RESOLVED_AT', '_wprp_resolved_at' ); // ISO 8601 timestamp stamped when a note is resolved (cleared on reopen)
define( 'WPRP_META_RESOLVED_BY', '_wprp_resolved_by' ); // user id who resolved the note (cleared on reopen)
define( 'WPRP_GLOBAL_KEY',  'site' );           // reserved ctx key for site-wide (every view) notes
define( 'WPRP_SHOT_DIR',    'wp-red-pen' );     // uploads subfolder for screenshots
define( 'WPRP_REST_NS',     'wprp/v1' );
define( 'WPRP_DBVER_OPT',   'wprp_db_version' ); // schema version (for one-time data migrations)
define( 'WPRP_SHOW_OPT',    'wprp_show_on' );    // global: which view scopes show the widget
define( 'WPRP_PINCOLOR_OPT', 'wprp_pin_color' ); // global: custom hex for the numbered element pins ('' = app accent)
define( 'WPRP_DARK_OPT',     'wprp_dark' );       // global: dark mode for the front-end panel ('' / 1)
define( 'WPRP_CUSTOM_TYPES_OPT', 'wprp_custom_types' ); // global: user-defined note types ("Label|#hexcolor" per line)
define( 'WPRP_HUB_URL_OPT',   'wprp_hub_url' );    // global: Red Pen Hub URL this site pushes notes to
define( 'WPRP_HUB_TOKEN_OPT', 'wprp_hub_token' );  // global: Red Pen Hub connect token
define( 'WPRP_BRAND_OPT',     'wprp_brand' );       // global (PRO): client-report branding (array: title, logo, color, hide_credit)
define( 'WPRP_PRO_KEY_OPT',   'wprp_pro_key' );      // global (PRO): the offline-signed license key the buyer pastes in
define( 'WPRP_PRO_PUBKEY',    'wFHUq3h8K8Kw8zKfpN7sjFT49529e8ACkbXJYmnR8h4=' ); // PRO license: Ed25519 public key, base64 of the raw 32 bytes. VERIFY-ONLY - safe to ship in the open; it can check a key, never mint one.
define( 'WPRP_META_AGENT',     '_wprp_agent' );     // agent feedback: target agent slug ('' = a human note)
define( 'WPRP_META_CODESCOPE', '_wprp_codescope' ); // agent feedback: optional code scope / file reference (free text)
define( 'WPRP_AGENTS_OPT',     'wprp_agents' );      // global: enabled agent platform slugs (agent feedback dormant when empty)
define( 'WPRP_AGENT_CUSTOM_OPT', 'wprp_agent_custom' ); // global: one optional custom agent label
define( 'WPRP_REVIEW_TOKENS_OPT', 'wprp_review_tokens' ); // global: client-reviewer link tokens (array of hash-only records)
define( 'WPRP_REVIEW_COOKIE',   'wprp_review' );      // reviewer-mode cookie name (persists reviewer mode across navigation; revalidated every hit)
define( 'WPRP_META_REVIEWER',   '_wprp_reviewer' );   // note meta: the reviewer's typed name/identity for attribution (defined now, consumed in a later phase)
define( 'WPRP_META_VIA_REVIEW', '_wprp_via_review' ); // note meta: 1 = note created through reviewer mode (defined now, consumed in a later phase)
define( 'WPRP_META_CLIENT_VISIBLE', '_wprp_client_visible' ); // note meta: 1 = the author marked this note visible to client reviewers. ABSENT MEANS INTERNAL - the gate fails closed, so a dev's working notes never reach a reviewer link unless they are opted in explicitly.
define( 'WPRP_META_REVIEW_TOKEN', '_wprp_review_token' );     // note meta: the reviewer-link token record id the note was filed through. The only reliable identity a reviewer has, so it is what scopes "their own notes".
define( 'WPRP_REVIEW_RATE_MAX',    20 );                      // anti-abuse: max reviewer note/reply creates per IP+token per window
define( 'WPRP_REVIEW_RATE_WINDOW', 10 * MINUTE_IN_SECONDS );  // anti-abuse: the rolling window for the reviewer create limiter

/** Workflow statuses: short key -> human label. Single source of truth for the 3-state model. */
function wprp_statuses() {
	return array(
		'open'     => __( 'Open', 'wp-red-pen' ),
		'progress' => __( 'In Progress', 'wp-red-pen' ),
		'resolved' => __( 'Resolved', 'wp-red-pen' ),
	);
}

/** All note post-status constants (for "any status" queries). */
function wprp_all_statuses() {
	return array( WPRP_STATUS_OPEN, WPRP_STATUS_PROGRESS, WPRP_STATUS_DONE );
}

/** Short status key ('open'|'progress'|'resolved') for a full post-status constant. */
function wprp_status_key( $post_status ) {
	if ( WPRP_STATUS_DONE === $post_status ) {
		return 'resolved';
	}
	if ( WPRP_STATUS_PROGRESS === $post_status ) {
		return 'progress';
	}
	return 'open';
}

/** Full post-status constant for a short status key; defaults to open for anything unknown. */
function wprp_status_const( $key ) {
	if ( 'resolved' === $key ) {
		return WPRP_STATUS_DONE;
	}
	if ( 'progress' === $key ) {
		return WPRP_STATUS_PROGRESS;
	}
	return WPRP_STATUS_OPEN;
}

/** Note types -> human labels. The single source of truth for the dropdowns (built-in + custom). */
function wprp_note_types() {
	$types = array(
		'note'       => __( 'Note', 'wp-red-pen' ),
		'suggestion' => __( 'Idea', 'wp-red-pen' ),
		'bug'        => __( 'Problem', 'wp-red-pen' ),
		'question'   => __( 'Question', 'wp-red-pen' ),
	);
	foreach ( wprp_custom_note_types() as $key => $ct ) {
		$types[ $key ] = $ct['label'];
	}
	return $types;
}

/**
 * User-defined custom note types. Stored as one "Label|#hexcolor" per line in
 * WPRP_CUSTOM_TYPES_OPT (the colour is optional). Returns key => [label, color],
 * where key is a stable slug derived from the label, prefixed "ct_" so it can
 * never collide with the built-ins. (Renaming a label yields a new type; existing
 * notes keep their stored key and just show that key as the label.)
 */
function wprp_custom_note_types() {
	$raw = (string) get_option( WPRP_CUSTOM_TYPES_OPT, '' );
	$out = array();
	if ( '' === trim( $raw ) ) {
		return $out;
	}
	foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
		$line = trim( $line );
		if ( '' === $line ) {
			continue;
		}
		$parts = explode( '|', $line, 2 );
		$label = trim( $parts[0] );
		$slug  = sanitize_title( $label );
		if ( '' === $label || '' === $slug ) {
			continue;
		}
		$color = isset( $parts[1] ) ? sanitize_hex_color( trim( $parts[1] ) ) : '';
		$out[ 'ct_' . $slug ] = array( 'label' => $label, 'color' => $color ? $color : '' );
	}
	return $out;
}

/** Background colour for a note type's flag: a custom type's colour, or '' for the built-ins (which use the app red). */
function wprp_note_type_color( $type ) {
	$custom = wprp_custom_note_types();
	return ( isset( $custom[ $type ] ) && $custom[ $type ]['color'] ) ? $custom[ $type ]['color'] : '';
}

/** Priority keys -> human labels. Single source of truth for the priority dropdown. */
function wprp_priorities() {
	return array(
		'low'    => __( 'Low', 'wp-red-pen' ),
		'normal' => __( 'Normal', 'wp-red-pen' ),
		'high'   => __( 'High', 'wp-red-pen' ),
	);
}

/**
 * Severity keys -> human labels. The impact axis, distinct from priority (scheduling):
 * a low-priority high-severity crash is a real thing. Optional per note - '' means unset,
 * so notes only show a severity when one was deliberately chosen. Single source of truth
 * for the severity dropdown.
 */
function wprp_severities() {
	return array(
		'blocker'  => __( 'Blocker', 'wp-red-pen' ),
		'critical' => __( 'Critical', 'wp-red-pen' ),
		'major'    => __( 'Major', 'wp-red-pen' ),
		'minor'    => __( 'Minor', 'wp-red-pen' ),
		'trivial'  => __( 'Trivial', 'wp-red-pen' ),
	);
}

/**
 * Every feature is free and open. This used to gate the white-label client-report
 * branding behind a license; it now returns true unconditionally so every install
 * gets the full feature set. The 'wprp_is_pro' filter is still applied so a site
 * can override in the unlikely case it needs to. The license helpers below are
 * retained but unreferenced, so any legacy stored key stays inert.
 */
function wprp_is_pro() {
	return (bool) apply_filters( 'wprp_is_pro', true );
}

/**
 * Decode the stored license key and return its info, or false if there is no key
 * or it does not verify. Info is array( 'email' => string ). Verified live off the
 * stored key so there is no separate "unlocked" flag to fall out of sync.
 *
 * @return array{email:string}|false
 */
function wprp_license_info() {
	$key = (string) get_option( WPRP_PRO_KEY_OPT, '' );
	return '' === trim( $key ) ? false : wprp_verify_license( $key );
}

/** URL-safe base64 decode (accepts missing padding). Returns raw bytes or false. */
function wprp_b64url_decode( $s ) {
	$s   = strtr( (string) $s, '-_', '+/' );
	$pad = strlen( $s ) % 4;
	if ( $pad ) {
		$s .= str_repeat( '=', 4 - $pad );
	}
	return base64_decode( $s, true );
}

/**
 * Verify a Red Pen PRO license key against the embedded Ed25519 public key. The key
 * is "base64url(payload).base64url(signature)" where payload is "email|YYYYMMDD".
 * Returns array( 'email' => ... ) on a genuine signature, or false on anything wrong
 * (malformed, tampered, unsigned, or sodium unavailable). No network, no clock.
 *
 * @param string $key The pasted license key.
 * @return array{email:string}|false
 */
function wprp_verify_license( $key ) {
	$key = trim( (string) $key );
	if ( '' === $key || 1 !== substr_count( $key, '.' ) ) {
		return false;
	}
	if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
		return false; // fail closed - WP 5.2+ bundles sodium_compat, so this is belt-and-braces
	}
	list( $p_enc, $s_enc ) = explode( '.', $key, 2 );
	$payload = wprp_b64url_decode( $p_enc );
	$sig     = wprp_b64url_decode( $s_enc );
	$pub     = base64_decode( WPRP_PRO_PUBKEY, true );
	if ( false === $payload || false === $sig || false === $pub
		|| 64 !== strlen( $sig ) || 32 !== strlen( $pub ) ) {
		return false;
	}
	if ( ! sodium_crypto_sign_verify_detached( $sig, $payload, $pub ) ) {
		return false;
	}
	// The licensee is the payload's first field: a buyer email for hand-issued keys,
	// or an opaque license id for pooled/batch keys (storefront auto-delivery). Accept
	// either - sanitize as text, not as an email, so id-form keys validate too.
	$licensee = sanitize_text_field( (string) strtok( $payload, '|' ) );
	return '' !== $licensee ? array( 'email' => $licensee ) : false; // key stays 'email' for cross-surface parity; holds the licensee (email or id)
}

/**
 * Client-report branding (PRO). Saved values with safe defaults; the accent falls
 * back to the app red so a half-filled brand never renders a broken report. These
 * values are only APPLIED when wprp_is_pro() - the free report is always plain.
 *
 * @return array{title:string,logo:string,color:string,hide_credit:bool}
 */
function wprp_report_brand() {
	$b = get_option( WPRP_BRAND_OPT, array() );
	$b = is_array( $b ) ? $b : array();
	return array(
		'title'       => isset( $b['title'] ) ? (string) $b['title'] : '',
		'logo'        => isset( $b['logo'] ) ? (string) $b['logo'] : '',
		'color'       => ( isset( $b['color'] ) && $b['color'] ) ? (string) $b['color'] : '#D32F2F',
		'hide_credit' => ! empty( $b['hide_credit'] ),
	);
}

/** View scopes the widget can be shown on (for the global visibility setting). */
/** Known agent platforms for the Agent Feedback feature: slug => label. */
function wprp_agent_platforms() {
	return array(
		'claude'   => __( 'Claude', 'wp-red-pen' ),
		'codex'    => __( 'Codex', 'wp-red-pen' ),
		'cursor'   => __( 'Cursor', 'wp-red-pen' ),
		'copilot'  => __( 'GitHub Copilot', 'wp-red-pen' ),
		'gemini'   => __( 'Gemini', 'wp-red-pen' ),
		'lmstudio' => __( 'LM Studio', 'wp-red-pen' ),
	);
}

/**
 * Assignable agents: slug => label. The enabled known platforms plus one optional custom
 * agent (slug 'custom'). An empty array means the Agent Feedback feature is dormant.
 */
function wprp_enabled_agents() {
	$enabled = get_option( WPRP_AGENTS_OPT, array() );
	$enabled = is_array( $enabled ) ? $enabled : array();
	$out     = array();
	foreach ( wprp_agent_platforms() as $slug => $label ) {
		if ( in_array( $slug, $enabled, true ) ) {
			$out[ $slug ] = $label;
		}
	}
	$custom = trim( (string) get_option( WPRP_AGENT_CUSTOM_OPT, '' ) );
	if ( '' !== $custom ) {
		$out['custom'] = $custom;
	}
	return $out;
}

/** Human label for an agent slug, or '' if it isn't a currently-enabled agent. */
function wprp_agent_label( $slug ) {
	$agents = wprp_enabled_agents();
	return isset( $agents[ $slug ] ) ? $agents[ $slug ] : '';
}

function wprp_view_scopes() {
	return array(
		'singular' => __( 'Posts &amp; pages (singular)', 'wp-red-pen' ),
		'archive'  => __( 'Archives (post-type, taxonomy, author, date)', 'wp-red-pen' ),
		'home'     => __( 'Home / front page', 'wp-red-pen' ),
		'search'   => __( 'Search results', 'wp-red-pen' ),
		'notfound' => __( '404 (not found)', 'wp-red-pen' ),
	);
}

/** Enabled view scopes (global option). Default: every scope. */
function wprp_show_on() {
	$saved = get_option( WPRP_SHOW_OPT, null );
	if ( ! is_array( $saved ) ) {
		return array_keys( wprp_view_scopes() ); // default on everywhere
	}
	return array_values( array_intersect( $saved, array_keys( wprp_view_scopes() ) ) );
}

/** The scope key for the current front-end view (matches wprp_view_scopes keys). */
function wprp_current_scope() {
	if ( is_404() ) {
		return 'notfound';
	}
	if ( is_search() ) {
		return 'search';
	}
	if ( is_front_page() || is_home() ) {
		return 'home';
	}
	if ( is_singular() ) {
		return 'singular';
	}
	if ( is_archive() ) {
		return 'archive';
	}
	return 'other';
}

/**
 * The targeting context for the current view, at both levels. Returns:
 *   array( 'page' => array(key,label,target), 'template' => array(key,label) )
 * The 'page' level identifies the specific thing on screen (a post, a term, a
 * post-type archive, the search/404/home page); 'template' identifies the view
 * type so a note applies to every page rendered the same way. 'target' is the
 * post id when the page is a singular post (kept for the meta box), else 0.
 */
function wprp_current_context() {
	$page = array(
		'key'    => '',
		'label'  => '',
		'target' => 0,
	);
	$tpl = array(
		'key'   => '',
		'label' => '',
	);

	if ( is_singular() ) {
		$id      = (int) get_queried_object_id();
		$pt      = get_post_type( $id );
		$pt_obj  = $pt ? get_post_type_object( $pt ) : null;
		$pt_name = $pt_obj ? $pt_obj->labels->singular_name : $pt;
		$page    = array(
			'key'    => 'post:' . $id,
			/* translators: %s: post title */
			'label'  => sprintf( __( 'Page: %s', 'wp-red-pen' ), get_the_title( $id ) ),
			'target' => $id,
		);
		$tpl = array(
			'key'   => 'tpl:single-' . $pt,
			/* translators: %s: post type singular name */
			'label' => sprintf( __( 'Template: single %s', 'wp-red-pen' ), $pt_name ),
		);
	} elseif ( is_post_type_archive() ) {
		$ptq     = get_query_var( 'post_type' );
		$pt      = (string) ( is_array( $ptq ) ? reset( $ptq ) : $ptq );
		$pt_obj  = $pt ? get_post_type_object( $pt ) : null;
		$pt_name = $pt_obj ? $pt_obj->labels->name : $pt;
		$page    = array(
			'key'    => 'pt_archive:' . $pt,
			/* translators: %s: post type name */
			'label'  => sprintf( __( 'Archive: %s', 'wp-red-pen' ), $pt_name ),
			'target' => 0,
		);
		$tpl = array(
			'key'   => 'tpl:archive-' . $pt,
			/* translators: %s: post type name */
			'label' => sprintf( __( 'Template: %s archive', 'wp-red-pen' ), $pt_name ),
		);
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( $term && isset( $term->term_id ) ) {
			$page = array(
				'key'    => 'term:' . $term->taxonomy . ':' . (int) $term->term_id,
				/* translators: 1: term name, 2: taxonomy */
				'label'  => sprintf( __( 'Archive: %1$s (%2$s)', 'wp-red-pen' ), $term->name, $term->taxonomy ),
				'target' => 0,
			);
			$tpl = array(
				'key'   => 'tpl:taxonomy-' . $term->taxonomy,
				/* translators: %s: taxonomy */
				'label' => sprintf( __( 'Template: %s archive', 'wp-red-pen' ), $term->taxonomy ),
			);
		}
	} elseif ( is_author() ) {
		$a    = get_queried_object();
		$page = array(
			'key'    => 'author:' . ( $a ? (int) $a->ID : 0 ),
			/* translators: %s: author display name */
			'label'  => sprintf( __( 'Author archive: %s', 'wp-red-pen' ), $a ? $a->display_name : '' ),
			'target' => 0,
		);
		$tpl = array(
			'key'   => 'tpl:author',
			'label' => __( 'Template: author archive', 'wp-red-pen' ),
		);
	} elseif ( is_date() ) {
		$ymd  = get_query_var( 'year' ) . '/' . get_query_var( 'monthnum' ) . '/' . get_query_var( 'day' );
		$page = array(
			'key'    => 'date:' . $ymd,
			'label'  => __( 'Date archive', 'wp-red-pen' ),
			'target' => 0,
		);
		$tpl = array(
			'key'   => 'tpl:date',
			'label' => __( 'Template: date archive', 'wp-red-pen' ),
		);
	} elseif ( is_search() ) {
		$page = array(
			'key'    => 'search',
			'label'  => __( 'Search results', 'wp-red-pen' ),
			'target' => 0,
		);
		$tpl = array(
			'key'   => 'tpl:search',
			'label' => __( 'Template: search results', 'wp-red-pen' ),
		);
	} elseif ( is_404() ) {
		$page = array(
			'key'    => '404',
			'label'  => __( '404 (not found)', 'wp-red-pen' ),
			'target' => 0,
		);
		$tpl = array(
			'key'   => 'tpl:404',
			'label' => __( 'Template: 404', 'wp-red-pen' ),
		);
	} elseif ( is_front_page() || is_home() ) {
		$is_front = is_front_page();
		$page     = array(
			'key'    => $is_front ? 'front' : 'home',
			'label'  => $is_front ? __( 'Front page', 'wp-red-pen' ) : __( 'Blog index', 'wp-red-pen' ),
			'target' => 0,
		);
		$tpl = array(
			'key'   => $is_front ? 'tpl:front' : 'tpl:home',
			'label' => $is_front ? __( 'Template: front page', 'wp-red-pen' ) : __( 'Template: blog index', 'wp-red-pen' ),
		);
	}

	return array(
		'page'     => $page,
		'template' => $tpl,
		// Site-wide level: a fixed, view-independent context so a note can apply to EVERY page.
		'global'   => array(
			'key'   => WPRP_GLOBAL_KEY,
			'label' => __( 'Site-wide (every page)', 'wp-red-pen' ),
		),
	);
}

/**
 * Users who may be assigned a note: everyone whose role carries the Red Pen
 * capability (edit_posts). Returned as id => display_name, capped at 200.
 */
function wprp_assignable_users() {
	$cached = get_transient( 'wprp_assignable_users' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$roles = array();
	foreach ( wp_roles()->roles as $slug => $role ) {
		if ( ! empty( $role['capabilities'][ WPRP_CAP ] ) ) {
			$roles[] = $slug;
		}
	}
	if ( ! $roles ) {
		return array();
	}
	$users = get_users(
		array(
			'role__in' => $roles,
			'orderby'  => 'display_name',
			'number'   => 200,
			'fields'   => array( 'ID', 'display_name' ),
		)
	);
	$out = array();
	foreach ( $users as $u ) {
		$out[ (int) $u->ID ] = $u->display_name;
	}
	set_transient( 'wprp_assignable_users', $out, HOUR_IN_SECONDS );
	return $out;
}
// Bust the assignable-users cache when the user base / roles / names change.
add_action( 'set_user_role', function () { delete_transient( 'wprp_assignable_users' ); } );
add_action( 'profile_update', function () { delete_transient( 'wprp_assignable_users' ); } );
add_action( 'user_register', function () { delete_transient( 'wprp_assignable_users' ); } );
add_action( 'deleted_user', function () { delete_transient( 'wprp_assignable_users' ); } );

/**
 * Validate + normalise an element-pin anchor payload into a compact JSON string
 * ({"sel":"...","x":0.5,"y":0.3}). Returns '' if it is missing or malformed.
 * sel is a CSS selector path (length-capped); x/y are 0..1 fractions of the
 * element box. The selector is only ever used client-side in querySelector and
 * is escaped on output, so we just bound its length + character set here.
 */
function wprp_sanitize_anchor( $anchor ) {
	if ( ! is_string( $anchor ) || '' === $anchor ) {
		return '';
	}
	$data = json_decode( $anchor, true );
	if ( ! is_array( $data ) || empty( $data['sel'] ) || ! is_string( $data['sel'] ) ) {
		return '';
	}
	$sel = trim( $data['sel'] );
	// Our selectors use the child combinator ' > ', so '>' is allowed; we only block the
	// chars that could break out of an HTML attribute/script context as defence in depth
	// (the selector is JSON-encoded in REST output and only ever used in querySelector).
	if ( '' === $sel || strlen( $sel ) > 600 || preg_match( '/[<"\']/', $sel ) ) {
		return '';
	}
	$x = isset( $data['x'] ) ? (float) $data['x'] : 0.5;
	$y = isset( $data['y'] ) ? (float) $data['y'] : 0.5;
	$x = min( 1, max( 0, $x ) );
	$y = min( 1, max( 0, $y ) );
	return (string) wp_json_encode(
		array(
			'sel' => $sel,
			'x'   => round( $x, 4 ),
			'y'   => round( $y, 4 ),
		)
	);
}

/** True when the current user is allowed to use Red Pen at all. */
function wprp_user_can() {
	return is_user_logged_in() && current_user_can( WPRP_CAP );
}

/** True when the current user has Dev Mode switched on. */
function wprp_devmode_on() {
	return wprp_user_can() && (bool) get_user_meta( get_current_user_id(), WPRP_USERMETA, true );
}

/* ---------------------------------------------------------------------------
 * Client reviewer links (Phase 1 of the WordPress Client Reviewer Link feature).
 *
 * A reviewer link lets a NON-LOGGED-IN client leave notes through an unguessable
 * bearer token. Tokens are stored HASH-ONLY (like a password) in the
 * WPRP_REVIEW_TOKENS_OPT option - the raw token is shown to the admin exactly
 * once at creation and never recoverable afterwards. The helpers below are the
 * token store + validation layer. NOTHING consumes wprp_can_review() /
 * wprp_can_contribute() yet - the render gate, asset enqueue, and REST callbacks
 * are deliberately left untouched until later phases.
 * ------------------------------------------------------------------------- */

/** The stored reviewer-token records (array of { id, label, hash, created, expires, enabled }). */
function wprp_review_tokens() {
	$tokens = get_option( WPRP_REVIEW_TOKENS_OPT, array() );
	return is_array( $tokens ) ? $tokens : array();
}

/**
 * Generate a new reviewer-link token. Stores ONLY a SHA-256 hash of the high-entropy
 * raw token; returns the RAW token (so the caller can display it once) plus the record.
 * Capability checks belong in the admin handler, not here.
 *
 * @param string $label   Human label for the link (client/project name).
 * @param int    $expires Unix timestamp the link expires, or 0 for never.
 * @return array { token: raw token string, record: stored record array }
 */
function wprp_generate_review_token( $label, $expires = 0 ) {
	// 20 random bytes = 160 bits of entropy, hex-encoded to a 40-char bearer token.
	if ( function_exists( 'random_bytes' ) ) {
		$raw = bin2hex( random_bytes( 20 ) );
	} else {
		$raw = wp_generate_password( 40, false ); // fallback only; random_bytes is preferred
	}
	$record = array(
		'id'      => uniqid( 'rp', true ),
		'label'   => sanitize_text_field( (string) $label ),
		'hash'    => hash( 'sha256', $raw ), // store the hash, never the raw token
		'created' => time(),
		'expires' => (int) $expires, // 0 = never
		'enabled' => true,
	);
	$tokens   = wprp_review_tokens();
	$tokens[] = $record;
	update_option( WPRP_REVIEW_TOKENS_OPT, $tokens, false ); // not autoloaded
	return array(
		'token'  => $raw,
		'record' => $record,
	);
}

/**
 * Look up a raw token against the store. Returns the matching record ONLY when it is
 * enabled and unexpired, using a timing-safe hash_equals compare. False otherwise.
 *
 * @param string $raw The raw bearer token from the request.
 * @return array|false The matched record, or false.
 */
function wprp_find_review_token( $raw ) {
	$raw = (string) $raw;
	if ( '' === $raw ) {
		return false;
	}
	$candidate = hash( 'sha256', $raw );
	$now        = time();
	foreach ( wprp_review_tokens() as $record ) {
		if ( empty( $record['enabled'] ) ) {
			continue;
		}
		if ( ! empty( $record['expires'] ) && (int) $record['expires'] <= $now ) {
			continue;
		}
		// Timing-safe compare so a token cannot be discovered byte-by-byte.
		if ( hash_equals( (string) $record['hash'], $candidate ) ) {
			return $record;
		}
	}
	return false;
}

/**
 * Resolve the RAW reviewer token currently in effect on a FRONT-END request, in
 * source order: ?wprp_review= query param, the X-WPRP-Review-Token request header
 * (how reviewer JS authenticates its REST fetches), then the reviewer cookie (auto
 * sent same-origin). Returns '' in wp-admin or when no token is present. NOT a
 * validation - just the raw bearer string, sanitized.
 *
 * @return string
 */
function wprp_current_review_raw() {
	// Reviewer mode is a strictly front-end door; never honour the token in wp-admin.
	if ( is_admin() ) {
		return '';
	}
	if ( isset( $_GET[ WPRP_REVIEW_COOKIE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- a bearer token, validated against the store, not a state-changing action
		return sanitize_text_field( wp_unslash( $_GET[ WPRP_REVIEW_COOKIE ] ) );
	}
	// REST calls from reviewer JS carry the token as a header (no wp_rest nonce to send).
	if ( isset( $_SERVER['HTTP_X_WPRP_REVIEW_TOKEN'] ) ) {
		return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPRP_REVIEW_TOKEN'] ) );
	}
	if ( isset( $_COOKIE[ WPRP_REVIEW_COOKIE ] ) ) {
		return sanitize_text_field( wp_unslash( $_COOKIE[ WPRP_REVIEW_COOKIE ] ) );
	}
	return '';
}

/**
 * On a FRONT-END (non-admin) request, resolve the reviewer token from the query
 * string (?wprp_review=), the X-WPRP-Review-Token header, or the reviewer cookie
 * and validate it against the store. Memoized per request. Returns the matched
 * record or false.
 *
 * @return array|false
 */
function wprp_reviewer_token_valid() {
	static $cached = null;
	if ( null !== $cached ) {
		return $cached;
	}
	// Reviewer mode is a strictly front-end door; never honour the token in wp-admin.
	if ( is_admin() ) {
		$cached = false;
		return $cached;
	}
	$raw    = wprp_current_review_raw();
	$cached = $raw ? wprp_find_review_token( $raw ) : false;
	return $cached;
}

/** True when a valid reviewer token is present on a front-end request. (Helper only - nothing consumes it yet.) */
function wprp_can_review() {
	return (bool) wprp_reviewer_token_valid();
}

/** True when the request may CREATE notes/replies: a logged-in dev OR a valid reviewer. (Defined now, consumed in a later phase.) */
function wprp_can_contribute() {
	return wprp_user_can() || wprp_can_review();
}

/**
 * Revoke a reviewer link by id: flip its enabled flag to false (kept for the audit
 * trail rather than deleted) and persist. Capability checks belong in the handler.
 *
 * @param string $id The record id.
 * @return bool True if a record was found and flipped.
 */
function wprp_revoke_review_token( $id ) {
	$id      = (string) $id;
	$tokens  = wprp_review_tokens();
	$changed = false;
	foreach ( $tokens as &$record ) {
		if ( isset( $record['id'] ) && (string) $record['id'] === $id && ! empty( $record['enabled'] ) ) {
			$record['enabled'] = false;
			$changed           = true;
		}
	}
	unset( $record );
	if ( $changed ) {
		update_option( WPRP_REVIEW_TOKENS_OPT, $tokens, false );
	}
	return $changed;
}

/**
 * Persist reviewer mode across navigation: when a valid ?wprp_review=<raw> lands on a
 * front-end request, (re)set a short-lived, site-scoped, httponly cookie. The cookie
 * only persists the mode - validation always re-checks the raw token against the store
 * on every request, so a revoked/expired token stops working immediately regardless of
 * the cookie. Runs on init at a priority after cookies are available.
 */
function wprp_review_set_cookie() {
	if ( is_admin() ) {
		return;
	}
	if ( ! isset( $_GET[ WPRP_REVIEW_COOKIE ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- bearer token, validated below
		return;
	}
	$raw = sanitize_text_field( wp_unslash( $_GET[ WPRP_REVIEW_COOKIE ] ) );
	if ( ! $raw || ! wprp_find_review_token( $raw ) ) {
		return; // only set the cookie for a token that currently validates
	}
	// A few-hours TTL; refreshed on every valid hit. httponly TRUE - the token is passed
	// to JS via the page config in a later phase, not read from the cookie by script.
	setcookie(
		WPRP_REVIEW_COOKIE,
		$raw,
		array(
			'expires'  => time() + ( 4 * HOUR_IN_SECONDS ),
			'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Lax',
		)
	);
}
add_action( 'init', 'wprp_review_set_cookie' );

/** Absolute path to the screenshots folder (uploads/wp-red-pen), created on demand. */
function wprp_shot_dir( $create = false ) {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . WPRP_SHOT_DIR;
	if ( $create ) {
		wp_mkdir_p( $dir );
		wprp_write_shot_guards( $dir );
	}
	return $dir;
}

/**
 * Drop a deny-by-default .htaccess + blank index.php into the screenshots folder so the
 * captures (which can show private/draft/admin-only pages) cannot be fetched or listed
 * directly over the web. They are served ONLY through the capability-gated reader
 * (admin-post wprp_shot) - defence in depth alongside the unguessable filename token.
 */
function wprp_write_shot_guards( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$ht = $dir . '/.htaccess';
	if ( ! file_exists( $ht ) ) {
		$rules = "# WP Red Pen - deny direct web access; screenshots are served via the gated reader.\n"
			. "<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
		@file_put_contents( $ht, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
	}
	$idx = $dir . '/index.php';
	if ( ! file_exists( $idx ) ) {
		@file_put_contents( $idx, "<?php // Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
	}
}

/**
 * URL for a stored screenshot - points at the capability-gated reader (admin-post
 * wprp_shot), NOT the raw public uploads path, so captures of private pages aren't
 * world-readable. The nonce is fresh per request and only meaningful for a logged-in user.
 */
function wprp_shot_url( $file ) {
	if ( ! $file ) {
		return '';
	}
	return add_query_arg(
		array(
			'action'   => 'wprp_shot',
			'file'     => rawurlencode( basename( $file ) ),
			'_wpnonce' => wp_create_nonce( 'wprp_shot' ),
		),
		admin_url( 'admin-post.php' )
	);
}

/**
 * Capability-gated screenshot reader. The <img> URLs from wprp_shot_url() resolve here;
 * verify the Red Pen capability + nonce, then stream the file from uploads/wp-red-pen.
 * Logged-out / nonce-less / out-of-folder requests get 403/404, so captures never leak.
 */
function wprp_serve_shot() {
	if ( ! wprp_user_can() ) {
		status_header( 403 );
		exit;
	}
	$file = isset( $_GET['file'] ) ? basename( sanitize_file_name( wp_unslash( $_GET['file'] ) ) ) : '';
	if ( '' === $file
		|| ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wprp_shot' )
		|| ! preg_match( '/\.(webp|png|jpg)$/i', $file ) ) {
		status_header( 403 );
		exit;
	}
	$path = wprp_shot_dir() . '/' . $file;
	if ( ! is_file( $path ) ) {
		status_header( 404 );
		exit;
	}
	$ext   = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
	$types = array(
		'webp' => 'image/webp',
		'png'  => 'image/png',
		'jpg'  => 'image/jpeg',
	);
	nocache_headers();
	header( 'Content-Type: ' . ( isset( $types[ $ext ] ) ? $types[ $ext ] : 'application/octet-stream' ) );
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Content-Disposition: inline; filename="' . $file . '"' );
	readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
	exit;
}
add_action( 'admin_post_wprp_shot', 'wprp_serve_shot' );
add_action( 'admin_post_nopriv_wprp_shot', 'wprp_serve_shot' );

/** One-time: drop the deny guards into an EXISTING screenshots folder (installs predating the gated reader). */
add_action(
	'admin_init',
	function () {
		if ( ! wprp_user_can() || get_option( 'wprp_shot_guarded' ) ) {
			return;
		}
		$dir = wprp_shot_dir( false );
		if ( is_dir( $dir ) ) {
			wprp_write_shot_guards( $dir );
		}
		update_option( 'wprp_shot_guarded', 1, false );
	}
);

/**
 * Decode a base64 image data URL from the browser (a WebP region grab, with
 * PNG/JPEG fallbacks) and write it into the screenshots folder. Returns the
 * stored filename, or '' on any problem. Validates the declared type, the magic
 * bytes (getimagesizefromstring), and a 6 MB ceiling.
 *
 * @param int    $note_id  Owning note id (used in the filename).
 * @param string $data_url data:image/webp;base64,... payload.
 * @return string Stored filename or ''.
 */
function wprp_save_shot( $note_id, $data_url ) {
	if ( ! is_string( $data_url ) || '' === $data_url ) {
		return '';
	}
	if ( ! preg_match( '#^data:image/(webp|png|jpeg);base64,#', $data_url, $m ) ) {
		return '';
	}
	$ext   = ( 'webp' === $m[1] ) ? 'webp' : ( ( 'jpeg' === $m[1] ) ? 'jpg' : 'png' );
	$bytes = base64_decode( substr( $data_url, strpos( $data_url, ',' ) + 1 ), true );
	if ( false === $bytes || strlen( $bytes ) < 64 || strlen( $bytes ) > 6 * MB_IN_BYTES ) {
		return '';
	}
	if ( ! @getimagesizefromstring( $bytes ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return '';
	}
	$dir = wprp_shot_dir( true );
	if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
		return '';
	}
	$file = 'note-' . (int) $note_id . '-' . wp_generate_password( 8, false ) . '.' . $ext;
	// Direct write: the bytes are already a validated image, extension is forced.
	if ( false === file_put_contents( $dir . '/' . $file, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return '';
	}
	return $file;
}

/** Delete a stored screenshot file by filename (basename-guarded). */
function wprp_delete_shot( $file ) {
	if ( ! $file ) {
		return;
	}
	$path = wprp_shot_dir() . '/' . basename( $file );
	if ( is_file( $path ) ) {
		wp_delete_file( $path );
	}
}

/** When a note is permanently deleted, remove its screenshot file too. */
add_action(
	'before_delete_post',
	function ( $post_id ) {
		if ( WPRP_CPT !== get_post_type( $post_id ) ) {
			return;
		}
		wprp_flush_counts(); // a deleted note may change the open count
		wprp_delete_shot( (string) get_post_meta( $post_id, WPRP_META_SHOT, true ) );

		// If this was an agent-targeted note, refresh that agent's brief (excluding the note being removed).
		$wprp_del_agent = (string) get_post_meta( $post_id, WPRP_META_AGENT, true );
		if ( '' !== $wprp_del_agent ) {
			wprp_write_agent_brief( $wprp_del_agent, (int) $post_id );
		}

		// Non-hierarchical CPT: WP won't cascade child replies, so delete them here
		// (only for top-level notes; replies have no children of their own). Fetch ALL
		// children unbounded - wprp_get_replies caps at 200, which would orphan the rest.
		$post = get_post( $post_id );
		if ( $post && 0 === (int) $post->post_parent ) {
			$reply_ids = get_posts(
				array(
					'post_type'      => WPRP_CPT,
					'post_status'    => array( WPRP_STATUS_OPEN, WPRP_STATUS_DONE ),
					'post_parent'    => (int) $post_id,
					'posts_per_page' => -1,
					'fields'         => 'ids',
				)
			);
			foreach ( $reply_ids as $reply_id ) {
				wp_delete_post( (int) $reply_id, true );
			}
		}
	}
);

// ---------------------------------------------------------------------------
// i18n
// ---------------------------------------------------------------------------
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'wp-red-pen', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

// ---------------------------------------------------------------------------
// Content model: a private note CPT + two custom statuses
// ---------------------------------------------------------------------------
add_action(
	'init',
	function () {
		register_post_type(
			WPRP_CPT,
			array(
				'label'               => __( 'Red Pen Notes', 'wp-red-pen' ),
				'public'              => false,
				'show_ui'             => false,   // we render our own surfaces
				'show_in_rest'        => false,   // our own REST namespace instead
				'exclude_from_search' => true,
				'hierarchical'        => false,
				'supports'            => array( 'editor', 'author' ),
				'map_meta_cap'        => true,
				'capability_type'     => 'post',
			)
		);

		register_post_status(
			WPRP_STATUS_OPEN,
			array(
				'label'                     => _x( 'Open', 'red pen note status', 'wp-red-pen' ),
				'public'                    => false,
				'internal'                  => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			)
		);
		register_post_status(
			WPRP_STATUS_PROGRESS,
			array(
				'label'                     => _x( 'In Progress', 'red pen note status', 'wp-red-pen' ),
				'public'                    => false,
				'internal'                  => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			)
		);
		register_post_status(
			WPRP_STATUS_DONE,
			array(
				'label'                     => _x( 'Resolved', 'red pen note status', 'wp-red-pen' ),
				'public'                    => false,
				'internal'                  => true,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
			)
		);
	}
);

// ---------------------------------------------------------------------------
// Note helpers (the shared core all three surfaces call)
// ---------------------------------------------------------------------------

/**
 * Create a note. Returns the new post id or WP_Error.
 *
 * @param int    $target_id Post/page the note is about.
 * @param string $body      The note text.
 * @param string $type      One of wprp_note_types() keys.
 * @param string $url       Context URL (where the note was dropped).
 * @param string $shot      Optional base64 image data URL to attach.
 * @param string $ctx       Optional browser/OS/viewport string (display only).
 * @param string $priority  One of wprp_priorities() keys (default 'normal').
 * @param int    $assignee  User id to assign the note to (0 = unassigned).
 * @param string $anchor    Optional element-pin anchor JSON (selector + x/y).
 */
/**
 * Persist a note's targeting context. $context may carry 'level' (page|template),
 * 'key' (canonical context key, e.g. post:12 / pt_archive:composer / tpl:single-x)
 * and 'label' (human). Falls back to a post: key when only a post target is known,
 * so legacy create calls and the per-post meta box keep working.
 */
function wprp_save_context( $note_id, $context, $target_id = 0 ) {
	$context = is_array( $context ) ? $context : array();
	$level   = ( isset( $context['level'] ) && in_array( $context['level'], array( 'template', 'global' ), true ) ) ? $context['level'] : 'page';
	$key     = isset( $context['key'] ) ? sanitize_text_field( (string) $context['key'] ) : '';
	$label   = isset( $context['label'] ) ? sanitize_text_field( (string) $context['label'] ) : '';
	if ( '' === $key && (int) $target_id > 0 ) {
		$key   = 'post:' . (int) $target_id;
		$label = '' !== $label ? $label : get_the_title( (int) $target_id );
	}
	update_post_meta( $note_id, WPRP_META_LEVEL, $level );
	if ( '' !== $key ) {
		update_post_meta( $note_id, WPRP_META_CTXKEY, $key );
	}
	if ( '' !== $label ) {
		update_post_meta( $note_id, WPRP_META_CTXLABEL, mb_substr( $label, 0, 200 ) );
	}
}

/**
 * Sanitize a note/reply body for safe storage AND display. Uses an EXPLICIT reduced
 * allowed-tags set, NOT wp_kses_post(): wp_kses_post is a no-op for users who hold the
 * unfiltered_html capability (admins / super admins), so on a shared review surface that
 * is re-rendered to every other editor via innerHTML it would let a privileged author
 * plant stored XSS. An explicit wp_kses() allow-list cannot be widened by any capability.
 */
function wprp_kses_note( $content ) {
	return wp_kses(
		(string) $content,
		array(
			'a'          => array( 'href' => true, 'title' => true, 'rel' => true ),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'u'          => array(),
			'code'       => array(),
			'pre'        => array(),
			'br'         => array(),
			'p'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'blockquote' => array(),
		)
	);
}

/**
 * Anti-abuse rate limiter for the reviewer (anonymous, token-bearing) create path.
 *
 * A reviewer link lets an unauthenticated visitor POST notes and replies, so the create
 * surface must be throttled before any real client gets a link. Logged-in devs are NEVER
 * limited - this helper is only ever called when the actor is a reviewer.
 *
 * A transient-backed sliding-window counter keyed by a hash of the visitor IP AND the raw
 * reviewer token (so two clients on the same office NAT do not share a budget, and a single
 * leaked token cannot be spread across IPs to multiply the cap). The transient TTL is the
 * window, so it self-expires - no cron, no cleanup. Returns true when the actor is OVER the
 * cap (caller should refuse with 429); false when there is still budget (and increments).
 *
 * IP is read from REMOTE_ADDR and validated as an IP. Behind a reverse proxy / load balancer
 * REMOTE_ADDR can be the proxy's address (so all reviewers would share one bucket) - acceptable
 * for v1; we deliberately do NOT trust X-Forwarded-For (spoofable, would let an attacker mint a
 * fresh bucket per request and defeat the limit entirely). The per-token half of the key still
 * bounds total volume even when the IP collapses to a proxy.
 *
 * @return bool True if the request is over the limit and must be rejected.
 */
function wprp_review_rate_exceeded() {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : false;
	$ip  = $ip ? $ip : 'noip'; // graceful fallback: still windowed, just coarser
	$raw = wprp_current_review_raw(); // the raw bearer token in effect this request
	$key = 'wprp_rl_' . md5( $ip . '|' . $raw ); // md5 only to bound the transient key length; not a security hash
	$n   = (int) get_transient( $key );
	if ( $n >= WPRP_REVIEW_RATE_MAX ) {
		return true;
	}
	set_transient( $key, $n + 1, WPRP_REVIEW_RATE_WINDOW );
	return false;
}

function wprp_create_note( $target_id, $body, $type = 'note', $url = '', $shot = '', $ctx = '', $priority = 'normal', $assignee = 0, $anchor = '', $context = array(), $agent = '', $codescope = '', $reviewer_name = '', $severity = '', $client_visible = false ) {
	if ( ! wprp_can_contribute() ) {
		return new WP_Error( 'wprp_forbidden', __( 'You cannot add notes.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	// A reviewer (valid token, NOT a logged-in dev) is hard-constrained: forced open, author 0,
	// no assignee / agent / code scope / screenshot, and stamped via_review + the reviewer name.
	$reviewer = ! wprp_user_can() && wprp_can_review();
	// Anti-abuse: throttle the anonymous reviewer create path only. Devs are never limited.
	if ( $reviewer && wprp_review_rate_exceeded() ) {
		return new WP_Error( 'wprp_rate_limited', __( 'You are adding notes too quickly. Please wait a few minutes and try again.', 'wp-red-pen' ), array( 'status' => 429 ) );
	}
	$body = trim( wprp_kses_note( $body ) );
	if ( '' === $body ) {
		return new WP_Error( 'wprp_empty', __( 'The note is empty.', 'wp-red-pen' ), array( 'status' => 400 ) );
	}
	$types = wprp_note_types();
	$type  = isset( $types[ $type ] ) ? $type : 'note';

	// Reviewers must never choose their own targeting. Derive it server-side from the page
	// URL and forbid site-wide / arbitrary-post targets, so a reviewer note always lands on
	// the public page they are actually on (never a global note on every dev page, never
	// attached to some unrelated or non-public post).
	if ( $reviewer ) {
		$rid = $url ? url_to_postid( $url ) : 0;
		if ( $rid > 0 && wprp_is_public_post( $rid ) ) {
			$target_id = $rid;
			$context   = array( 'level' => 'page', 'key' => 'post:' . $rid, 'label' => get_the_title( $rid ) );
		} else {
			$ck        = isset( $context['key'] ) ? $context['key'] : '';
			$okeys     = wprp_reviewer_safe_keys( array( $ck ) );
			$target_id = 0;
			$context   = $okeys
				? array( 'level' => 'page', 'key' => $okeys[0], 'label' => isset( $context['label'] ) ? (string) $context['label'] : '' )
				: array();
		}
	}

	$id = wp_insert_post(
		array(
			'post_type'    => WPRP_CPT,
			'post_status'  => WPRP_STATUS_OPEN,
			'post_author'  => $reviewer ? 0 : get_current_user_id(),
			'post_content' => $body,
			'post_title'   => wp_trim_words( wp_strip_all_tags( $body ), 8, '...' ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, WPRP_META_TARGET, (int) $target_id );
	update_post_meta( $id, WPRP_META_TYPE, $type );
	update_post_meta( $id, WPRP_META_URL, esc_url_raw( $url ) );
	wprp_save_context( $id, $context, (int) $target_id );

	$ctx = sanitize_text_field( (string) $ctx );
	if ( '' !== $ctx ) {
		update_post_meta( $id, WPRP_META_CTX, mb_substr( $ctx, 0, 200 ) );
	}

	$prios    = wprp_priorities();
	$priority = isset( $prios[ $priority ] ) ? $priority : 'normal';
	update_post_meta( $id, WPRP_META_PRIORITY, $priority );

	$sevs     = wprp_severities();
	if ( isset( $sevs[ $severity ] ) ) {
		update_post_meta( $id, WPRP_META_SEVERITY, $severity );
	}

	if ( $reviewer ) {
		// Mark the note as reviewer feedback and attribute it; ignore assignee/agent/codescope/shot.
		update_post_meta( $id, WPRP_META_VIA_REVIEW, 1 );
		// A note the client wrote is client-visible by definition, so the reviewer can always
		// see their own report back. Stamping the token record id is what lets the read path
		// tell "their own" from "some other client's" later on.
		update_post_meta( $id, WPRP_META_CLIENT_VISIBLE, 1 );
		$tok_id = wprp_current_review_token_id();
		if ( '' !== $tok_id ) {
			update_post_meta( $id, WPRP_META_REVIEW_TOKEN, $tok_id );
		}
		$reviewer_name = sanitize_text_field( (string) $reviewer_name );
		if ( '' !== $reviewer_name ) {
			update_post_meta( $id, WPRP_META_REVIEWER, mb_substr( $reviewer_name, 0, 80 ) );
		}
	} else {
		// Dev-filed notes are INTERNAL unless the author ticks "Visible to client reviewers".
		if ( ! empty( $client_visible ) ) {
			update_post_meta( $id, WPRP_META_CLIENT_VISIBLE, 1 );
		}
		$assignee = (int) $assignee;
		if ( $assignee > 0 && user_can( $assignee, WPRP_CAP ) ) {
			update_post_meta( $id, WPRP_META_ASSIGNEE, $assignee );
		}

		$agent = (string) $agent;
		if ( '' !== $agent && '' !== wprp_agent_label( $agent ) ) {
			update_post_meta( $id, WPRP_META_AGENT, $agent );
		}
		$codescope = sanitize_text_field( (string) $codescope );
		if ( '' !== $codescope ) {
			update_post_meta( $id, WPRP_META_CODESCOPE, mb_substr( $codescope, 0, 300 ) );
		}
	}

	$anchor = wprp_sanitize_anchor( $anchor );
	if ( '' !== $anchor ) {
		update_post_meta( $id, WPRP_META_ANCHOR, $anchor );
	}

	// Reviewers cannot attach screenshots (the html2canvas asset is never even enqueued for them);
	// ignore any shot param defensively so a crafted request cannot smuggle a file in.
	if ( ! $reviewer && '' !== (string) $shot ) {
		$file = wprp_save_shot( $id, (string) $shot );
		if ( '' !== $file ) {
			update_post_meta( $id, WPRP_META_SHOT, $file );
		}
	}

	// Agent notes: rebuild the brief once, now that EVERY meta (codescope, url, anchor, shot) is
	// written. The meta hook above may have fired a partial snapshot mid-create; this supersedes it.
	if ( ! $reviewer && '' !== $agent && '' !== wprp_agent_label( $agent ) ) {
		wprp_write_agent_brief( $agent );
	}
	return $id;
}

/**
 * Add a reply to a note. Replies are child wprp_note posts (post_parent = the
 * note id); they carry no target/type/status meaning of their own. Returns the
 * new reply id or WP_Error.
 *
 * @param int    $parent_id The note being replied to.
 * @param string $body      The reply text.
 */
function wprp_create_reply( $parent_id, $body, $reviewer_name = '' ) {
	if ( ! wprp_can_contribute() ) {
		return new WP_Error( 'wprp_forbidden', __( 'You cannot reply.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	$reviewer = ! wprp_user_can() && wprp_can_review();
	// Anti-abuse: throttle the anonymous reviewer reply path only. Devs are never limited.
	if ( $reviewer && wprp_review_rate_exceeded() ) {
		return new WP_Error( 'wprp_rate_limited', __( 'You are replying too quickly. Please wait a few minutes and try again.', 'wp-red-pen' ), array( 'status' => 429 ) );
	}
	$parent = get_post( $parent_id );
	if ( ! $parent || WPRP_CPT !== $parent->post_type || (int) $parent->post_parent !== 0 ) {
		return new WP_Error( 'wprp_missing', __( 'Note not found.', 'wp-red-pen' ), array( 'status' => 404 ) );
	}
	// A reviewer may only reply to a note their scoped GET would have returned: OPEN, non-agent,
	// on a public page/template. The reply endpoint echoes the parent note back (shaped for the
	// reviewer), so without this a token holder could reach a site-wide/global dev note, a note on
	// a draft/private page, or an agent-queue note by its id - reading content the GET deliberately
	// hides and spamming replies onto internal notes. Same visibility rule as the read path.
	if ( $reviewer && ! wprp_reviewer_can_see_note( $parent ) ) {
		return new WP_Error( 'wprp_forbidden', __( 'You can only reply to open notes on this site\'s public pages.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	$body = trim( wprp_kses_note( $body ) );
	if ( '' === $body ) {
		return new WP_Error( 'wprp_empty', __( 'The reply is empty.', 'wp-red-pen' ), array( 'status' => 400 ) );
	}
	$reply_id = wp_insert_post(
		array(
			'post_type'    => WPRP_CPT,
			'post_status'  => WPRP_STATUS_OPEN,
			'post_parent'  => (int) $parent_id,
			'post_author'  => $reviewer ? 0 : get_current_user_id(),
			'post_content' => $body,
			'post_title'   => wp_trim_words( wp_strip_all_tags( $body ), 8, '...' ),
		),
		true
	);
	if ( ! is_wp_error( $reply_id ) && $reviewer ) {
		update_post_meta( $reply_id, WPRP_META_VIA_REVIEW, 1 );
		$tok_id = wprp_current_review_token_id();
		if ( '' !== $tok_id ) {
			update_post_meta( $reply_id, WPRP_META_REVIEW_TOKEN, $tok_id );
		}
		$reviewer_name = sanitize_text_field( (string) $reviewer_name );
		if ( '' !== $reviewer_name ) {
			update_post_meta( $reply_id, WPRP_META_REVIEWER, mb_substr( $reviewer_name, 0, 80 ) );
		}
	}
	return $reply_id;
}

/** Replies (child posts) of a note, oldest first. */
function wprp_get_replies( $parent_id ) {
	return get_posts(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => array( WPRP_STATUS_OPEN, WPRP_STATUS_DONE ),
			'post_parent'    => (int) $parent_id,
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'ASC',
		)
	);
}

/**
 * Replies for MANY notes in a single query: array( parent_id => WP_Post[] ), oldest first.
 * Kills the N+1 where wprp_note_to_array() fired a wprp_get_replies() query per note when
 * shaping a whole GET /notes payload.
 */
function wprp_get_replies_for( $parent_ids ) {
	$parent_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $parent_ids ) ) ) );
	if ( ! $parent_ids ) {
		return array();
	}
	$replies = get_posts(
		array(
			'post_type'       => WPRP_CPT,
			'post_status'     => array( WPRP_STATUS_OPEN, WPRP_STATUS_DONE ),
			'post_parent__in' => $parent_ids,
			'posts_per_page'  => -1,
			'orderby'         => 'date',
			'order'           => 'ASC',
		)
	);
	$map = array();
	foreach ( $replies as $r ) {
		$map[ (int) $r->post_parent ][] = $r;
	}
	return $map;
}

/**
 * Reply counts for many notes in a single query: array( parent_id => count ).
 * Avoids the N+1 of calling wprp_get_replies() per row in the repository table just
 * to count children.
 */
function wprp_reply_counts( $parent_ids ) {
	global $wpdb;
	$parent_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $parent_ids ) ) ) );
	if ( ! $parent_ids ) {
		return array();
	}
	$placeholders = implode( ',', array_fill( 0, count( $parent_ids ), '%d' ) );
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT post_parent, COUNT(*) AS c FROM {$wpdb->posts}
			 WHERE post_type = %s AND post_status IN ( %s, %s ) AND post_parent IN ( {$placeholders} )
			 GROUP BY post_parent",
			array_merge( array( WPRP_CPT, WPRP_STATUS_OPEN, WPRP_STATUS_DONE ), $parent_ids )
		)
	);
	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[ (int) $r->post_parent ] = (int) $r->c;
	}
	return $out;
}

/** Shape a reply post into the plain array the JS consumes. */
function wprp_reply_to_array( $reply ) {
	$author = get_userdata( $reply->post_author );
	return array(
		'id'     => (int) $reply->ID,
		'body'   => wpautop( wprp_kses_note( $reply->post_content ) ),
		'author' => $author ? $author->display_name : __( 'Unknown', 'wp-red-pen' ),
		'date'   => get_the_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $reply ),
	);
}

/**
 * Flip a note open/resolved. Returns true or WP_Error.
 *
 * @param int    $note_id Note post id.
 * @param string $status  WPRP_STATUS_OPEN or WPRP_STATUS_DONE.
 */
function wprp_set_status( $note_id, $status ) {
	if ( ! wprp_user_can() ) {
		return new WP_Error( 'wprp_forbidden', __( 'Not allowed.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	$note = get_post( $note_id );
	if ( ! $note || WPRP_CPT !== $note->post_type || 0 !== (int) $note->post_parent ) {
		// post_parent != 0 means this is a reply - replies have no open/resolved status of their own.
		return new WP_Error( 'wprp_missing', __( 'Note not found.', 'wp-red-pen' ), array( 'status' => 404 ) );
	}
	$status = in_array( $status, wprp_all_statuses(), true ) ? $status : WPRP_STATUS_OPEN;
	wp_update_post(
		array(
			'ID'          => (int) $note_id,
			'post_status' => $status,
		)
	);
	// Stamp who/when on resolve so the note carries an audit trail across surfaces
	// (the Hub board reads these); clear both if the note is reopened.
	if ( WPRP_STATUS_DONE === $status ) {
		if ( ! get_post_meta( (int) $note_id, WPRP_META_RESOLVED_AT, true ) ) {
			update_post_meta( (int) $note_id, WPRP_META_RESOLVED_AT, gmdate( 'c' ) );
		}
		update_post_meta( (int) $note_id, WPRP_META_RESOLVED_BY, (int) get_current_user_id() );
	} else {
		delete_post_meta( (int) $note_id, WPRP_META_RESOLVED_AT );
		delete_post_meta( (int) $note_id, WPRP_META_RESOLVED_BY );
	}
	return true;
}

/**
 * Update an existing note's editable fields. body/type/priority/assignee are set
 * whenever present in $args. The screenshot and element anchor each support an
 * explicit *_remove flag (so the large payloads only travel when actually changed):
 * remove wins, else a non-empty replacement applies, else the field is left as-is.
 * Replacing a screenshot deletes the old file. Returns true or WP_Error.
 *
 * @param int   $note_id A top-level note id (not a reply).
 * @param array $args    body, type, priority, assignee, anchor, anchor_remove, shot, shot_remove.
 */
function wprp_update_note( $note_id, $args ) {
	if ( ! wprp_user_can() ) {
		return new WP_Error( 'wprp_forbidden', __( 'Not allowed.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	$note = get_post( $note_id );
	if ( ! $note || WPRP_CPT !== $note->post_type || 0 !== (int) $note->post_parent ) {
		return new WP_Error( 'wprp_missing', __( 'Note not found.', 'wp-red-pen' ), array( 'status' => 404 ) );
	}

	if ( (int) $note->post_author !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
		return new WP_Error( 'wprp_forbidden', __( 'You can only edit your own notes.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	$body = trim( wprp_kses_note( isset( $args['body'] ) ? $args['body'] : '' ) );
	if ( '' === $body ) {
		return new WP_Error( 'wprp_empty', __( 'The note is empty.', 'wp-red-pen' ), array( 'status' => 400 ) );
	}
	wp_update_post(
		array(
			'ID'           => (int) $note_id,
			'post_content' => $body,
			'post_title'   => wp_trim_words( wp_strip_all_tags( $body ), 8, '...' ),
		)
	);

	if ( isset( $args['type'] ) ) {
		$types = wprp_note_types();
		$type  = isset( $types[ $args['type'] ] ) ? $args['type'] : 'note';
		update_post_meta( $note_id, WPRP_META_TYPE, $type );
	}

	// Re-target (change page/template level) when the editor sends a context.
	if ( isset( $args['level'] ) || isset( $args['ctx_key'] ) ) {
		$target = isset( $args['target'] ) ? (int) $args['target'] : 0;
		update_post_meta( $note_id, WPRP_META_TARGET, $target );
		wprp_save_context(
			$note_id,
			array(
				'level' => isset( $args['level'] ) ? $args['level'] : 'page',
				'key'   => isset( $args['ctx_key'] ) ? $args['ctx_key'] : '',
				'label' => isset( $args['ctx_label'] ) ? $args['ctx_label'] : '',
			),
			$target
		);
	}

	if ( isset( $args['priority'] ) ) {
		$prios    = wprp_priorities();
		$priority = isset( $prios[ $args['priority'] ] ) ? $args['priority'] : 'normal';
		update_post_meta( $note_id, WPRP_META_PRIORITY, $priority );
	}

	// Client visibility. Only touched when the caller actually sends the key, so a partial
	// update never silently exposes an internal note (or hides a client's own report).
	if ( isset( $args['client_visible'] ) ) {
		if ( ! empty( $args['client_visible'] ) ) {
			update_post_meta( $note_id, WPRP_META_CLIENT_VISIBLE, 1 );
		} else {
			delete_post_meta( $note_id, WPRP_META_CLIENT_VISIBLE );
		}
	}

	if ( isset( $args['severity'] ) ) {
		$sevs = wprp_severities();
		if ( isset( $sevs[ $args['severity'] ] ) ) {
			update_post_meta( $note_id, WPRP_META_SEVERITY, $args['severity'] );
		} else {
			delete_post_meta( $note_id, WPRP_META_SEVERITY );
		}
	}

	if ( isset( $args['assignee'] ) ) {
		$assignee = (int) $args['assignee'];
		if ( $assignee > 0 && user_can( $assignee, WPRP_CAP ) ) {
			update_post_meta( $note_id, WPRP_META_ASSIGNEE, $assignee );
		} else {
			delete_post_meta( $note_id, WPRP_META_ASSIGNEE );
		}
	}

	if ( isset( $args['agent'] ) ) {
		$agent = (string) $args['agent'];
		if ( '' !== $agent && '' !== wprp_agent_label( $agent ) ) {
			update_post_meta( $note_id, WPRP_META_AGENT, $agent );
		} else {
			delete_post_meta( $note_id, WPRP_META_AGENT );
		}
	}

	if ( isset( $args['codescope'] ) ) {
		$cs = sanitize_text_field( (string) $args['codescope'] );
		if ( '' !== $cs ) {
			update_post_meta( $note_id, WPRP_META_CODESCOPE, mb_substr( $cs, 0, 300 ) );
		} else {
			delete_post_meta( $note_id, WPRP_META_CODESCOPE );
		}
	}

	if ( ! empty( $args['anchor_remove'] ) ) {
		delete_post_meta( $note_id, WPRP_META_ANCHOR );
	} elseif ( '' !== (string) ( isset( $args['anchor'] ) ? $args['anchor'] : '' ) ) {
		$anchor = wprp_sanitize_anchor( (string) $args['anchor'] );
		if ( '' !== $anchor ) {
			update_post_meta( $note_id, WPRP_META_ANCHOR, $anchor );
		}
	}

	if ( ! empty( $args['shot_remove'] ) ) {
		wprp_delete_shot( (string) get_post_meta( $note_id, WPRP_META_SHOT, true ) );
		delete_post_meta( $note_id, WPRP_META_SHOT );
	} elseif ( '' !== (string) ( isset( $args['shot'] ) ? $args['shot'] : '' ) ) {
		$file = wprp_save_shot( $note_id, (string) $args['shot'] );
		if ( '' !== $file ) {
			wprp_delete_shot( (string) get_post_meta( $note_id, WPRP_META_SHOT, true ) );
			update_post_meta( $note_id, WPRP_META_SHOT, $file );
		}
	}

	return true;
}

/**
 * Notes attached to a target post.
 *
 * @param int    $target_id Target post id.
 * @param string $status    'open' | 'resolved' | 'any'.
 * @return WP_Post[]
 */
function wprp_get_notes_for( $target_id, $status = 'any' ) {
	$statuses = wprp_all_statuses();
	if ( 'open' === $status ) {
		$statuses = array( WPRP_STATUS_OPEN );
	} elseif ( 'resolved' === $status ) {
		$statuses = array( WPRP_STATUS_DONE );
	} elseif ( 'progress' === $status ) {
		$statuses = array( WPRP_STATUS_PROGRESS );
	}
	return get_posts(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => $statuses,
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => WPRP_META_TARGET, 'value' => (int) $target_id ),
				// Exclude agent-targeted notes from this human (meta-box) surface.
				array(
					'relation' => 'OR',
					array( 'key' => WPRP_META_AGENT, 'compare' => 'NOT EXISTS' ),
					array( 'key' => WPRP_META_AGENT, 'value' => '', 'compare' => '=' ),
				),
			),
		)
	);
}

/**
 * Every top-level human note across the whole site, regardless of which page/post
 * it is attached to. This is what the Hub's cross-project pull needs: the default
 * target=0 query only returns site-wide notes, so page-attached notes were invisible
 * on the combined board. Excludes agent-targeted notes and replies. Uncapped because
 * the Hub authenticates as a dev pulling its own site.
 *
 * @param string $status 'open' | 'progress' | 'resolved' | 'any'.
 * @return WP_Post[]
 */
function wprp_get_all_notes( $status = 'any' ) {
	$statuses = wprp_all_statuses();
	if ( 'open' === $status ) {
		$statuses = array( WPRP_STATUS_OPEN );
	} elseif ( 'resolved' === $status ) {
		$statuses = array( WPRP_STATUS_DONE );
	} elseif ( 'progress' === $status ) {
		$statuses = array( WPRP_STATUS_PROGRESS );
	}
	return get_posts(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => $statuses,
			'post_parent'    => 0,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => WPRP_META_AGENT, 'compare' => 'NOT EXISTS' ),
				array( 'key' => WPRP_META_AGENT, 'value' => '', 'compare' => '=' ),
			),
		)
	);
}

/**
 * Notes whose context key is one of $keys (top-level only). The front end passes
 * the current view's page key AND template key, so a page shows both its own
 * notes and the template-level notes that apply to every page like it.
 *
 * @param string[] $keys   Context keys to match.
 * @param string   $status 'open' | 'resolved' | 'any'.
 * @return WP_Post[]
 */
function wprp_get_notes_for_context( $keys, $status = 'any' ) {
	$keys = array_values( array_filter( array_map( 'strval', (array) $keys ) ) );
	if ( ! $keys ) {
		return array();
	}
	$statuses = wprp_all_statuses();
	if ( 'open' === $status ) {
		$statuses = array( WPRP_STATUS_OPEN );
	} elseif ( 'resolved' === $status ) {
		$statuses = array( WPRP_STATUS_DONE );
	} elseif ( 'progress' === $status ) {
		$statuses = array( WPRP_STATUS_PROGRESS );
	}
	return get_posts(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => $statuses,
			'post_parent'    => 0,
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => WPRP_META_CTXKEY,
					'value'   => $keys,
					'compare' => 'IN',
				),
				// Human surfaces exclude agent-targeted notes (those live in the repo's "For agents" view).
				array(
					'relation' => 'OR',
					array( 'key' => WPRP_META_AGENT, 'compare' => 'NOT EXISTS' ),
					array( 'key' => WPRP_META_AGENT, 'value' => '', 'compare' => '=' ),
				),
			),
		)
	);
}

/**
 * Every top-level note targeted at one agent, across the whole site - the agent's queue.
 * Unlike the human surfaces, this INCLUDES agent notes and keys on the agent slug.
 *
 * @param string $slug    Agent slug.
 * @param string $status  'open' | 'progress' | 'resolved' | 'any'.
 * @param int    $exclude Optional note ID to leave out (used while a note is mid-deletion).
 * @return WP_Post[]
 */
function wprp_get_notes_for_agent( $slug, $status = 'any', $exclude = 0 ) {
	$slug = (string) $slug;
	if ( '' === $slug ) {
		return array();
	}
	$statuses = wprp_all_statuses();
	if ( 'open' === $status ) {
		$statuses = array( WPRP_STATUS_OPEN );
	} elseif ( 'resolved' === $status ) {
		$statuses = array( WPRP_STATUS_DONE );
	} elseif ( 'progress' === $status ) {
		$statuses = array( WPRP_STATUS_PROGRESS );
	}
	$args = array(
		'post_type'      => WPRP_CPT,
		'post_status'    => $statuses,
		'post_parent'    => 0,
		'posts_per_page' => 500,
		'orderby'        => 'date',
		'order'          => 'DESC',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		'meta_query'     => array(
			array( 'key' => WPRP_META_AGENT, 'value' => $slug ),
		),
	);
	if ( $exclude > 0 ) {
		$args['post__not_in'] = array( (int) $exclude );
	}
	return get_posts( $args );
}

/**
 * Per-site random secret woven into the agent-brief filename so the briefs are as
 * unguessable as the screenshots. Without this the briefs sit at a KNOWN path
 * (agent-claude.json) and, on any server that ignores the folder's .htaccess deny
 * (nginx, or Apache with AllowOverride None), would be fetchable by anyone. Generated
 * once on first need, stored not-autoloaded.
 */
function wprp_brief_secret() {
	$secret = (string) get_option( 'wprp_brief_secret' );
	if ( '' === $secret ) {
		$secret = function_exists( 'random_bytes' ) ? bin2hex( random_bytes( 8 ) ) : wp_generate_password( 16, false );
		update_option( 'wprp_brief_secret', $secret, false );
	}
	return $secret;
}

/** Filesystem path of an agent's JSON brief inside the deny-protected screenshots folder. */
function wprp_agent_brief_path( $slug ) {
	return trailingslashit( wprp_shot_dir() ) . 'agent-' . sanitize_file_name( (string) $slug ) . '-' . wprp_brief_secret() . '.json';
}

/**
 * Is a post safe for a reviewer (an anonymous client-link holder) to see notes about?
 * Only publicly-viewable published content - never drafts, private, pending, or trashed
 * posts, whose note bodies could leak internal/unpublished work.
 */
function wprp_is_public_post( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post ) {
		return false;
	}
	if ( function_exists( 'is_post_publicly_viewable' ) ) { // WP 5.7+
		return (bool) is_post_publicly_viewable( $post );
	}
	$type = get_post_type_object( $post->post_type );
	return 'publish' === $post->post_status && $type && $type->public;
}

/**
 * Narrow a set of context keys to the ones a reviewer is allowed to read notes for.
 * Drops the site-wide (global) key entirely - reviewers never see site-wide dev notes -
 * and drops post keys that point at non-public content. Public view keys (home / front /
 * search / 404 and the tpl:/pt_archive:/term:/date:/author: archive templates) pass.
 * This is the server-side guard: the client can ask for any keys, but only these return.
 */
function wprp_reviewer_safe_keys( $keys ) {
	$safe = array();
	foreach ( (array) $keys as $key ) {
		$key = (string) $key;
		if ( '' === $key || WPRP_GLOBAL_KEY === $key ) {
			continue;
		}
		if ( 0 === strpos( $key, 'post:' ) ) {
			$pid = (int) substr( $key, 5 );
			if ( $pid > 0 && wprp_is_public_post( $pid ) ) {
				$safe[] = $key;
			}
			continue;
		}
		if ( preg_match( '/^(front|home|search|404)$/', $key )
			|| preg_match( '/^(tpl:|pt_archive:|term:|date:|author:)/', $key ) ) {
			$safe[] = $key;
		}
	}
	return array_values( array_unique( $safe ) );
}

/**
 * The reviewer-link token record id in effect on this request, or '' when there is no
 * valid token. This id is the ONLY durable identity an anonymous reviewer has - they
 * have no account and the typed display name is free text - so it is what scopes "my
 * own notes" on the read path.
 *
 * @return string
 */
function wprp_current_review_token_id() {
	$rec = wprp_reviewer_token_valid();
	return ( is_array( $rec ) && isset( $rec['id'] ) ) ? (string) $rec['id'] : '';
}

/**
 * Has this note been explicitly opted in to the client-reviewer surface?
 *
 * FAILS CLOSED on purpose. A missing flag means internal, so a note that predates the
 * flag - or one a dev filed without ticking the box - stays invisible to every reviewer
 * link. The dev's own working notes on a live client page are the default, not the
 * exception, which is why the default has to be the safe one.
 *
 * @param int $note_id Note post id.
 * @return bool
 */
function wprp_note_is_client_visible( $note_id ) {
	return '1' === (string) get_post_meta( (int) $note_id, WPRP_META_CLIENT_VISIBLE, true );
}

/**
 * Did the reviewer holding THIS request's link file this note themselves? Requires both
 * the via-review stamp and a matching token record id, so one client's link never surfaces
 * another client's notes and a revoked/reissued link does not inherit the old one's history.
 *
 * @param int $note_id Note post id.
 * @return bool
 */
function wprp_reviewer_owns_note( $note_id ) {
	$note_id = (int) $note_id;
	if ( '1' !== (string) get_post_meta( $note_id, WPRP_META_VIA_REVIEW, true ) ) {
		return false;
	}
	$tok = wprp_current_review_token_id();
	if ( '' === $tok ) {
		return false;
	}
	return $tok === (string) get_post_meta( $note_id, WPRP_META_REVIEW_TOKEN, true );
}

/**
 * THE single reviewer visibility gate. Every reviewer read path runs through this, so a
 * note is either visible to the token holder everywhere or nowhere - the GET, the priming
 * payload, the create/reply echo, and anything added later cannot drift apart.
 *
 * A note is visible when ALL of the structural constraints hold:
 *   - it is a top-level note (never a reply post, never another post type)
 *   - it is not an agent-queue note (that is an internal surface)
 *   - its context key is one wprp_reviewer_safe_keys() allows - never site-wide, never a
 *     draft/private/pending post
 * AND either:
 *   - the token holder filed it themselves, at ANY status (so a client can see that what
 *     they reported was fixed instead of assuming it was lost), or
 *   - it carries the explicit client-visible flag AND is still open.
 *
 * The flag is an ADDITIONAL gate on top of the older constraints, never a replacement for
 * them. Without it, any open note on any public page was readable by any link holder - a
 * developer's internal notes included.
 *
 * @param WP_Post|null $note A top-level note post.
 * @return bool
 */
function wprp_reviewer_can_see_note( $note ) {
	if ( ! $note || WPRP_CPT !== $note->post_type || 0 !== (int) $note->post_parent ) {
		return false;
	}
	if ( '' !== (string) get_post_meta( $note->ID, WPRP_META_AGENT, true ) ) {
		return false; // agent notes are an internal surface, never shown to reviewers
	}
	$key = (string) get_post_meta( $note->ID, WPRP_META_CTXKEY, true );
	if ( ! wprp_reviewer_safe_keys( array( $key ) ) ) {
		return false;
	}
	if ( wprp_reviewer_owns_note( $note->ID ) ) {
		return true; // their own report, at whatever status it has reached
	}
	if ( ! wprp_note_is_client_visible( $note->ID ) ) {
		return false; // not opted in = internal
	}
	return WPRP_STATUS_OPEN === $note->post_status;
}

/**
 * Narrow a list of note posts to the ones the current reviewer may read. Use this on every
 * reviewer read path instead of trusting the query's own scoping.
 *
 * @param WP_Post[] $notes Candidate notes.
 * @return WP_Post[]
 */
function wprp_reviewer_visible_notes( $notes ) {
	$out = array();
	foreach ( (array) $notes as $n ) {
		if ( wprp_reviewer_can_see_note( $n ) ) {
			$out[] = $n;
		}
	}
	return $out;
}

/**
 * Build the portable JSON brief for one agent: its actionable queue (open + in-progress),
 * bodies as plain text, replies, code scope, and where each note lives. Pure data - no
 * markup, no nonces - so a local agent can read the file straight off disk.
 */
function wprp_build_agent_brief( $slug, $exclude = 0 ) {
	$slug  = (string) $slug;
	$notes = wprp_get_notes_for_agent( $slug, 'any', $exclude );
	$types = wprp_note_types();
	$prios = wprp_priorities();
	$out   = array();
	foreach ( $notes as $n ) {
		$sk = wprp_status_key( $n->post_status );
		if ( 'resolved' === $sk ) {
			continue; // the brief is the actionable queue (open + in-progress)
		}
		$priority = (string) get_post_meta( $n->ID, WPRP_META_PRIORITY, true );
		$priority = isset( $prios[ $priority ] ) ? $priority : 'normal';
		$type     = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
		$replies  = array();
		foreach ( wprp_get_replies( $n->ID ) as $r ) {
			$ra        = get_userdata( $r->post_author );
			$replies[] = array(
				'author' => $ra ? $ra->display_name : '',
				'body'   => wp_strip_all_tags( $r->post_content ),
				'date'   => get_the_time( 'c', $r ),
			);
		}
		$out[] = array(
			'id'         => (int) $n->ID,
			'status'     => $sk,
			'type'       => isset( $types[ $type ] ) ? $type : 'note',
			'priority'   => $priority,
			'body'       => wp_strip_all_tags( $n->post_content ),
			'codeScope'  => (string) get_post_meta( $n->ID, WPRP_META_CODESCOPE, true ),
			'url'        => (string) get_post_meta( $n->ID, WPRP_META_URL, true ),
			'where'      => (string) get_post_meta( $n->ID, WPRP_META_CTXLABEL, true ),
			'anchor'     => (string) get_post_meta( $n->ID, WPRP_META_ANCHOR, true ),
			'screenshot' => (string) get_post_meta( $n->ID, WPRP_META_SHOT, true ),
			'replies'    => $replies,
		);
	}
	return array(
		'agent'      => $slug,
		'agentLabel' => wprp_agent_label( $slug ),
		'generated'  => gmdate( 'c' ),
		'site'       => home_url( '/' ),
		'count'      => count( $out ),
		'notes'      => $out,
	);
}

/**
 * Write (or, when the queue is empty, remove) an agent's JSON brief file. Called whenever an
 * agent note changes. The file lives in the deny-protected uploads folder, so it is readable
 * from the filesystem by a local agent but not fetchable over the web (briefs can describe
 * private pages).
 */
function wprp_write_agent_brief( $slug, $exclude = 0 ) {
	$slug = (string) $slug;
	if ( '' === $slug ) {
		return;
	}
	wprp_shot_dir( true ); // ensure the folder + deny guards exist
	$path  = wprp_agent_brief_path( $slug );
	// Clean up any legacy, guessably-named brief from before the secret suffix (v0.18.0).
	$legacy = trailingslashit( wprp_shot_dir() ) . 'agent-' . sanitize_file_name( $slug ) . '.json';
	if ( $legacy !== $path && file_exists( $legacy ) ) {
		@unlink( $legacy ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
	}
	$brief = wprp_build_agent_brief( $slug, $exclude );
	if ( empty( $brief['notes'] ) ) {
		if ( file_exists( $path ) ) {
			@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_unlink, WordPress.PHP.NoSilencedErrors.Discouraged
		}
		return;
	}
	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents, WordPress.PHP.NoSilencedErrors.Discouraged
	@file_put_contents( $path, wp_json_encode( $brief, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
}

/** Count of open notes across the whole site (for the admin-bar badge). */
function wprp_open_count() {
	$cached = get_transient( 'wprp_open_count' );
	if ( false !== $cached ) {
		return (int) $cached;
	}
	$q = new WP_Query(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => WPRP_STATUS_OPEN,
			'post_parent'    => 0, // count notes, not replies
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
			// The badge counts human open notes; agent-targeted notes are excluded.
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'OR',
				array( 'key' => WPRP_META_AGENT, 'compare' => 'NOT EXISTS' ),
				array( 'key' => WPRP_META_AGENT, 'value' => '', 'compare' => '=' ),
			),
		)
	);
	$count = (int) $q->found_posts;
	set_transient( 'wprp_open_count', $count, MINUTE_IN_SECONDS );
	return $count;
}

/** Bust the cached open-note count - called on every create / status-change / delete. */
function wprp_flush_counts() {
	delete_transient( 'wprp_open_count' );
}
// Any insert/update of a note (create, status flip, edit) busts the count; delete is handled in before_delete_post.
add_action( 'save_post_' . WPRP_CPT, 'wprp_flush_counts' );

// Assigning/reassigning a note to an agent happens via post meta AFTER the post is inserted, so
// save_post alone misses the initial create. Catch the meta write itself to (re)build the brief.
function wprp_brief_on_agent_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
	if ( WPRP_META_AGENT !== $meta_key || WPRP_CPT !== get_post_type( $post_id ) ) {
		return;
	}
	$slug = (string) $meta_value;
	if ( '' !== $slug ) {
		wprp_write_agent_brief( $slug );
	}
}
add_action( 'added_post_meta', 'wprp_brief_on_agent_meta', 10, 4 );
add_action( 'updated_post_meta', 'wprp_brief_on_agent_meta', 10, 4 );

// When an agent-targeted note (or a reply to one) changes, refresh that agent's JSON brief.
add_action(
	'save_post_' . WPRP_CPT,
	function ( $post_id ) {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}
		// A reply (post_parent != 0) belongs to its parent note; the brief embeds replies.
		$owner = ( (int) $post->post_parent !== 0 ) ? (int) $post->post_parent : (int) $post_id;
		$slug  = (string) get_post_meta( $owner, WPRP_META_AGENT, true );
		if ( '' !== $slug ) {
			wprp_write_agent_brief( $slug );
		}
	}
);

/**
 * Lightweight priming payload embedded in data-cfg so a Dev-Mode page load can set the FAB
 * badge + place element pins WITHOUT fetching the full notes payload (bodies, replies,
 * screenshots, the reply N+1). The heavy GET /notes is deferred to the first panel open.
 * Only OPEN top-level notes for this view are counted; only anchored ones become pins.
 */
function wprp_priming_data( $keys ) {
	$notes = wprp_get_notes_for_context( $keys, 'any' );
	$types = wprp_note_types();
	$open  = 0;
	$pins  = array();
	foreach ( $notes as $n ) {
		$key = wprp_status_key( $n->post_status );
		if ( 'resolved' === $key ) {
			continue; // resolved notes: no pin, not in the open count
		}
		if ( 'open' === $key ) {
			$open++;
		}
		$anchor = (string) get_post_meta( $n->ID, WPRP_META_ANCHOR, true );
		if ( '' === $anchor ) {
			continue;
		}
		$type   = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
		$pins[] = array(
			'id'        => (int) $n->ID,
			'anchor'    => $anchor,
			'statusKey' => $key,
			'resolved'  => false,
			'typeLabel' => isset( $types[ $type ] ) ? $types[ $type ] : $types['note'],
			'body'      => wp_trim_words( wp_strip_all_tags( $n->post_content ), 14, '...' ),
		);
	}
	return array(
		'openCount' => $open,
		'pins'      => $pins,
	);
}

/**
 * Shape a note post into the plain array the JS + REST consume. Pass $replies (a WP_Post[]
 * from wprp_get_replies_for()) when shaping many notes to avoid a per-note reply query;
 * leave it null for a single note (falls back to wprp_get_replies()).
 */
function wprp_note_to_array( $note, $replies = null ) {
	$type     = (string) get_post_meta( $note->ID, WPRP_META_TYPE, true );
	$types    = wprp_note_types();
	$author   = get_userdata( $note->post_author );
	$priority = (string) get_post_meta( $note->ID, WPRP_META_PRIORITY, true );
	$prios    = wprp_priorities();
	$priority = isset( $prios[ $priority ] ) ? $priority : 'normal';
	$severity = (string) get_post_meta( $note->ID, WPRP_META_SEVERITY, true );
	$sevs     = wprp_severities();
	$severity = isset( $sevs[ $severity ] ) ? $severity : '';
	$assignee = (int) get_post_meta( $note->ID, WPRP_META_ASSIGNEE, true );
	$au       = $assignee ? get_userdata( $assignee ) : false;
	$rby      = (int) get_post_meta( $note->ID, WPRP_META_RESOLVED_BY, true );
	$rbu      = $rby ? get_userdata( $rby ) : false;
	$level    = (string) get_post_meta( $note->ID, WPRP_META_LEVEL, true );
	$level    = in_array( $level, array( 'template', 'global' ), true ) ? $level : 'page';
	return array(
		'id'         => (int) $note->ID,
		'body'       => wpautop( wprp_kses_note( $note->post_content ) ),
		'raw'        => $note->post_content, // unformatted, for the edit textarea
		'type'       => $type,
		'typeLabel'  => isset( $types[ $type ] ) ? $types[ $type ] : $types['note'],
		'typeColor'  => wprp_note_type_color( $type ),
		'status'     => $note->post_status,
		'statusKey'  => wprp_status_key( $note->post_status ),
		'statusLabel' => wprp_statuses()[ wprp_status_key( $note->post_status ) ],
		'resolved'   => WPRP_STATUS_DONE === $note->post_status,
		'author'     => $author ? $author->display_name : __( 'Unknown', 'wp-red-pen' ),
		'date'       => get_the_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $note ),
		'createdAt'  => get_the_time( 'c', $note ), // ISO 8601, for cross-tool consumers (the Hub); 'date' stays localized for our own UI
		'resolvedAt' => (string) get_post_meta( $note->ID, WPRP_META_RESOLVED_AT, true ),
		'resolvedBy' => $rbu ? $rbu->display_name : '',
		'target'     => (int) get_post_meta( $note->ID, WPRP_META_TARGET, true ),
		'shot'       => wprp_shot_url( (string) get_post_meta( $note->ID, WPRP_META_SHOT, true ) ),
		'ctx'         => (string) get_post_meta( $note->ID, WPRP_META_CTX, true ),
		'priority'    => $priority,
		'priorityLabel' => isset( $prios[ $priority ] ) ? $prios[ $priority ] : $prios['normal'],
		'severity'    => $severity,
		'severityLabel' => '' !== $severity ? $sevs[ $severity ] : '',
		'assignee'    => $assignee,
		'assigneeName' => $au ? $au->display_name : '',
		'anchor'      => (string) get_post_meta( $note->ID, WPRP_META_ANCHOR, true ),
		'level'       => $level,
		'ctxKey'      => (string) get_post_meta( $note->ID, WPRP_META_CTXKEY, true ),
		'ctxLabel'    => (string) get_post_meta( $note->ID, WPRP_META_CTXLABEL, true ),
		'clientVisible' => wprp_note_is_client_visible( $note->ID ),
		'viaReview'   => '1' === (string) get_post_meta( $note->ID, WPRP_META_VIA_REVIEW, true ),
		'agent'       => (string) get_post_meta( $note->ID, WPRP_META_AGENT, true ),
		'agentLabel'  => wprp_agent_label( (string) get_post_meta( $note->ID, WPRP_META_AGENT, true ) ),
		'codeScope'   => (string) get_post_meta( $note->ID, WPRP_META_CODESCOPE, true ),
		'replies'     => array_map( 'wprp_reply_to_array', is_array( $replies ) ? $replies : wprp_get_replies( $note->ID ) ),
	);
}

/**
 * Shape a note into the RESTRICTED array a reviewer (token-bearing anonymous client)
 * is allowed to see. This is the security boundary for read: it deliberately omits
 * every internal-only field - assignee, agent, code scope, screenshot/shot url, the
 * dev author identity, ctx (browser/OS), and the raw edit body. Reviewers get only
 * what they need to avoid duplicate feedback: the rendered body, type, priority,
 * status, anchor, the page url, and the reviewer's own display name when present.
 * Replies are stripped to body + reviewer name + date (no dev author identity).
 *
 * @param WP_Post        $note    The note post.
 * @param WP_Post[]|null $replies Pre-fetched replies, or null to query.
 * @return array
 */
/**
 * Turn the stored ISO 8601 resolved-at stamp into the site's own date format for display.
 * Returns '' when there is no stamp (notes resolved before the stamp existed).
 *
 * @param string $iso ISO 8601 timestamp, UTC.
 * @return string
 */
function wprp_format_resolved_date( $iso ) {
	$iso = trim( (string) $iso );
	if ( '' === $iso ) {
		return '';
	}
	$ts = strtotime( $iso );
	return $ts ? date_i18n( (string) get_option( 'date_format' ), $ts ) : '';
}

function wprp_note_to_array_reviewer( $note, $replies = null ) {
	$type     = (string) get_post_meta( $note->ID, WPRP_META_TYPE, true );
	$types    = wprp_note_types();
	$priority = (string) get_post_meta( $note->ID, WPRP_META_PRIORITY, true );
	$prios    = wprp_priorities();
	$priority = isset( $prios[ $priority ] ) ? $priority : 'normal';
	$level    = (string) get_post_meta( $note->ID, WPRP_META_LEVEL, true );
	$level    = in_array( $level, array( 'template', 'global' ), true ) ? $level : 'page';
	$reviewer = (string) get_post_meta( $note->ID, WPRP_META_REVIEWER, true );
	$target   = (int) get_post_meta( $note->ID, WPRP_META_TARGET, true );
	$rep_list = is_array( $replies ) ? $replies : wprp_get_replies( $note->ID );
	return array(
		'id'            => (int) $note->ID,
		'body'          => wpautop( wprp_kses_note( $note->post_content ) ),
		'type'          => $type,
		'typeLabel'     => isset( $types[ $type ] ) ? $types[ $type ] : $types['note'],
		'typeColor'     => wprp_note_type_color( $type ),
		'status'        => $note->post_status,
		'statusKey'     => wprp_status_key( $note->post_status ),
		'statusLabel'   => wprp_statuses()[ wprp_status_key( $note->post_status ) ],
		'resolved'      => WPRP_STATUS_DONE === $note->post_status,
		'priority'      => $priority,
		'priorityLabel' => isset( $prios[ $priority ] ) ? $prios[ $priority ] : $prios['normal'],
		'anchor'        => (string) get_post_meta( $note->ID, WPRP_META_ANCHOR, true ),
		'level'         => $level,
		// Their own reports come back at any status, so the client can see a thing was fixed
		// instead of guessing whether it ever saved. Resolved-at is a plain date, no dev identity.
		'mine'          => wprp_reviewer_owns_note( $note->ID ),
		'resolvedAt'    => ( WPRP_STATUS_DONE === $note->post_status ) ? wprp_format_resolved_date( (string) get_post_meta( $note->ID, WPRP_META_RESOLVED_AT, true ) ) : '',
		// A reviewer-attributed display name only - never a logged-in dev's identity.
		'author'        => '' !== $reviewer ? $reviewer : __( 'Reviewer', 'wp-red-pen' ),
		'date'          => get_the_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $note ),
		'url'           => $target ? get_permalink( $target ) : (string) get_post_meta( $note->ID, WPRP_META_URL, true ),
		'replies'       => array_map( 'wprp_reply_to_array_reviewer', $rep_list ),
	);
}

/** Strip a reply for reviewer eyes: body + reviewer-attributed name + date only (no dev author identity). */
function wprp_reply_to_array_reviewer( $reply ) {
	$reviewer = (string) get_post_meta( $reply->ID, WPRP_META_REVIEWER, true );
	return array(
		'id'     => (int) $reply->ID,
		'body'   => wpautop( wprp_kses_note( $reply->post_content ) ),
		'author' => '' !== $reviewer ? $reviewer : __( 'Reviewer', 'wp-red-pen' ),
		'date'   => get_the_time( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $reply ),
	);
}

/**
 * Reviewer-scoped priming payload for the page config: OPEN top-level notes for the
 * current view ONLY, each stripped to the reviewer-safe shape, plus the open count
 * and anchored pins. Mirrors wprp_priming_data() but never leaks dev-only fields.
 *
 * @param string[] $keys Context keys for the current view (page + template).
 * @return array
 */
function wprp_priming_data_reviewer( $keys ) {
	// Same gate as the GET, applied to the same candidate set - the priming payload is a
	// read path like any other, so it must not be able to show a note the GET would hide.
	$notes = wprp_reviewer_visible_notes( wprp_get_notes_for_context( $keys, 'any' ) );
	$types = wprp_note_types();
	$pins  = array();
	$open  = 0;
	foreach ( $notes as $n ) {
		$sk = wprp_status_key( $n->post_status );
		if ( 'open' === $sk ) {
			++$open;
		}
		$anchor = (string) get_post_meta( $n->ID, WPRP_META_ANCHOR, true );
		if ( '' === $anchor || 'resolved' === $sk ) {
			continue; // resolved notes keep their entry in the list but drop their map pin
		}
		$type   = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
		$pins[] = array(
			'id'        => (int) $n->ID,
			'anchor'    => $anchor,
			'statusKey' => $sk,
			'resolved'  => false,
			'typeLabel' => isset( $types[ $type ] ) ? $types[ $type ] : $types['note'],
			'body'      => wp_trim_words( wp_strip_all_tags( $n->post_content ), 14, '...' ),
		);
	}
	return array(
		'openCount' => $open,
		'pins'      => $pins,
	);
}

// ---------------------------------------------------------------------------
// Red Pen Hub - push this site's notes to the local combined board (PRO).
// Outbound only, non-blocking, dev-only. The Hub stores what we send; this site
// stays the source of truth. No-op unless a Hub URL + token are configured.
// ---------------------------------------------------------------------------
function wprp_push_to_hub( $blocking = false ) {
	$url   = trim( (string) get_option( WPRP_HUB_URL_OPT, '' ) );
	$token = trim( (string) get_option( WPRP_HUB_TOKEN_OPT, '' ) );
	if ( '' === $url || '' === $token ) {
		return $blocking ? new WP_Error( 'wprp_hub_unconfigured', __( 'Hub URL or token is missing.', 'wp-red-pen' ) ) : null;
	}
	$posts = get_posts(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => wprp_all_statuses(),
			'post_parent'    => 0,
			'posts_per_page' => -1,
		)
	);
	$notes = array();
	foreach ( $posts as $p ) {
		$target    = (int) get_post_meta( $p->ID, WPRP_META_TARGET, true );
		$type      = (string) get_post_meta( $p->ID, WPRP_META_TYPE, true );
		$statuskey = wprp_status_key( $p->post_status );
		$rby_id    = (int) get_post_meta( $p->ID, WPRP_META_RESOLVED_BY, true );
		$rby_u     = $rby_id ? get_userdata( $rby_id ) : false;
		$asg_id    = (int) get_post_meta( $p->ID, WPRP_META_ASSIGNEE, true );
		$asg_u     = $asg_id ? get_userdata( $asg_id ) : false;
		$notes[]   = array(
			'id'           => (int) $p->ID,
			'body'         => wp_strip_all_tags( $p->post_content ),
			'type'         => $type,
			'typeColor'    => wprp_note_type_color( $type ),
			'priority'     => (string) get_post_meta( $p->ID, WPRP_META_PRIORITY, true ),
			// Keep the push payload in step with the pull shape (wprp_note_to_array) so a
			// push-connected site shows the same severity / timestamps / identity on the board.
			'severity'     => (string) get_post_meta( $p->ID, WPRP_META_SEVERITY, true ),
			// Emit the canonical status key ('open' / 'progress' / 'resolved') the same way the
			// pull path and every other surface do, so Hub in-progress write-backs reconcile cleanly.
			'status'       => $statuskey,
			'url'          => $target ? get_permalink( $target ) : home_url( '/' ),
			'anchor'       => (string) get_post_meta( $p->ID, WPRP_META_ANCHOR, true ),
			'createdAt'    => get_post_time( 'c', true, $p ),
			'resolvedAt'   => (string) get_post_meta( $p->ID, WPRP_META_RESOLVED_AT, true ),
			'resolvedBy'   => $rby_u ? $rby_u->display_name : '',
			'assigneeName' => $asg_u ? $asg_u->display_name : '',
		);
	}
	$project = (string) get_bloginfo( 'name' );
	if ( '' === $project ) {
		$project = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	}
	$res = wp_remote_post(
		rtrim( $url, '/' ) . '/api/ingest',
		array(
			'timeout'  => $blocking ? 8 : 4,
			'blocking' => (bool) $blocking,
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'token'   => $token,
					'project' => $project,
					'surface' => 'wordpress',
					'notes'   => $notes,
				)
			),
		)
	);
	return $blocking ? $res : null;
}

// Push once per request (batched on shutdown) whenever notes change.
function wprp_mark_dirty_for_hub( $post_id = 0 ) {
	if ( $post_id && ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) ) {
		return;
	}
	$GLOBALS['wprp_hub_dirty'] = true;
}
add_action( 'save_post_' . WPRP_CPT, 'wprp_mark_dirty_for_hub' );
add_action( 'before_delete_post', function ( $pid ) { if ( WPRP_CPT === get_post_type( $pid ) ) { wprp_mark_dirty_for_hub(); } } );
add_action( 'wp_trash_post', function ( $pid ) { if ( WPRP_CPT === get_post_type( $pid ) ) { wprp_mark_dirty_for_hub(); } } );
add_action( 'shutdown', function () { if ( ! empty( $GLOBALS['wprp_hub_dirty'] ) ) { wprp_push_to_hub(); } } );

// ---------------------------------------------------------------------------
// One-time data migration: backfill the page/template context on legacy notes
// ---------------------------------------------------------------------------
add_action(
	'init',
	function () {
		if ( version_compare( (string) get_option( WPRP_DBVER_OPT, '0' ), '0.5.0', '>=' ) ) {
			return;
		}
		// Legacy notes were created before the context model: they have a numeric
		// _wprp_target but no _wprp_ctx_key. Backfill them as page-level post notes.
		$legacy = get_posts(
			array(
				'post_type'      => WPRP_CPT,
				'post_status'    => array( WPRP_STATUS_OPEN, WPRP_STATUS_DONE ),
				'post_parent'    => 0,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					array(
						'key'     => WPRP_META_CTXKEY,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);
		foreach ( $legacy as $nid ) {
			$t = (int) get_post_meta( $nid, WPRP_META_TARGET, true );
			update_post_meta( $nid, WPRP_META_LEVEL, 'page' );
			if ( $t > 0 ) {
				update_post_meta( $nid, WPRP_META_CTXKEY, 'post:' . $t );
				update_post_meta( $nid, WPRP_META_CTXLABEL, mb_substr( (string) get_the_title( $t ), 0, 200 ) );
			}
		}
		update_option( WPRP_DBVER_OPT, '0.5.0', false ); // tiny option, no need to autoload on every request
	},
	20
);

// ---------------------------------------------------------------------------
// REST API - the front-end button talks to this
// ---------------------------------------------------------------------------
add_action(
	'rest_api_init',
	function () {
		// Dev-only routes (edit / status / delete) keep this strict callback.
		$perm = function () {
			return wprp_user_can();
		};
		// Create + reply also accept a valid reviewer token (logged-in dev OR token-bearer).
		// The token is read from the X-WPRP-Review-Token header inside wprp_can_review();
		// it is NOT a wp_rest nonce, which is why these routes are intentionally nonce-free
		// for the reviewer path. wprp_create_note / wprp_create_reply force the safe shape.
		$perm_contribute = function () {
			return wprp_can_contribute();
		};

		register_rest_route(
			WPRP_REST_NS,
			'/notes',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $perm_contribute,
					'callback'            => function ( $req ) {
						// A reviewer (valid token, NOT a logged-in dev) gets a hard-scoped, stripped read:
						// OPEN notes for the requested page/context only, never the agent view, never a
						// target/status they pick, and every note shaped by wprp_note_to_array_reviewer().
						$reviewer = ! wprp_user_can() && wprp_can_review();
						$target = (int) $req->get_param( 'target' );
						$status = (string) $req->get_param( 'status' );
						$keys   = (string) $req->get_param( 'keys' );
							$agent  = (string) $req->get_param( 'agent' );
						if ( $reviewer ) {
							// Pass the requested keys through the reviewer allow-list server-side: no
							// site-wide notes, no notes on non-public (draft/private) posts, whatever
							// keys the client asks for. Then run EVERY candidate through the single
							// visibility gate, which drops internal notes and lets the token holder
							// see their own reports at any status.
							$safe  = wprp_reviewer_safe_keys( explode( ',', $keys ) );
							$notes = $safe ? wprp_reviewer_visible_notes( wprp_get_notes_for_context( $safe, 'any' ) ) : array();
						} else {
							// scope=all is the Hub's cross-project pull: every note on the site,
							// not just the target=0 site-wide ones. agent/keys/target paths unchanged.
							$scope = (string) $req->get_param( 'scope' );
							if ( '' !== $agent ) {
								$notes = wprp_get_notes_for_agent( $agent, $status ? $status : 'any' );
							} elseif ( '' !== $keys ) {
								$notes = wprp_get_notes_for_context( explode( ',', $keys ), $status ? $status : 'any' );
							} elseif ( 'all' === $scope ) {
								$notes = wprp_get_all_notes( $status ? $status : 'any' );
							} else {
								$notes = wprp_get_notes_for( $target, $status ? $status : 'any' );
							}
						}
						// Batch the replies (one query for all notes, not one per note) and prime
							// the user cache so author/assignee lookups don't each hit the DB.
							$rep_map = wprp_get_replies_for( wp_list_pluck( $notes, 'ID' ) );
							$uids    = array();
							foreach ( $notes as $gnote ) {
								$uids[] = (int) $gnote->post_author;
								$uids[] = (int) get_post_meta( $gnote->ID, WPRP_META_ASSIGNEE, true );
							}
							foreach ( $rep_map as $rep_list ) {
								foreach ( $rep_list as $rep ) {
									$uids[] = (int) $rep->post_author;
								}
							}
							$uids = array_values( array_filter( array_unique( $uids ) ) );
							if ( $uids ) {
								cache_users( $uids );
							}
							$out = array();
							foreach ( $notes as $gnote ) {
								$replies = isset( $rep_map[ $gnote->ID ] ) ? $rep_map[ $gnote->ID ] : array();
								$out[]   = $reviewer
									? wprp_note_to_array_reviewer( $gnote, $replies )
									: wprp_note_to_array( $gnote, $replies );
							}
							return rest_ensure_response( $out );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $perm_contribute,
					'callback'            => function ( $req ) {
						$id = wprp_create_note(
							(int) $req->get_param( 'target' ),
							(string) $req->get_param( 'body' ),
							(string) $req->get_param( 'type' ),
							(string) $req->get_param( 'url' ),
							(string) $req->get_param( 'shot' ),
							(string) $req->get_param( 'ctx' ),
							(string) $req->get_param( 'priority' ),
							(int) $req->get_param( 'assignee' ),
							(string) $req->get_param( 'anchor' ),
							array(
								'level' => (string) $req->get_param( 'level' ),
								'key'   => (string) $req->get_param( 'ctx_key' ),
								'label' => (string) $req->get_param( 'ctx_label' ),
							),
							(string) $req->get_param( 'agent' ),
							(string) $req->get_param( 'codescope' ),
							(string) $req->get_param( 'reviewer' ),
							(string) $req->get_param( 'severity' ),
							! empty( $req->get_param( 'client_visible' ) )
						);
						if ( is_wp_error( $id ) ) {
							return $id;
						}
						// A reviewer gets the stripped shape back (no dev-only fields ever echoed to them),
						// and only if the same gate that governs the GET says they may see it.
						$reviewer = ! wprp_user_can() && wprp_can_review();
						if ( $reviewer ) {
							$fresh = get_post( $id );
							return rest_ensure_response(
								wprp_reviewer_can_see_note( $fresh ) ? wprp_note_to_array_reviewer( $fresh ) : array( 'id' => (int) $id )
							);
						}
						return rest_ensure_response( wprp_note_to_array( get_post( $id ) ) );
					},
				),
			)
		);

		register_rest_route(
			WPRP_REST_NS,
			'/notes/(?P<id>\d+)/status',
			array(
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => function ( $req ) {
					$status_param = (string) $req->get_param( 'status' );
					$target       = ( '' !== $status_param )
						? wprp_status_const( $status_param )
						: ( $req->get_param( 'resolved' ) ? WPRP_STATUS_DONE : WPRP_STATUS_OPEN );
					$res          = wprp_set_status( (int) $req['id'], $target );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					return rest_ensure_response( wprp_note_to_array( get_post( (int) $req['id'] ) ) );
				},
			)
		);

		register_rest_route(
			WPRP_REST_NS,
			'/notes/(?P<id>\d+)/replies',
			array(
				'methods'             => 'POST',
				'permission_callback' => $perm_contribute,
				'callback'            => function ( $req ) {
					$res = wprp_create_reply( (int) $req['id'], (string) $req->get_param( 'body' ), (string) $req->get_param( 'reviewer' ) );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					// Return the parent note shaped for the caller (stripped for reviewers). The echo
					// re-checks the visibility gate rather than assuming wprp_create_reply()'s own
					// check still holds - a past bug in exactly this spot leaked internal notes.
					$reviewer = ! wprp_user_can() && wprp_can_review();
					$parent   = get_post( (int) $req['id'] );
					if ( $reviewer ) {
						return rest_ensure_response(
							wprp_reviewer_can_see_note( $parent ) ? wprp_note_to_array_reviewer( $parent ) : array( 'id' => (int) $req['id'] )
						);
					}
					return rest_ensure_response( wprp_note_to_array( $parent ) );
				},
			)
		);

		// Update an existing note (edit text / type / priority / assignee / screenshot / pin).
		register_rest_route(
			WPRP_REST_NS,
			'/notes/(?P<id>\d+)',
			array(
				'methods'             => 'POST',
				'permission_callback' => $perm,
				'callback'            => function ( $req ) {
					$res = wprp_update_note(
						(int) $req['id'],
						array(
							'body'          => (string) $req->get_param( 'body' ),
							'type'          => $req->get_param( 'type' ),
							'client_visible' => $req->get_param( 'client_visible' ),
							'priority'      => $req->get_param( 'priority' ),
							'severity'      => $req->get_param( 'severity' ),
							'assignee'      => $req->get_param( 'assignee' ),
							'anchor'        => (string) $req->get_param( 'anchor' ),
							'anchor_remove' => $req->get_param( 'anchor_remove' ),
							'shot'          => (string) $req->get_param( 'shot' ),
							'shot_remove'   => $req->get_param( 'shot_remove' ),
								'level'         => $req->get_param( 'level' ),
								'ctx_key'       => $req->get_param( 'ctx_key' ),
								'ctx_label'     => $req->get_param( 'ctx_label' ),
								'target'        => $req->get_param( 'target' ),
								'agent'         => $req->get_param( 'agent' ),
								'codescope'     => $req->get_param( 'codescope' ),
						)
					);
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					return rest_ensure_response( wprp_note_to_array( get_post( (int) $req['id'] ) ) );
				},
			)
		);
	}
);

// ---------------------------------------------------------------------------
// Admin bar: Dev Mode toggle + open-note badge
// ---------------------------------------------------------------------------
add_action(
	'admin_bar_menu',
	function ( $bar ) {
		if ( ! wprp_user_can() ) {
			return;
		}
		$on    = wprp_devmode_on();
		$count = wprp_open_count();
		$label = sprintf(
			/* translators: %s: On or Off */
			__( 'Red Pen: %s', 'wp-red-pen' ),
			$on ? __( 'On', 'wp-red-pen' ) : __( 'Off', 'wp-red-pen' )
		);
		if ( $count > 0 ) {
			$label .= ' (' . (int) $count . ')';
		}
		$toggle_url = wp_nonce_url( add_query_arg( 'wprp_toggle', '1' ), 'wprp_toggle', 'wprp_nonce' );
		$bar->add_node(
			array(
				'id'    => 'wprp-toggle',
				'title' => '<svg viewBox="0 0 24 24" aria-hidden="true" style="width:17px;height:17px;margin:7px 6px 0 0;vertical-align:top;float:left"><path fill="currentColor" fill-rule="evenodd" d="M12 2l6.5 6.5-4.6 11.8a2 2 0 0 1-3.8 0L5.5 8.5 12 2zM13.7 11.4a1.7 1.7 0 1 1-3.4 0 1.7 1.7 0 1 1 3.4 0zM11.3 4.6h1.4v5h-1.4zM11.6 12.9h.8v6.7h-.8z"/></svg>' . esc_html( $label ),
				'href'  => esc_url( $toggle_url ),
				'meta'  => array( 'title' => __( 'Toggle Red Pen Dev Mode', 'wp-red-pen' ) ),
			)
		);
		$bar->add_node(
			array(
				'parent' => 'wprp-toggle',
				'id'     => 'wprp-repo',
				'title'  => __( 'Open the notes repository', 'wp-red-pen' ),
				'href'   => esc_url( admin_url( 'tools.php?page=wp-red-pen' ) ),
			)
		);
		// Front-end helper only (no on-page pins to jump to in wp-admin), and only when Dev Mode is on.
		if ( $on && ! is_admin() ) {
			$bar->add_node(
				array(
					'parent' => 'wprp-toggle',
					'id'     => 'wprp-jump',
					'title'  => __( 'Jump to next open note', 'wp-red-pen' ),
					'href'   => '#wprp-jump-next',
					'meta'   => array( 'title' => __( 'Scroll to the next open note pinned on this page (shortcut: J)', 'wp-red-pen' ) ),
				)
			);
		}
	},
	80
);

/**
 * Tint the admin-bar Red Pen pen icon with the brand accent red. The core
 * `#wpadminbar .ab-icon::before` rule sets the icon colour directly, so an inline
 * span colour won't win - this higher-specificity rule does. The admin bar renders
 * on the front end too (for logged-in users), so print on both wp_head + admin_head.
 */
function wprp_admin_bar_icon_css() {
	if ( ! wprp_user_can() ) {
		return;
	}
	echo '<style id="wprp-adminbar-css">#wpadminbar #wp-admin-bar-wprp-toggle .ab-icon:before{color:#D32F2F}</style>';
}
add_action( 'admin_head', 'wprp_admin_bar_icon_css' );
add_action( 'wp_head', 'wprp_admin_bar_icon_css' );

/** Handle the admin-bar toggle click (flip the per-user meta, then redirect clean). */
add_action(
	'init',
	function () {
		if ( ! isset( $_GET['wprp_toggle'] ) ) {
			return;
		}
		if ( ! wprp_user_can()
			|| ! isset( $_GET['wprp_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['wprp_nonce'] ) ), 'wprp_toggle' ) ) {
			return;
		}
		$uid = get_current_user_id();
		update_user_meta( $uid, WPRP_USERMETA, get_user_meta( $uid, WPRP_USERMETA, true ) ? '' : '1' );
		wp_safe_redirect( remove_query_arg( array( 'wprp_toggle', 'wprp_nonce' ) ) );
		exit;
	}
);

// ---------------------------------------------------------------------------
// Front-end: the floating button + popout dialog (Dev Mode on, singular views)
// ---------------------------------------------------------------------------
add_action(
	'wp_footer',
	function () {
		// Two doors: a logged-in dev in Dev Mode, OR a token-bearing anonymous reviewer.
		if ( ! wprp_devmode_on() && ! wprp_can_review() ) {
			return;
		}
		// Reviewer = a valid token holder who is NOT a logged-in dev. They get a restricted UI.
		$reviewer = ! wprp_user_can() && wprp_can_review();
		$scope = wprp_current_scope();
		if ( ! in_array( $scope, wprp_show_on(), true ) ) {
			return;
		}
		$ctx = wprp_current_context();
		if ( '' === $ctx['page']['key'] ) {
			return; // a view we can't target (shouldn't happen for enabled scopes)
		}
		$target = (int) $ctx['page']['target'];
		$types = wprp_note_types();
		$opts  = '';
		foreach ( $types as $key => $label ) {
			$opts .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		$prio_opts = '';
		foreach ( wprp_priorities() as $key => $label ) {
			$prio_opts .= '<option value="' . esc_attr( $key ) . '"' . selected( $key, 'normal', false ) . '>' . esc_html( $label ) . '</option>';
		}
		// Severity is optional: an empty first option means "no severity set".
		$sev_opts = '<option value="">' . esc_html__( 'Severity...', 'wp-red-pen' ) . '</option>';
		foreach ( wprp_severities() as $key => $label ) {
			$sev_opts .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		// Assignee + agent option lists are DEV-ONLY: a reviewer never sees the user list or the
		// agent slugs (those are internal surface). Reviewers get an empty agent set and no user_opts.
		$agents    = $reviewer ? array() : wprp_enabled_agents();
		$user_opts = '';
		if ( ! $reviewer ) {
			// Assignee options: Unassigned + humans, plus (when agent feedback is on) an Agents group.
			// Agent options use value "agent:<slug>"; human options are the numeric user id.
			$user_opts = '<option value="0">' . esc_html__( 'Unassigned', 'wp-red-pen' ) . '</option>';
			foreach ( wprp_assignable_users() as $uid => $uname ) {
				$user_opts .= '<option value="' . (int) $uid . '">' . esc_html( $uname ) . '</option>';
			}
			if ( $agents ) {
				$user_opts .= '<optgroup label="' . esc_attr__( 'Agents', 'wp-red-pen' ) . '">';
				foreach ( $agents as $aslug => $alabel ) {
					$user_opts .= '<option value="agent:' . esc_attr( $aslug ) . '">' . esc_html( $alabel ) . '</option>';
				}
				$user_opts .= '</optgroup>';
			}
		}
		// Level options built from the current view: This page + (when distinct) This template.
		// Reviewers only ever file page-level notes (their targeting is clamped server-side too);
		// the template + site-wide levels are dev-only.
		$level_opts = '<option value="page">' . esc_html( $ctx['page']['label'] ) . '</option>';
		if ( ! $reviewer && '' !== $ctx['template']['key'] ) {
			$level_opts .= '<option value="template">' . esc_html( $ctx['template']['label'] ) . '</option>';
		}
		if ( ! $reviewer ) {
			$level_opts .= '<option value="global">' . esc_html( $ctx['global']['label'] ) . '</option>';
		}
		if ( $reviewer ) {
			// RESTRICTED reviewer config: no wp_rest nonce (reviewers have none - the JS sends the
			// token header instead), reviewer-scoped + stripped priming, and a reviewer flag the JS
			// reads to hide every dev-only control. No user list, agents, dark toggle, or repo link.
			$cfg = wp_json_encode(
				array(
					'root'        => esc_url_raw( rest_url( WPRP_REST_NS ) ),
					'nonce'       => '',
					'reviewer'    => true,
					'reviewToken' => wprp_current_review_raw(),
					'url'         => esc_url_raw( home_url( add_query_arg( array() ) ) ),
					'page'        => $ctx['page'],     // { key, label, target }
					'template'    => $ctx['template'], // { key, label }
					// No 'global' for reviewers: they never see or file site-wide notes.
					'priming'     => wprp_priming_data_reviewer( array_values( array_filter( array( $ctx['page']['key'], $ctx['template']['key'] ) ) ) ),
				)
			);
		} else {
		$cfg = wp_json_encode(
			array(
				'root'     => esc_url_raw( rest_url( WPRP_REST_NS ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'url'      => esc_url_raw( home_url( add_query_arg( array() ) ) ),
				'page'     => $ctx['page'],     // { key, label, target }
				'template' => $ctx['template'], // { key, label }
				'global'   => $ctx['global'],   // { key, label } - site-wide, every view
				'priming'  => wprp_priming_data( array_values( array_filter( array( $ctx['page']['key'], $ctx['template']['key'], $ctx['global']['key'] ) ) ) ),
			)
		);
		}
		// Dark mode is a dev display preference; a reviewer always gets the default light panel.
		$root_class = ( ! $reviewer && get_option( WPRP_DARK_OPT ) ) ? ' class="wprp-dark"' : '';
		?>
		<div id="wprp-root"<?php echo $root_class; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static literal ?> data-cfg='<?php echo esc_attr( $cfg ); ?>'>
			<button type="button" id="wprp-fab" aria-expanded="false" aria-haspopup="dialog" aria-controls="wprp-panel" aria-label="<?php esc_attr_e( 'Red Pen notes', 'wp-red-pen' ); ?>" title="<?php esc_attr_e( 'Red Pen notes', 'wp-red-pen' ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true" class="wprp-nib"><path fill="currentColor" fill-rule="evenodd" d="M12 2l6.5 6.5-4.6 11.8a2 2 0 0 1-3.8 0L5.5 8.5 12 2zM13.7 11.4a1.7 1.7 0 1 1-3.4 0 1.7 1.7 0 1 1 3.4 0zM11.3 4.6h1.4v5h-1.4zM11.6 12.9h.8v6.7h-.8z"/></svg>
				<span id="wprp-fab-count" class="wprp-count" aria-hidden="true" hidden></span>
			</button>
			<section id="wprp-panel" hidden tabindex="-1" role="dialog" aria-label="<?php esc_attr_e( 'Red Pen notes', 'wp-red-pen' ); ?>">
				<div id="wprp-resize" class="wprp-resize" role="separator" tabindex="0" aria-orientation="vertical" aria-valuemin="300" aria-valuenow="420" aria-label="<?php esc_attr_e( 'Resize the panel (arrow keys to widen or narrow)', 'wp-red-pen' ); ?>" title="<?php esc_attr_e( 'Drag or use arrow keys to resize', 'wp-red-pen' ); ?>"></div>
				<header class="wprp-head">
					<svg viewBox="0 0 24 24" aria-hidden="true" style="width:15px;height:15px;flex:none"><path fill="currentColor" fill-rule="evenodd" d="M12 2l6.5 6.5-4.6 11.8a2 2 0 0 1-3.8 0L5.5 8.5 12 2zM13.7 11.4a1.7 1.7 0 1 1-3.4 0 1.7 1.7 0 1 1 3.4 0zM11.3 4.6h1.4v5h-1.4zM11.6 12.9h.8v6.7h-.8z"/></svg>
					<strong><?php esc_html_e( 'Red Pen', 'wp-red-pen' ); ?></strong>
					<span class="wprp-page"><?php echo esc_html( $ctx['page']['label'] ); ?></span>
					<?php if ( ! $reviewer ) : ?>
					<a class="wprp-repo-link" href="<?php echo esc_url( admin_url( 'tools.php?page=wp-red-pen' ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Open the notes repository', 'wp-red-pen' ); ?>" aria-label="<?php esc_attr_e( 'Open the notes repository', 'wp-red-pen' ); ?>"><span class="dashicons dashicons-list-view" aria-hidden="true"></span></a>
					<?php endif; ?>
						<button type="button" class="wprp-x" id="wprp-close" aria-label="<?php esc_attr_e( 'Close', 'wp-red-pen' ); ?>">&times;</button>
				</header>
				<div class="wprp-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter notes', 'wp-red-pen' ); ?>">
						<button type="button" class="wprp-tab wprp-active" id="wprp-tab-open" role="tab" aria-selected="true" aria-controls="wprp-list"><?php esc_html_e( 'Open', 'wp-red-pen' ); ?></button>
						<button type="button" class="wprp-tab" id="wprp-tab-progress" role="tab" aria-selected="false" aria-controls="wprp-list"><?php esc_html_e( 'In Progress', 'wp-red-pen' ); ?></button>
						<button type="button" class="wprp-tab" id="wprp-tab-resolved" role="tab" aria-selected="false" aria-controls="wprp-list"><?php esc_html_e( 'Resolved', 'wp-red-pen' ); ?></button>
					</div>
					<div id="wprp-list" class="wprp-list" role="tabpanel" aria-live="polite" aria-busy="true"><p class="wprp-muted"><?php esc_html_e( 'Loading notes...', 'wp-red-pen' ); ?></p></div>
				<form id="wprp-form" class="wprp-form">
					<div class="wprp-formrow">
						<select id="wprp-type" aria-label="<?php esc_attr_e( 'Note type', 'wp-red-pen' ); ?>"><?php echo $opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
						<button type="button" id="wprp-more-toggle" class="wprp-more-toggle" aria-expanded="false" aria-controls="wprp-more"><?php esc_html_e( 'More', 'wp-red-pen' ); ?></button>
					</div>
					<div class="wprp-more" id="wprp-more" hidden>
						<select id="wprp-priority" aria-label="<?php esc_attr_e( 'Priority', 'wp-red-pen' ); ?>"><?php echo $prio_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
						<?php if ( ! $reviewer ) : ?>
						<select id="wprp-severity" aria-label="<?php esc_attr_e( 'Severity', 'wp-red-pen' ); ?>"><?php echo $sev_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
						<?php endif; ?>
						<?php if ( ! $reviewer ) : ?>
						<select id="wprp-assignee" aria-label="<?php esc_attr_e( 'Assign to', 'wp-red-pen' ); ?>"><?php echo $user_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
						<?php endif; ?>
						<label class="wprp-levellabel"><?php esc_html_e( 'Reporting level', 'wp-red-pen' ); ?>
							<select id="wprp-level" aria-label="<?php esc_attr_e( 'Reporting level', 'wp-red-pen' ); ?>"><?php echo $level_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
						</label>
						<?php if ( ! $reviewer ) : ?>
						<label class="wprp-cvlabel" title="<?php esc_attr_e( 'Off by default. Your notes stay internal unless you share them.', 'wp-red-pen' ); ?>"><input type="checkbox" id="wprp-client-visible"> <?php esc_html_e( 'Visible to client reviewers', 'wp-red-pen' ); ?></label>
						<?php endif; ?>
						<?php if ( $agents ) : ?>
						<input type="text" id="wprp-codescope" maxlength="300" placeholder="<?php esc_attr_e( 'Code scope for agent (optional) e.g. includes/foo.php:42', 'wp-red-pen' ); ?>" aria-label="<?php esc_attr_e( 'Code scope', 'wp-red-pen' ); ?>">
						<?php endif; ?>
					</div>
					<textarea id="wprp-body" rows="3" placeholder="<?php esc_attr_e( 'Add a note, question, or idea...', 'wp-red-pen' ); ?>" required></textarea>
					<div class="wprp-shotrow">
						<button type="button" id="wprp-shot-btn" class="wprp-shotbtn"><span class="dashicons dashicons-camera"></span> <?php esc_html_e( 'Screenshot', 'wp-red-pen' ); ?></button>
						<div id="wprp-shot-preview" class="wprp-shot-preview" hidden>
							<img id="wprp-shot-thumb" alt="<?php esc_attr_e( 'Screenshot preview', 'wp-red-pen' ); ?>">
							<button type="button" id="wprp-shot-clear" class="wprp-shot-clear" aria-label="<?php esc_attr_e( 'Remove screenshot', 'wp-red-pen' ); ?>">&times;</button>
						</div>
					</div>
					<div class="wprp-pinrow">
						<button type="button" id="wprp-pin-btn" class="wprp-shotbtn"><span class="dashicons dashicons-location"></span> <?php esc_html_e( 'Pin to element', 'wp-red-pen' ); ?></button>
						<span id="wprp-pin-info" class="wprp-pin-info" hidden><span id="wprp-pin-label"></span><button type="button" id="wprp-pin-clear" class="wprp-pin-clear" aria-label="<?php esc_attr_e( 'Remove pin', 'wp-red-pen' ); ?>">&times;</button></span>
					</div>
					<div class="wprp-editbar" id="wprp-editbar" hidden>
						<span><?php esc_html_e( 'Editing note', 'wp-red-pen' ); ?></span>
						<button type="button" id="wprp-edit-cancel" class="wprp-edit-cancel"><?php esc_html_e( 'Cancel', 'wp-red-pen' ); ?></button>
					</div>
					<button type="submit" class="wprp-submit"><?php esc_html_e( 'Add note', 'wp-red-pen' ); ?></button>
				</form>
			</section>
		</div>
		<?php
		wprp_print_frontend_assets();
	}
);

/** Inline CSS + JS for the floating widget (single-file family pattern). */
function wprp_print_frontend_assets() {
	// Custom pin colour ('' = use the theme red default). Emitted as a rule on #wprp-pinlayer
	// below, because the pin layer is appended to <body>, OUTSIDE #wprp-root - so a custom
	// property set on the root never cascades to the markers.
	$wprp_pin_color = sanitize_hex_color( (string) get_option( WPRP_PINCOLOR_OPT, '' ) );
	?>
	<style id="wprp-css">
		/* ===== Host-page armor =====
		   Red Pen renders inside the site's own page, so theme rules that style bare
		   :is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) elements (button{}, :is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) p{}, :is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) a{}, *:focus-visible) - often with !important -
		   match our DOM too. Namespaced classes can't block element selectors, so the
		   defense is two-layered: every declaration in this sheet carries !important
		   (the widget wins any property it declares), and the two rules below pin the
		   typography/reset properties the component rules don't declare.
		   The whole sheet lives one ID tier up: every component rule is scoped under
		   its mount-root ID (:is(#wprp-root,...) .wprp-x = 1,1,0) and the armor rule
		   below sits just under them at 1,0,1 - so even attribute-qualified theme
		   :is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) rules like button[type="submit"]{...!important} (0,1,1, which out-ranks a
		   bare .wprp-* class) lose to both layers. Accidental site selectors never
		   contain our IDs.
		   Do not add background or display here - background is animated (wprp-flash)
		   and display is toggled from JS. Known residual gap: rem units track the
		   site's html font-size (e.g. the 62.5% trick shrinks the panel). */
		#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif!important;font-size:16px!important;line-height:1.45!important;letter-spacing:normal!important;text-transform:none!important;text-align:left!important;text-shadow:none!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) :is(a,button,select,textarea,label,p,span,div,ul,ol,li,img,svg,h1,h2,h3,h4,h5,h6){transform:none!important;font-family:inherit!important;font-size:inherit!important;font-weight:inherit!important;font-style:inherit!important;line-height:inherit!important;color:inherit!important;letter-spacing:inherit!important;text-transform:inherit!important;text-shadow:none!important;text-decoration:none!important;box-shadow:none!important;border:none!important;margin:0!important;padding:0!important;min-width:0!important;min-height:0!important;float:none!important;list-style:none!important}
		/* the armor's font-family/weight inherit would defeat WP's dashicons.css (normal weight), so re-assert the icon font */
		#wprp-root .dashicons{font-family:dashicons!important;font-weight:400!important;font-style:normal!important;line-height:1!important}
		#wprp-root{--wprp-red:#D32F2F!important;--wprp-red-dark:#B71C1C!important;--wprp-accent:#FF5252!important;--wprp-ink:#1E2225!important;--wprp-gray:#3A3A3C!important;position:fixed!important;right:20px!important;bottom:20px!important;z-index:99990!important;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif!important}
			/* The HTML [hidden] attribute is the weakest possible style, so an id/class rule that
			   sets display silently defeats it and the toggle does nothing. The doubled root ID
			   puts this at 2,1,0 - above every scoped component rule, including the (2,0,0)
			   #wprp-root #wprp-panel{display:flex} that panel.hidden must override. */
			#wprp-root#wprp-root [hidden]{display:none!important}
			/* Dark mode (Display settings toggle) - front-end panel only; scoped to #wprp-root.wprp-dark. */
			#wprp-root.wprp-dark #wprp-panel{background:#23272b!important;color:#e6e9ec!important;border-color:#3a3f44!important}
			/* background stays normal-weight: the wprp-flash keyframes animate background, and animations lose to !important declarations */
			#wprp-root.wprp-dark .wprp-note{background:#2a2f34;border-color:#3a3f44!important}
			#wprp-root.wprp-dark .wprp-muted,#wprp-root.wprp-dark .wprp-meta,#wprp-root.wprp-dark .wprp-ctx,#wprp-root.wprp-dark .wprp-reply-meta,#wprp-root.wprp-dark .wprp-assignee,#wprp-root.wprp-dark .wprp-levellabel{color:#9aa0a6!important}
			#wprp-root.wprp-dark .wprp-form{background:#1f2327!important;border-top-color:#3a3f44!important}
			#wprp-root.wprp-dark .wprp-form select,#wprp-root.wprp-dark .wprp-form textarea,#wprp-root.wprp-dark .wprp-replytext{background:#1a1d20!important;color:#e6e9ec!important;border-color:#3a3f44!important}
			#wprp-root.wprp-dark .wprp-reply{background:#1f2327!important}
			#wprp-root.wprp-dark .wprp-replies{border-top-color:#3a3f44!important}
			#wprp-root.wprp-dark .wprp-resolve,#wprp-root.wprp-dark .wprp-locate,#wprp-root.wprp-dark .wprp-edit,#wprp-root.wprp-dark .wprp-replysend,#wprp-root.wprp-dark .wprp-shotbtn{background:#2a2f34!important;color:#aeb4ba!important;border-color:#3a3f44!important}
			#wprp-root.wprp-dark .wprp-prio-normal,#wprp-root.wprp-dark .wprp-note .wprp-level{background:#3a3f44!important;color:#cfd4d8!important;border-color:#4a4f55!important}
			#wprp-root.wprp-dark .wprp-tabs{background:#23272b!important;border-bottom-color:#3a3f44!important}
			#wprp-root.wprp-dark .wprp-status,#wprp-root.wprp-dark .wprp-more-toggle,#wprp-root.wprp-dark .wprp-shotbtn{background:#1a1d20!important;color:#cfd4d8!important;border-color:#3a3f44!important}
			#wprp-root.wprp-dark .wprp-pin-info{background:#2a2f34!important;color:#cfd4d8!important;border-color:#3a3f44!important}
			#wprp-root.wprp-dark .wprp-editbar{background:#3a2526!important;border-color:#6b3a3c!important;color:#ff8a80!important}
			#wprp-root.wprp-dark .wprp-cvlabel,#wprp-root.wprp-dark .wprp-levellabel{color:#aeb4ba!important}
		#wprp-root #wprp-fab{width:52px!important;height:52px!important;border-radius:50%!important;border:none!important;background:var(--wprp-red)!important;color:#fff!important;cursor:pointer!important;box-shadow:0 4px 14px rgba(211,47,47,.45)!important;display:flex!important;align-items:center!important;justify-content:center!important;position:relative!important;transition:transform .12s,background .12s!important}
		#wprp-root #wprp-fab:hover{transform:translateY(-2px)!important;background:var(--wprp-red-dark)!important}
		#wprp-fab .wprp-nib{width:25px!important;height:25px!important;display:block!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-count{position:absolute!important;top:-4px!important;right:-4px!important;min-width:18px!important;height:18px!important;padding:0 4px!important;border-radius:9px!important;background:#fff!important;color:var(--wprp-red)!important;font-size:11px!important;font-weight:700!important;line-height:18px!important;text-align:center!important;box-shadow:0 1px 3px rgba(30,34,37,.3)!important}
		#wprp-root #wprp-panel{position:absolute!important;right:0!important;bottom:64px!important;width:420px!important;max-width:calc(100vw - 40px)!important;max-height:70vh!important;display:flex!important;flex-direction:column!important;background:#fff!important;color:var(--wprp-ink)!important;border:1px solid #d6dade!important;border-radius:10px!important;box-shadow:0 10px 34px rgba(30,34,37,.28)!important;overflow:hidden!important}
			/* left-edge drag handle: the panel is right-anchored, so dragging the left edge widens it */
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-resize{position:absolute!important;left:0!important;top:0!important;width:9px!important;height:100%!important;cursor:ew-resize!important;z-index:6!important;touch-action:none!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-resize::before{content:""!important;position:absolute!important;left:2px!important;top:50%!important;transform:translateY(-50%)!important;width:3px!important;height:36px!important;border-radius:2px!important;background:#cfd4d8!important;transition:background .12s!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-resize:hover::before{background:var(--wprp-red)!important;height:54px!important}
			#wprp-panel.wprp-resizing{user-select:none!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-head{display:flex!important;align-items:center!important;gap:.5rem!important;padding:.6rem .75rem!important;background:var(--wprp-red)!important;color:#fff!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-head .wprp-page{font-size:.78rem!important;opacity:.85!important;margin-left:auto!important;max-width:150px!important;overflow:hidden!important;text-overflow:ellipsis!important;white-space:nowrap!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-x{background:none!important;border:none!important;color:#fff!important;font-size:20px!important;line-height:1!important;cursor:pointer!important;padding:0 0 0 .25rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-repo-link{color:#fff!important;display:inline-flex!important;align-items:center!important;text-decoration:none!important;opacity:.9!important;padding:0 .1rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-repo-link:hover{opacity:1!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-repo-link .dashicons{font-size:18px!important;width:18px!important;height:18px!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-list{padding:.5rem .75rem!important;overflow-y:auto!important;flex:1!important;min-height:60px!important}
		/* Anti-squish: the non-scrolling children of #wprp-panel (a flex column) must
		   not shrink. Without flex:none the browser compresses them past their content
		   height once the notes overflow the panel max-height. .wprp-list is a block
		   container so the note cards themselves are already safe - the exposure here
		   is the header, the tab row and the add form. */
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) :is(.wprp-head,.wprp-tabs,.wprp-form){flex:none!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-muted{color:var(--wprp-gray)!important;font-size:.85rem!important;margin:.4rem 0!important}
		/* branded empty state for the "No open notes on this page" message (open tab only) */
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-empty{background:var(--wprp-red)!important;color:#fff!important;font-weight:600!important;text-align:center!important;border-radius:6px!important;padding:.55rem .7rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note{border:1px solid #e6e9ec!important;border-left:3px solid var(--wprp-red)!important;border-radius:6px!important;padding:.45rem .6rem!important;margin-bottom:.5rem!important;font-size:.86rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note.wprp-resolved{opacity:.55!important;border-left-color:var(--wprp-gray)!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note .wprp-meta{display:flex!important;flex-wrap:wrap!important;gap:.35rem!important;align-items:center!important;font-size:.72rem!important;color:var(--wprp-gray)!important;margin-bottom:.25rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-tag{background:var(--wprp-red)!important;color:#fff!important;border-radius:3px!important;padding:.02rem .3rem!important;font-weight:600!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note .wprp-body p{margin:.2rem 0!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-ctx{margin-top:.3rem!important;font-size:.68rem!important;color:var(--wprp-gray)!important;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-replies{margin-top:.4rem!important;border-top:1px dashed #e6e9ec!important;padding-top:.4rem!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-reply{font-size:.8rem!important;padding:.2rem .4rem!important;margin-bottom:.3rem!important;background:#f3f5f6!important;border-radius:4px!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-reply-meta{font-size:.68rem!important;color:var(--wprp-gray)!important;margin-bottom:.1rem!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-reply-body p{margin:.15rem 0!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-replyform{display:flex!important;gap:.3rem!important;margin-top:.25rem!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-replytext{flex:1!important;min-width:0!important;border:1px solid #cfd4d8!important;border-radius:4px!important;padding:.25rem .4rem!important;font:inherit!important;font-size:.8rem!important;resize:vertical!important;box-sizing:border-box!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-replysend{background:#fff!important;border:1px solid #cfd4d8!important;border-radius:4px!important;color:var(--wprp-ink)!important;font-size:.78rem!important;padding:.2rem .55rem!important;cursor:pointer!important;white-space:nowrap!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-replysend:hover{border-color:var(--wprp-red)!important;color:var(--wprp-red)!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-meta .wprp-actions{margin-left:auto!important;display:inline-flex!important;align-items:center!important;gap:.35rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-resolve{background:none!important;border:1px solid #cfd4d8!important;border-radius:4px!important;color:var(--wprp-gray)!important;font-size:.72rem!important;cursor:pointer!important;padding:.1rem .4rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-resolve:hover{border-color:var(--wprp-red)!important;color:var(--wprp-red)!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-locate{display:inline-flex!important;align-items:center!important;justify-content:center!important;background:none!important;border:1px solid #cfd4d8!important;border-radius:4px!important;color:var(--wprp-gray)!important;cursor:pointer!important;padding:.1rem .3rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-locate:hover{border-color:var(--wprp-red)!important;color:var(--wprp-red)!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-locate .dashicons{font-size:15px!important;width:15px!important;height:15px!important}
		/* locate highlight: a red bordered box drawn with padding around the pinned element */
		#wprp-locate-hl{position:fixed!important;z-index:99987!important;border:3px solid var(--wprp-red)!important;border-radius:4px!important;background:rgba(211,47,47,.08)!important;box-shadow:0 0 0 2px rgba(255,255,255,.5)!important;pointer-events:none!important;transition:opacity .25s!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-edit{background:none!important;border:1px solid #cfd4d8!important;border-radius:4px!important;color:var(--wprp-gray)!important;font-size:.72rem!important;cursor:pointer!important;padding:.1rem .4rem!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-edit:hover{border-color:var(--wprp-red)!important;color:var(--wprp-red)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-editbar{display:flex!important;align-items:center!important;justify-content:space-between!important;gap:.5rem!important;font-size:.78rem!important;color:var(--wprp-red)!important;background:#fff4f4!important;border:1px solid #f3c0c0!important;border-radius:5px!important;padding:.25rem .5rem!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-edit-cancel{background:none!important;border:none!important;color:var(--wprp-gray)!important;text-decoration:underline!important;cursor:pointer!important;font-size:.78rem!important;padding:0!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-edit-cancel:hover{color:var(--wprp-red)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-form{display:flex!important;flex-direction:column!important;gap:.4rem!important;padding:.6rem .75rem!important;border-top:1px solid #e6e9ec!important;background:#f7f9fa!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-form select,:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-form textarea{width:100%!important;border:1px solid #cfd4d8!important;border-radius:5px!important;padding:.35rem .5rem!important;font:inherit!important;font-size:.86rem!important;box-sizing:border-box!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-form textarea{resize:vertical!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-formrow{display:flex!important;gap:.4rem!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-formrow select{flex:1!important;min-width:0!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-prio{border-radius:3px!important;padding:.02rem .3rem!important;font-size:.68rem!important;font-weight:600!important;border:1px solid transparent!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-prio-high{background:var(--wprp-red)!important;color:#fff!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-prio-normal{background:#eef1f3!important;color:var(--wprp-gray)!important;border-color:#dfe3e6!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-prio-low{background:transparent!important;color:var(--wprp-gray)!important;border-color:#dfe3e6!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-sev{border-radius:3px!important;padding:.02rem .3rem!important;font-size:.68rem!important;font-weight:600!important;background:transparent!important;border:1px solid currentColor!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-sev-blocker{color:#8e1b28!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-sev-critical{color:#c0392b!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-sev-major{color:#b9770e!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-sev-minor{color:var(--wprp-gray)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-sev-trivial{color:var(--wprp-gray)!important;opacity:.75!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-assignee{color:var(--wprp-gray)!important;font-size:.72rem!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-levellabel{display:flex!important;flex-direction:column!important;gap:.15rem!important;font-size:.7rem!important;color:var(--wprp-gray)!important;font-weight:600!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-levellabel select{font-weight:400!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note .wprp-level{background:#eef1f3!important;color:var(--wprp-gray)!important;border:1px solid #dfe3e6!important;border-radius:3px!important;padding:.02rem .3rem!important;font-size:.66rem!important;font-weight:600!important}
			/* client-visibility opt-in: the form checkbox and the badge that marks an opted-in note */
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-cvlabel{display:flex!important;align-items:center!important;gap:.35rem!important;font-size:.76rem!important;color:var(--wprp-gray)!important;font-weight:600!important;cursor:pointer!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-cvlabel input{width:14px!important;height:14px!important;margin:0!important;flex:none!important;accent-color:var(--wprp-red)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-cv{background:#14569B!important;color:#fff!important;border-radius:3px!important;padding:.02rem .3rem!important;font-size:.66rem!important;font-weight:600!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-submit{align-self:flex-end!important;background:var(--wprp-red)!important;color:#fff!important;border:none!important;border-radius:5px!important;padding:.4rem .9rem!important;cursor:pointer!important;font-size:.86rem!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-submit:hover{background:var(--wprp-red-dark)!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-submit:disabled{opacity:.6!important;cursor:default!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-shotrow{display:flex!important;align-items:center!important;gap:.5rem!important;flex-wrap:wrap!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-shotbtn{display:inline-flex!important;align-items:center!important;gap:.25rem!important;background:#fff!important;border:1px solid #cfd4d8!important;border-radius:5px!important;color:var(--wprp-ink)!important;font-size:.82rem!important;padding:.3rem .6rem!important;cursor:pointer!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-shotbtn:hover{border-color:var(--wprp-red)!important;color:var(--wprp-red)!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-shotbtn .dashicons{font-size:16px!important;width:16px!important;height:16px!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-shot-preview{position:relative!important;display:inline-block!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-shot-preview img{height:40px!important;width:auto!important;max-width:120px!important;border:1px solid #cfd4d8!important;border-radius:4px!important;display:block!important;object-fit:cover!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-shot-clear{position:absolute!important;top:-7px!important;right:-7px!important;width:18px!important;height:18px!important;border-radius:50%!important;border:none!important;background:var(--wprp-ink)!important;color:#fff!important;font-size:13px!important;line-height:1!important;cursor:pointer!important;padding:0!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note .wprp-shot{margin-top:.35rem!important;display:block!important}
		:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note .wprp-shot img{max-width:100%!important;border:1px solid #e6e9ec!important;border-radius:5px!important;display:block!important}
		/* full-screen drag-to-capture overlay */
		#wprp-capture{position:fixed!important;inset:0!important;z-index:99999!important;cursor:crosshair!important;background:rgba(30,34,37,.28)!important}
		#wprp-capture .wprp-selbox{position:absolute!important;border:2px dashed var(--wprp-red)!important;background:rgba(211,47,47,.12)!important;pointer-events:none!important}
		#wprp-capture .wprp-hint,#wprp-pinmode .wprp-hint{position:fixed!important;top:14px!important;left:50%!important;transform:translateX(-50%)!important;background:var(--wprp-ink)!important;color:#fff!important;font-family:-apple-system,sans-serif!important;font-size:.82rem!important;padding:.4rem .8rem!important;border-radius:6px!important;pointer-events:none!important}
			/* screenshot markup (drawing layer): flatten pen/arrow/box onto the captured WebP. */
			/* Hex literals (not the --wprp-* vars): the modal mounts on <body>, outside #wprp-root, so those custom properties don't cascade here. */
			/* Base theme = LIGHT; the prefers-color-scheme: dark block below restores the dark look. The white/red buttons read on both bars, so only the backdrop, bar, and separator flip. */
			#wprp-markup{position:fixed!important;inset:0!important;z-index:100001!important;background:rgba(30,34,37,.40)!important;display:flex!important;flex-direction:column!important;align-items:center!important;justify-content:center!important;gap:10px!important;padding:14px!important;box-sizing:border-box!important}
			#wprp-markup .wprp-mk-bar{display:flex!important;gap:8px!important;align-items:center!important;background:#eef1f3!important;padding:8px!important;border-radius:10px!important;border:1px solid #d7dde2!important;box-shadow:0 4px 18px rgba(30,34,37,.25)!important;font-family:-apple-system,sans-serif!important;flex-wrap:wrap!important;justify-content:center!important}
			#wprp-markup .wprp-mk-tool{display:inline-flex!important;align-items:center!important;gap:.4rem!important;background:#fff!important;border:2px solid #D32F2F!important;color:#D32F2F!important;font:inherit!important;font-size:.9rem!important;font-weight:600!important;padding:.55rem .9rem!important;border-radius:8px!important;cursor:pointer!important;line-height:1!important}
			#wprp-markup .wprp-mk-tool svg{width:20px!important;height:20px!important;display:block!important;flex:none!important}
			#wprp-markup .wprp-mk-tool:hover{background:#fde8e8!important}
			#wprp-markup .wprp-mk-tool.wprp-active{background:#D32F2F!important;color:#fff!important}
			#wprp-markup .wprp-mk-sp{width:1px!important;height:26px!important;background:#cfd4d8!important;margin:0 2px!important}
			#wprp-markup .wprp-mk-done{background:#D32F2F!important;color:#fff!important;font-weight:700!important}
			#wprp-markup .wprp-mk-done:hover{background:#B71C1C!important}
			#wprp-markup .wprp-mk-stage{flex:1!important;min-height:0!important;display:flex!important;align-items:center!important;justify-content:center!important;width:100%!important}
			#wprp-markup .wprp-mk-canvas{max-width:100%!important;max-height:100%!important;background:#fff!important;border-radius:4px!important;box-shadow:0 6px 24px rgba(0,0,0,.3)!important;cursor:crosshair!important;touch-action:none!important}
			@media (prefers-color-scheme: dark){
				#wprp-markup{background:rgba(30,34,37,.92)!important}
				#wprp-markup .wprp-mk-bar{background:#1E2225!important;border-color:#33383c!important;box-shadow:0 4px 18px rgba(0,0,0,.5)!important}
				/* inactive/light buttons get a dark surface (red border kept, brighter-red text+icon for contrast); active tool + Save stay solid red */
				#wprp-markup .wprp-mk-tool{background:#26292c!important;color:#FF5252!important}
				#wprp-markup .wprp-mk-tool:hover{background:#33383c!important}
				#wprp-markup .wprp-mk-tool.wprp-active{background:#D32F2F!important;color:#fff!important}
				#wprp-markup .wprp-mk-done{background:#D32F2F!important;color:#fff!important}
				#wprp-markup .wprp-mk-done:hover{background:#B71C1C!important}
				#wprp-markup .wprp-mk-sp{background:#55585b!important}
				#wprp-markup .wprp-mk-canvas{box-shadow:0 6px 24px rgba(0,0,0,.6)!important}
			}
			#wprp-shot-thumb{cursor:pointer!important}
			/* element-pin: form row + indicator */
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pinrow{display:flex!important;align-items:center!important;gap:.5rem!important;flex-wrap:wrap!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pin-info{font-size:.78rem!important;color:var(--wprp-gray)!important;display:inline-flex!important;align-items:center!important;gap:.3rem!important;background:#eef1f3!important;border:1px solid #dfe3e6!important;border-radius:4px!important;padding:.1rem .2rem .1rem .45rem!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pin-clear{border:none!important;background:var(--wprp-ink)!important;color:#fff!important;width:16px!important;height:16px!important;border-radius:50%!important;font-size:12px!important;line-height:1!important;cursor:pointer!important;padding:0!important}
			/* element-pin: full-screen picker */
			#wprp-pinmode{position:fixed!important;inset:0!important;z-index:99999!important;cursor:crosshair!important;background:rgba(30,34,37,.10)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pinhl{position:fixed!important;border:2px solid var(--wprp-red)!important;background:rgba(211,47,47,.12)!important;pointer-events:none!important;z-index:99999!important;box-sizing:border-box!important}
			/* element-pin: the placed markers */
			#wprp-pinlayer{position:fixed!important;inset:0!important;z-index:99988!important;pointer-events:none!important}
			<?php if ( $wprp_pin_color ) { echo '#wprp-pinlayer{--wprp-pin:' . $wprp_pin_color . '}'; } // custom pin colour, scoped to the layer the markers actually live in ?>
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pin{position:fixed!important;transform:translate(-50%,-50%)!important;min-width:22px!important;height:22px!important;padding:0 5px!important;border-radius:11px!important;background:var(--wprp-pin,#D32F2F)!important;color:#fff!important;border:2px solid #fff!important;box-shadow:0 2px 6px rgba(30,34,37,.4)!important;font-size:11px!important;font-weight:700!important;line-height:1!important;display:flex!important;align-items:center!important;justify-content:center!important;cursor:pointer!important;pointer-events:auto!important;box-sizing:border-box!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pin:hover{filter:brightness(0.9)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pin.wprp-resolved{background:var(--wprp-gray)!important;opacity:.65!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note.wprp-flash{animation:wprp-flash 1.3s ease!important}
			@keyframes wprp-flash{0%{background:rgba(211,47,47,.20)}100%{background:transparent}}
		#wprp-busy{position:fixed!important;inset:0!important;z-index:99999!important;display:flex!important;align-items:center!important;justify-content:center!important;background:rgba(30,34,37,.18)!important;font-family:-apple-system,sans-serif!important}
		#wprp-busy span{background:var(--wprp-ink)!important;color:#fff!important;font-size:.85rem!important;padding:.5rem 1rem!important;border-radius:6px!important}
		#wprp-toast{position:fixed!important;left:50%!important;bottom:84px!important;transform:translateX(-50%)!important;z-index:100000!important;background:var(--wprp-red-dark)!important;color:#fff!important;font-family:-apple-system,sans-serif!important;font-size:.82rem!important;padding:.45rem .9rem!important;border-radius:6px!important;box-shadow:0 4px 14px rgba(30,34,37,.4)!important;max-width:80vw!important;text-align:center!important}
			/* Keyboard focus: a visible ring on every interactive surface, legible over any site background. */
			#wprp-root :focus-visible{outline:2px solid var(--wprp-red)!important;outline-offset:2px!important}
			#wprp-fab:focus-visible,:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pin:focus-visible{outline:none!important;box-shadow:0 0 0 2px #fff,0 0 0 5px var(--wprp-red)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-resize:focus-visible{outline:2px solid var(--wprp-red)!important;outline-offset:-2px!important}
			/* Open / Resolved tab strip */
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-tabs{display:flex!important;gap:.25rem!important;padding:.4rem .75rem 0!important;background:#fff!important;border-bottom:1px solid #e6e9ec!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-tab{flex:1!important;background:none!important;border:none!important;border-bottom:2px solid transparent!important;color:var(--wprp-gray)!important;font:inherit!important;font-size:.8rem!important;font-weight:600!important;padding:.4rem .25rem!important;margin-bottom:-1px!important;cursor:pointer!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-tab:hover{color:var(--wprp-red)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-tab.wprp-active{color:var(--wprp-red)!important;border-bottom-color:var(--wprp-red)!important}
			/* on the Resolved tab the notes are the content, so don't dim them */
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-list-resolved .wprp-note.wprp-resolved{opacity:1!important}
			/* collapsible advanced fields in the add-note form */
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-more-toggle{background:#fff!important;border:1px solid #cfd4d8!important;border-radius:5px!important;color:var(--wprp-gray)!important;font:inherit!important;font-size:.82rem!important;padding:.35rem .6rem!important;cursor:pointer!important;white-space:nowrap!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-more-toggle:hover{border-color:var(--wprp-red)!important;color:var(--wprp-red)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-more{display:flex!important;flex-direction:column!important;gap:.4rem!important}
			/* the Undo action inside a toast */
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-toast-action{background:none!important;border:1px solid rgba(255,255,255,.55)!important;color:#fff!important;border-radius:4px!important;font:inherit!important;font-size:.78rem!important;font-weight:600!important;padding:.12rem .5rem!important;margin-left:.6rem!important;cursor:pointer!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-toast-action:hover{background:rgba(255,255,255,.18)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-replytext{min-height:2.4em!important}
			/* 3-state status: per-note dropdown + In Progress accent (amber) */
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-status{background:#fff!important;border:1px solid #cfd4d8!important;border-radius:4px!important;color:var(--wprp-gray)!important;font:inherit!important;font-size:.72rem!important;padding:.05rem .2rem!important;cursor:pointer!important;max-width:9.5em!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-status:hover{border-color:var(--wprp-red)!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note.wprp-st-progress{border-left-color:#E8A100!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pin-progress{background:#E8A100!important}
			:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-pin-progress:hover{background:#C98A00!important}
			/* Respect the user's reduced-motion preference: kill transitions, the flash, and the resize accent grow. */
			@media (prefers-reduced-motion: reduce){
				#wprp-fab,#wprp-fab:hover{transition:none!important;transform:none!important}
				:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-resize::before,:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-resize:hover::before{transition:none!important}
				#wprp-locate-hl{transition:none!important}
				:is(#wprp-root,#wprp-markup,#wprp-capture,#wprp-pinmode,#wprp-pinlayer,#wprp-busy,#wprp-toast) .wprp-note.wprp-flash{animation:none!important}
			}
	</style>
	<script id="wprp-js">
	(function () {
		var root = document.getElementById('wprp-root');
		if (!root) { return; }
		var cfg = JSON.parse(root.getAttribute('data-cfg'));
		// Reviewer mode: a token-bearing anonymous client. The overlay is read-only on existing
		// notes (no resolve/edit/delete/assign), screenshots are off, and an optional one-time name
		// prompt attributes their feedback. cfg.reviewer is set server-side only for a valid token.
		var isReviewer = !!cfg.reviewer;
		var REVIEWER_NAME_KEY = 'wprpReviewerName';
		var reviewerName = '';
		var reviewerNameAsked = false;
		if (isReviewer) {
			try { reviewerName = localStorage.getItem(REVIEWER_NAME_KEY) || ''; reviewerNameAsked = !!localStorage.getItem(REVIEWER_NAME_KEY + 'Asked'); } catch (e) {}
		}
		var REVIEWER_NAME_PROMPT = '<?php echo esc_js( __( 'Your name (optional) so the site owner knows who left this feedback:', 'wp-red-pen' ) ); ?>';
		// Ask once per browser, the first time the reviewer opens the panel. Skippable (Cancel/blank).
		function ensureReviewerName() {
			if (!isReviewer || reviewerNameAsked) { return; }
			reviewerNameAsked = true;
			try { localStorage.setItem(REVIEWER_NAME_KEY + 'Asked', '1'); } catch (e) {}
			var v = null;
			try { v = window.prompt(REVIEWER_NAME_PROMPT, reviewerName || ''); } catch (e) { v = null; }
			if (v != null) { reviewerName = String(v).slice(0, 80).trim(); try { if (reviewerName) { localStorage.setItem(REVIEWER_NAME_KEY, reviewerName); } } catch (e) {} }
		}
		var fab = document.getElementById('wprp-fab');
		var panel = document.getElementById('wprp-panel');
		var list = document.getElementById('wprp-list');
		var form = document.getElementById('wprp-form');
		var body = document.getElementById('wprp-body');
		var typeSel = document.getElementById('wprp-type');
			var prioSel = document.getElementById('wprp-priority');
			var sevSel = document.getElementById('wprp-severity'); // null for reviewers (dev-only field)
			var assigneeSel = document.getElementById('wprp-assignee');
			var levelSel = document.getElementById('wprp-level');
			var codeScopeInput = document.getElementById('wprp-codescope'); // null when agent feedback is off
			var clientVisChk = document.getElementById('wprp-client-visible'); // null for reviewers (dev-only field)
			// Agents live in the assignee dropdown as value "agent:<slug>"; split into {assignee, agent}.
			function splitAssignee(v) { return (v && v.indexOf('agent:') === 0) ? { assignee: 0, agent: v.slice(6) } : { assignee: (v || '0'), agent: '' }; }
			// The current view's contexts (key/label/target) the server computed for this page.
			function ctxForLevel(lvl) { if (lvl === 'global' && cfg.global && cfg.global.key) { return cfg.global; } return (lvl === 'template' && cfg.template && cfg.template.key) ? cfg.template : cfg.page; }
		var countEl = document.getElementById('wprp-fab-count');
		var tabOpenBtn = document.getElementById('wprp-tab-open');
		var tabProgressBtn = document.getElementById('wprp-tab-progress');
		var tabResolvedBtn = document.getElementById('wprp-tab-resolved');
		var currentTab = 'open';
		var moreToggle = document.getElementById('wprp-more-toggle');
		var moreBox = document.getElementById('wprp-more');
		var shotBtn = document.getElementById('wprp-shot-btn');
		var shotPrev = document.getElementById('wprp-shot-preview');
		var shotThumb = document.getElementById('wprp-shot-thumb');
		var shotClear = document.getElementById('wprp-shot-clear');
		var pendingShot = null;
		// screenshot markup (drawing layer) i18n - declared early so the thumb tooltip can read them at setup time
		var MK_ARROW  = '<?php echo esc_js( __( 'Arrow', 'wp-red-pen' ) ); ?>';
		var MK_BOX    = '<?php echo esc_js( __( 'Box', 'wp-red-pen' ) ); ?>';
		var MK_PEN    = '<?php echo esc_js( __( 'Pen', 'wp-red-pen' ) ); ?>';
		var MK_UNDO   = '<?php echo esc_js( __( 'Undo', 'wp-red-pen' ) ); ?>';
		var MK_DONE   = '<?php echo esc_js( __( 'Save Screenshot', 'wp-red-pen' ) ); ?>';
		var MK_CANCEL = '<?php echo esc_js( __( 'Cancel', 'wp-red-pen' ) ); ?>';
		var MK_REOPEN = '<?php echo esc_js( __( 'Click to draw on this screenshot', 'wp-red-pen' ) ); ?>';
		var pinBtn = document.getElementById('wprp-pin-btn');
		var pinInfo = document.getElementById('wprp-pin-info');
		var pinLabel = document.getElementById('wprp-pin-label');
		var pinClear = document.getElementById('wprp-pin-clear');
		var pendingAnchor = null;
		var pinLayer = null;
		var pins = [];
		var focusId = null;
		var editingId = null;     // null = create mode; a note id = editing that note
		var shotRemove = false;   // edit mode: user cleared the existing screenshot
		var anchorRemove = false; // edit mode: user cleared the existing element pin
		var lastNotes = [];       // most recent notes payload (so Edit can prefill from it)
		var editBar = document.getElementById('wprp-editbar');
		var editCancel = document.getElementById('wprp-edit-cancel');
		var submitBtn = form.querySelector('.wprp-submit');
		var ADD_LABEL = submitBtn ? submitBtn.textContent : 'Add note';
		var SAVE_LABEL = '<?php echo esc_js( __( 'Save changes', 'wp-red-pen' ) ); ?>';

		function api(path, opts) {
			opts = opts || {};
			// Reviewers authenticate with the bearer token header (they have no wp_rest nonce);
			// logged-in devs send the nonce. The two paths are mutually exclusive.
			var auth = cfg.reviewToken ? { 'X-WPRP-Review-Token': cfg.reviewToken } : { 'X-WP-Nonce': cfg.nonce };
			opts.headers = Object.assign({ 'Content-Type': 'application/json' }, auth, opts.headers || {});
			return fetch(cfg.root + path, opts).then(function (r) {
				if (!r.ok) { throw new Error('HTTP ' + r.status); }
				return r.json();
			});
		}
		function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

			// Transient error/notice toast (so a failed save never fails silently).
			var toastEl = null, toastTimer = null;
			function toast(msg, actionLabel, actionFn) {
				if (!toastEl) { toastEl = document.createElement('div'); toastEl.id = 'wprp-toast'; toastEl.setAttribute('role', 'alert'); toastEl.setAttribute('aria-live', 'assertive'); toastEl.setAttribute('aria-atomic', 'true'); document.body.appendChild(toastEl); }
				toastEl.textContent = '';
					var _tspan = document.createElement('span'); _tspan.textContent = msg; toastEl.appendChild(_tspan);
					if (actionLabel && actionFn) {
						var _tbtn = document.createElement('button'); _tbtn.type = 'button'; _tbtn.className = 'wprp-toast-action'; _tbtn.textContent = actionLabel;
						_tbtn.addEventListener('click', function () { if (toastTimer) { clearTimeout(toastTimer); } toastEl.style.display = 'none'; actionFn(); });
						toastEl.appendChild(_tbtn);
					}
				toastEl.style.display = 'block';
				if (toastTimer) { clearTimeout(toastTimer); }
				toastTimer = setTimeout(function () { if (toastEl) { toastEl.style.display = 'none'; } }, actionLabel ? 6000 : 4000);
			}
			var SAVE_FAILED = '<?php echo esc_js( __( 'Could not save - check your connection and try again.', 'wp-red-pen' ) ); ?>';
				// a11y strings + reduced-motion check (built once).
				var FAB_LABEL = '<?php echo esc_js( __( 'Red Pen notes', 'wp-red-pen' ) ); ?>';
				var FAB_LABEL_N = '<?php echo esc_js( __( 'Red Pen notes, %d open', 'wp-red-pen' ) ); ?>';
				var PIN_PREFIX = '<?php echo esc_js( __( 'Note', 'wp-red-pen' ) ); ?>';
				var RESOLVED_WORD = '<?php echo esc_js( __( 'resolved', 'wp-red-pen' ) ); ?>';
				var TAB_OPEN = '<?php echo esc_js( __( 'Open', 'wp-red-pen' ) ); ?>';
				var TAB_RESOLVED = '<?php echo esc_js( __( 'Resolved', 'wp-red-pen' ) ); ?>';
				var EMPTY_OPEN = '<?php echo esc_js( __( 'No open notes on this page.', 'wp-red-pen' ) ); ?>';
				var EMPTY_RESOLVED = '<?php echo esc_js( __( 'No resolved notes on this page.', 'wp-red-pen' ) ); ?>';
				var TAB_PROGRESS = '<?php echo esc_js( __( 'In Progress', 'wp-red-pen' ) ); ?>';
				var EMPTY_PROGRESS = '<?php echo esc_js( __( 'No in-progress notes on this page.', 'wp-red-pen' ) ); ?>';
				var STATUS_LABELS = { open: TAB_OPEN, progress: TAB_PROGRESS, resolved: TAB_RESOLVED };
				/* translators: %s: a status label (Open / In Progress / Resolved) */
				var MARKED_MSG = '<?php echo esc_js( __( 'Marked %s.', 'wp-red-pen' ) ); ?>';
				function emptyMsgFor(tab) { return tab === 'resolved' ? EMPTY_RESOLVED : (tab === 'progress' ? EMPTY_PROGRESS : EMPTY_OPEN); }
				function statusKeyOf(n) { return n.statusKey || (n.resolved ? 'resolved' : 'open'); }
				var RESOLVED_MSG = '<?php echo esc_js( __( 'Note resolved.', 'wp-red-pen' ) ); ?>';
				var REOPENED_MSG = '<?php echo esc_js( __( 'Note reopened.', 'wp-red-pen' ) ); ?>';
				var UNDO_LABEL = '<?php echo esc_js( __( 'Undo', 'wp-red-pen' ) ); ?>';
				/* translators: %s: agent name */
				var MARKED_AGENT_MSG = '<?php echo esc_js( __( 'Sent to %s (in the agent queue).', 'wp-red-pen' ) ); ?>';
				var SHOT_UNAVAILABLE = '<?php echo esc_js( __( 'Screenshot tool failed to load - reload the page and try again.', 'wp-red-pen' ) ); ?>';
				function reduceMotion() { return !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches); }
				function updateFabLabel(open) { fab.setAttribute('aria-label', open > 0 ? FAB_LABEL_N.replace('%d', open) : FAB_LABEL); }
				// Focus management for the panel (a role=dialog): trap Tab inside it, restore focus on close.
				var wprpLastFocus = null;
				var WPRP_FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),select:not([disabled]),textarea:not([disabled]),[tabindex]:not([tabindex="-1"])';
				function panelFocusables() { return Array.prototype.slice.call(panel.querySelectorAll(WPRP_FOCUSABLE)).filter(function (el) { return el.offsetParent !== null; }); }
				function wprpRestoreFocus() { var back = (wprpLastFocus && document.body.contains(wprpLastFocus)) ? wprpLastFocus : fab; wprpLastFocus = null; try { back.focus(); } catch (e) {} }

			// A short, readable "browser / OS / viewport" string for the note, parsed from the
			// user agent (best-effort; the raw UA is the fallback). Server sanitises + length-caps.
			function buildCtx() {
				var ua = navigator.userAgent || '';
				var browser =
					/Edg\//.test(ua) ? 'Edge' :
					/OPR\/|Opera/.test(ua) ? 'Opera' :
					/Firefox\//.test(ua) ? 'Firefox' :
					/Chrome\//.test(ua) ? 'Chrome' :
					/Safari\//.test(ua) ? 'Safari' : '';
				var os =
					/Windows/.test(ua) ? 'Windows' :
					/Mac OS X|Macintosh/.test(ua) ? 'macOS' :
					/Android/.test(ua) ? 'Android' :
					/iPhone|iPad|iOS/.test(ua) ? 'iOS' :
					/Linux/.test(ua) ? 'Linux' : '';
				var vp = window.innerWidth + 'x' + window.innerHeight;
				var parts = [browser, os, vp].filter(Boolean);
				return parts.length ? parts.join(' / ') : ua.slice(0, 120);
			}

		function noteHtml(n) {
			var sk = statusKeyOf(n);
			return '<div class="wprp-note wprp-st-' + sk + (sk === 'resolved' ? ' wprp-resolved' : '') + '" data-id="' + n.id + '">' +
				'<div class="wprp-meta"><span class="wprp-tag"' + (n.typeColor ? ' style="background:' + esc(n.typeColor) + '"' : '') + '>' + esc(n.typeLabel) + '</span>' +
				'<span class="wprp-prio wprp-prio-' + esc(n.priority || 'normal') + '">' + esc(n.priorityLabel) + '</span>' +
				(n.severity ? '<span class="wprp-sev wprp-sev-' + esc(n.severity) + '">' + esc(n.severityLabel) + '</span>' : '') +
				(n.level === 'template' ? '<span class="wprp-level" title="' + esc(n.ctxLabel || '') + '"><?php echo esc_js( __( 'Template', 'wp-red-pen' ) ); ?></span>' : '') +
					(n.level === 'global' ? '<span class="wprp-level" title="' + esc(n.ctxLabel || '') + '"><?php echo esc_js( __( 'Site-wide', 'wp-red-pen' ) ); ?></span>' : '') +
				// Dev view: who can read this note. Internal is the default, so the badge marks the exception.
				(!isReviewer && n.clientVisible ? '<span class="wprp-cv" title="<?php echo esc_js( __( 'Client reviewers can read this note', 'wp-red-pen' ) ); ?>"><?php echo esc_js( __( 'Client visible', 'wp-red-pen' ) ); ?></span>' : '') +
				// Reviewer view: their own report, and when it was marked done.
				(isReviewer && sk !== 'open' ? '<span class="wprp-level">' + esc(n.statusLabel || '') + '</span>' : '') +
				(isReviewer && n.resolvedAt ? '<span class="wprp-level"><?php echo esc_js( __( 'Fixed', 'wp-red-pen' ) ); ?> ' + esc(n.resolvedAt) + '</span>' : '') +
				'<span>' + esc(n.author) + '</span><span>' + esc(n.date) + '</span>' +
				(n.assigneeName ? '<span class="wprp-assignee">&rarr; ' + esc(n.assigneeName) + '</span>' : '') +
				'<span class="wprp-actions">' +
				(n.anchor ? '<button type="button" class="wprp-locate" title="<?php echo esc_js( __( 'Highlight the pinned element', 'wp-red-pen' ) ); ?>" aria-label="<?php echo esc_js( __( 'Highlight the pinned element', 'wp-red-pen' ) ); ?>"><span class="dashicons dashicons-search"></span></button>' : '') +
				// Status dropdown + Edit are dev-only mutate controls; a reviewer sees notes read-only.
				(isReviewer ? '' : (
					'<select class="wprp-status" aria-label="<?php echo esc_js( __( 'Status', 'wp-red-pen' ) ); ?>">' +
						'<option value="open"' + (sk === 'open' ? ' selected' : '') + '>' + esc(TAB_OPEN) + '</option>' +
						'<option value="progress"' + (sk === 'progress' ? ' selected' : '') + '>' + esc(TAB_PROGRESS) + '</option>' +
						'<option value="resolved"' + (sk === 'resolved' ? ' selected' : '') + '>' + esc(TAB_RESOLVED) + '</option>' +
					'</select>' +
					'<button type="button" class="wprp-edit"><?php echo esc_js( __( 'Edit', 'wp-red-pen' ) ); ?></button>'
				)) + '</span></div>' +
				'<div class="wprp-body">' + n.body + '</div>' +
				(n.shot ? '<a class="wprp-shot" href="' + esc(n.shot) + '" target="_blank" rel="noopener"><img src="' + esc(n.shot) + '" alt="screenshot"></a>' : '') +
				(n.ctx ? '<div class="wprp-ctx">' + esc(n.ctx) + '</div>' : '') +
				'<div class="wprp-replies">' +
					((n.replies && n.replies.length) ? n.replies.map(replyHtml).join('') : '') +
					'<form class="wprp-replyform" data-id="' + n.id + '">' +
						'<textarea class="wprp-replytext" rows="2" placeholder="<?php echo esc_js( __( 'Reply...', 'wp-red-pen' ) ); ?>" required></textarea>' +
						'<button type="submit" class="wprp-replysend"><?php echo esc_js( __( 'Reply', 'wp-red-pen' ) ); ?></button>' +
					'</form>' +
				'</div>' +
				'</div>';
			}

			function replyHtml(r) {
				return '<div class="wprp-reply"><div class="wprp-reply-meta">' + esc(r.author) + ' &middot; ' + esc(r.date) + '</div>' +
					'<div class="wprp-reply-body">' + r.body + '</div></div>';
		}

		function render() {
			var openCount = 0, progressCount = 0, resolvedCount = 0;
			for (var _i = 0; _i < lastNotes.length; _i++) { var _k = statusKeyOf(lastNotes[_i]); if (_k === 'resolved') { resolvedCount++; } else if (_k === 'progress') { progressCount++; } else { openCount++; } }
			var notes = [];
			for (var _j = 0; _j < lastNotes.length; _j++) { if (statusKeyOf(lastNotes[_j]) === currentTab) { notes.push(lastNotes[_j]); } }
			if (!notes.length) {
				list.innerHTML = '<p class="wprp-muted' + (currentTab === 'resolved' || currentTab === 'progress' ? '' : ' wprp-empty') + '">' + emptyMsgFor(currentTab) + '</p>';
			} else {
				list.innerHTML = notes.map(noteHtml).join('');
			}
			if (tabOpenBtn && tabResolvedBtn && tabProgressBtn) {
				tabOpenBtn.textContent = TAB_OPEN + ' (' + openCount + ')';
				tabProgressBtn.textContent = TAB_PROGRESS + ' (' + progressCount + ')';
				tabResolvedBtn.textContent = TAB_RESOLVED + ' (' + resolvedCount + ')';
				tabOpenBtn.classList.toggle('wprp-active', currentTab === 'open');
				tabProgressBtn.classList.toggle('wprp-active', currentTab === 'progress');
				tabResolvedBtn.classList.toggle('wprp-active', currentTab === 'resolved');
				tabOpenBtn.setAttribute('aria-selected', currentTab === 'open' ? 'true' : 'false');
				tabProgressBtn.setAttribute('aria-selected', currentTab === 'progress' ? 'true' : 'false');
				tabResolvedBtn.setAttribute('aria-selected', currentTab === 'resolved' ? 'true' : 'false');
			}
			list.classList.toggle('wprp-list-resolved', currentTab === 'resolved');
			if (openCount > 0) { countEl.textContent = openCount; countEl.hidden = false; } else { countEl.hidden = true; }
				updateFabLabel(openCount);
				list.setAttribute('aria-busy', 'false');
			if (focusId) {
				var fel = list.querySelector('.wprp-note[data-id="' + focusId + '"]');
				if (fel) { fel.scrollIntoView({ block: 'center' }); fel.classList.remove('wprp-flash'); void fel.offsetWidth; fel.classList.add('wprp-flash'); }
				focusId = null;
			}
		}

		var wprpLoadSeq = 0;
		function load() {
			var keys = [cfg.page && cfg.page.key, cfg.template && cfg.template.key, cfg.global && cfg.global.key].filter(Boolean).join(',');
			list.setAttribute('aria-busy', 'true');
				var wprpSeq = ++wprpLoadSeq;
				return api('/notes?keys=' + encodeURIComponent(keys)).then(function (notes) {
				if (wprpSeq !== wprpLoadSeq) { return notes; }
				lastNotes = notes;
				render();
				buildPins(notes);
				return notes;
			}).catch(function () {
				list.innerHTML = '<p class="wprp-muted"><?php echo esc_js( __( 'Could not load notes.', 'wp-red-pen' ) ); ?></p>';
					list.setAttribute('aria-busy', 'false');
			});
		}

		// ---- surgical DOM updates: patch only the affected note so other notes' reply drafts + scroll survive ----
		function noteNodeById(nid) { return list.querySelector('.wprp-note[data-id="' + nid + '"]'); }
		function htmlToNode(html) { var d = document.createElement('div'); d.innerHTML = html; return d.firstChild; }
		function lastNotesIndex(nid) { for (var i = 0; i < lastNotes.length; i++) { if (String(lastNotes[i].id) === String(nid)) { return i; } } return -1; }
		function noteBelongsToTab(n) { return statusKeyOf(n) === currentTab; }
		function clearPlaceholder() { if (!list.querySelector('.wprp-note')) { list.innerHTML = ''; } }
		function showEmptyIfNeeded() { if (!list.querySelector('.wprp-note')) { list.innerHTML = '<p class="wprp-muted' + (currentTab === 'resolved' ? '' : ' wprp-empty') + '">' + (currentTab === 'resolved' ? EMPTY_RESOLVED : EMPTY_OPEN) + '</p>'; } }
		function refreshCounts() {
			var o = 0, p = 0, r = 0;
			for (var i = 0; i < lastNotes.length; i++) { var k = statusKeyOf(lastNotes[i]); if (k === 'resolved') { r++; } else if (k === 'progress') { p++; } else { o++; } }
			if (tabOpenBtn) { tabOpenBtn.textContent = TAB_OPEN + ' (' + o + ')'; }
			if (tabProgressBtn) { tabProgressBtn.textContent = TAB_PROGRESS + ' (' + p + ')'; }
			if (tabResolvedBtn) { tabResolvedBtn.textContent = TAB_RESOLVED + ' (' + r + ')'; }
			if (o > 0) { countEl.textContent = o; countEl.hidden = false; } else { countEl.hidden = true; }
			updateFabLabel(o);
		}
		// Replace a single note's node in place (reply / edit) - leaves every other note untouched.
		function patchNoteInPlace(data) {
			if (data.agent) {
				// the note became an agent note -> it leaves the human panel entirely (isolated queue)
				var ax = lastNotesIndex(data.id); if (ax >= 0) { lastNotes.splice(ax, 1); }
				var an = noteNodeById(data.id); if (an) { an.parentNode.removeChild(an); }
				toast(MARKED_AGENT_MSG.split('%s').join(data.agentLabel || data.agent));
				showEmptyIfNeeded(); refreshCounts(); buildPins(lastNotes);
				return;
			}
			var idx = lastNotesIndex(data.id);
			if (idx >= 0) { lastNotes[idx] = data; } else { lastNotes.unshift(data); }
			var node = noteNodeById(data.id);
			if (node) { node.parentNode.replaceChild(htmlToNode(noteHtml(data)), node); }
			else if (noteBelongsToTab(data)) { clearPlaceholder(); list.insertBefore(htmlToNode(noteHtml(data)), list.firstChild); }
			refreshCounts(); buildPins(lastNotes);
		}
		// Flip a note's status locally (resolve / reopen / undo) and move it on/off the active tab.
		function applyStatusLocally(nid, statusKey) {
			var idx = lastNotesIndex(nid);
			if (idx >= 0) { lastNotes[idx].statusKey = statusKey; lastNotes[idx].resolved = (statusKey === 'resolved'); lastNotes[idx].status = (statusKey === 'resolved' ? 'wprp_resolved' : (statusKey === 'progress' ? 'wprp_progress' : 'wprp_open')); lastNotes[idx].statusLabel = STATUS_LABELS[statusKey] || ''; }
			var n = (idx >= 0) ? lastNotes[idx] : null;
			var node = noteNodeById(nid);
			if (n && noteBelongsToTab(n)) {
				if (node) { node.parentNode.replaceChild(htmlToNode(noteHtml(n)), node); }
				else { clearPlaceholder(); list.insertBefore(htmlToNode(noteHtml(n)), list.firstChild); }
			} else if (node) { node.parentNode.removeChild(node); }
			showEmptyIfNeeded(); refreshCounts(); buildPins(lastNotes);
		}
		// Drop a freshly created note straight into the list (newest first) without a refetch.
		function applyNewNote(data) {
			if (data.agent) {
				// agent-targeted note: isolated to the agent queue, never shown in the human panel
				toast(MARKED_AGENT_MSG.split('%s').join(data.agentLabel || data.agent));
				return;
			}
			lastNotes.unshift(data);
			if (noteBelongsToTab(data)) {
				clearPlaceholder();
				var n = htmlToNode(noteHtml(data));
				list.insertBefore(n, list.firstChild);
				n.classList.remove('wprp-flash'); void n.offsetWidth; n.classList.add('wprp-flash');
			}
			refreshCounts(); buildPins(lastNotes);
		}

		// ---- Open / Resolved tabs: switch which notes the panel lists (client-side from lastNotes) ----
		function setTab(tab) { currentTab = (tab === 'resolved' || tab === 'progress') ? tab : 'open'; render(); }
		if (tabOpenBtn) { tabOpenBtn.addEventListener('click', function () { setTab('open'); }); }
		if (tabProgressBtn) { tabProgressBtn.addEventListener('click', function () { setTab('progress'); }); }
		if (tabResolvedBtn) { tabResolvedBtn.addEventListener('click', function () { setTab('resolved'); }); }
		// ---- "More" toggle: reveal priority / assignee / reporting-level for the rare case ----
		if (moreToggle && moreBox) { moreToggle.addEventListener('click', function () { var o = moreBox.hidden; moreBox.hidden = !o; moreToggle.setAttribute('aria-expanded', o ? 'true' : 'false'); }); }
		// ---- remember report settings (type / priority / assignee / level) across page loads ----
		var WPRP_PREFS_KEY = 'wprpReportPrefs';
		function setSelVal(sel, val) { if (!sel || val == null || val === '') { return; } for (var i = 0; i < sel.options.length; i++) { if (sel.options[i].value === String(val)) { sel.value = String(val); return; } } }
		function savePrefs() { if (editingId) { return; } try { localStorage.setItem(WPRP_PREFS_KEY, JSON.stringify({ type: typeSel && typeSel.value, priority: prioSel && prioSel.value, assignee: assigneeSel && assigneeSel.value, level: levelSel && levelSel.value })); } catch (e) {} }
		function applyPrefs() { var p; try { p = JSON.parse(localStorage.getItem(WPRP_PREFS_KEY) || 'null'); } catch (e) { p = null; } if (!p) { return; } setSelVal(typeSel, p.type); setSelVal(prioSel, p.priority); setSelVal(assigneeSel, p.assignee); setSelVal(levelSel, p.level); }
		[typeSel, prioSel, assigneeSel, levelSel].forEach(function (s) { if (s) { s.addEventListener('change', savePrefs); } });
		applyPrefs();

		fab.addEventListener('click', function () {
			var show = panel.hidden;
			panel.hidden = !show;
			fab.setAttribute('aria-expanded', show ? 'true' : 'false');
			if (show) { ensureReviewerName(); wprpLastFocus = document.activeElement; load(); try { panel.focus(); } catch (e) {} }
		});
		document.getElementById('wprp-close').addEventListener('click', function () { wprpRestoreFocus();
			panel.hidden = true; fab.setAttribute('aria-expanded', 'false');
		});
		// Esc closes the open panel - but not while a capture/pin overlay is up (those own Esc).
		document.addEventListener('keydown', function (e) {
			if (e.key !== 'Escape' || panel.hidden) { return; }
			if (document.getElementById('wprp-capture') || document.getElementById('wprp-pinmode')) { return; }
			panel.hidden = true; fab.setAttribute('aria-expanded', 'false'); wprpRestoreFocus();
		});

		// ---- resizable panel: drag the left edge to widen; the custom width persists ----
		// Focus trap: while the panel is open, Tab/Shift+Tab cycle within it.
			panel.addEventListener('keydown', function (e) {
				if (e.key !== 'Tab' || panel.hidden) { return; }
				var f = panelFocusables();
				if (!f.length) { return; }
				var first = f[0], last = f[f.length - 1];
				if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
				else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
			});
			var resizeHandle = document.getElementById('wprp-resize');
		var WPRP_W_KEY = 'wprpPanelWidth';
		var MIN_W = 300;
		function maxW() { return Math.max(MIN_W, window.innerWidth - 40); }
		function clampW(w) { return Math.min(maxW(), Math.max(MIN_W, w)); }
		function applyW(w) { var cw = clampW(w); panel.style.setProperty('width', cw + 'px', 'important'); if (resizeHandle) { resizeHandle.setAttribute('aria-valuenow', String(Math.round(cw))); resizeHandle.setAttribute('aria-valuemax', String(Math.round(maxW()))); } }
		function storedW() { try { var v = parseInt(localStorage.getItem(WPRP_W_KEY), 10); return (v > 0) ? v : 0; } catch (e) { return 0; } }
		function saveW(w) { try { localStorage.setItem(WPRP_W_KEY, String(Math.round(clampW(w)))); } catch (e) {} }

		// Initial width: re-apply a saved custom width if there is one; otherwise the CSS default stands.
		(function initWidth() {
			var w = storedW();
			if (w) { applyW(w); }
		})();

		if (resizeHandle) {
			var rzStartX = 0, rzStartW = 0, rzActive = false;
			function rzPoint(e) { return e.touches && e.touches[0] ? e.touches[0].clientX : e.clientX; }
			function rzMove(e) {
				if (!rzActive) { return; }
				if (e.cancelable) { e.preventDefault(); }
				applyW(rzStartW + (rzStartX - rzPoint(e))); // dragging left (smaller clientX) widens
			}
			function rzUp() {
				if (!rzActive) { return; }
				rzActive = false;
				panel.classList.remove('wprp-resizing');
				window.removeEventListener('mousemove', rzMove);
				window.removeEventListener('mouseup', rzUp);
				window.removeEventListener('touchmove', rzMove);
				window.removeEventListener('touchend', rzUp);
				saveW(panel.getBoundingClientRect().width);
			}
			function rzDown(e) {
				rzActive = true;
				rzStartX = rzPoint(e);
				rzStartW = panel.getBoundingClientRect().width;
				panel.classList.add('wprp-resizing');
				window.addEventListener('mousemove', rzMove);
				window.addEventListener('mouseup', rzUp);
				window.addEventListener('touchmove', rzMove, { passive: false });
				window.addEventListener('touchend', rzUp);
				e.preventDefault();
			}
			resizeHandle.addEventListener('mousedown', rzDown);
			resizeHandle.addEventListener('touchstart', rzDown, { passive: false });
				// Keyboard resize: arrows nudge (Shift = larger step), Home/End jump to min/max.
				resizeHandle.addEventListener('keydown', function (e) {
					var cur = panel.getBoundingClientRect().width || (parseInt(panel.style.width, 10) || 420), step = e.shiftKey ? 60 : 20, nw = cur;
					if (e.key === 'ArrowLeft') { nw = cur + step; } else if (e.key === 'ArrowRight') { nw = cur - step; } else if (e.key === 'Home') { nw = MIN_W; } else if (e.key === 'End') { nw = maxW(); } else { return; }
					e.preventDefault(); applyW(nw); saveW(panel.getBoundingClientRect().width || nw);
				});
			// Keep an explicit width within bounds when the viewport shrinks.
			window.addEventListener('resize', function () { if (panel.style.width) { applyW(parseInt(panel.style.width, 10) || MIN_W); } });
		}

		list.addEventListener('click', function (e) {
			var lbtn = e.target.closest('.wprp-locate');
				if (lbtn) {
					locateNote(lbtn.closest('.wprp-note').getAttribute('data-id'));
					return;
				}
			var ebtn = e.target.closest('.wprp-edit');
				if (ebtn) {
					var eid = ebtn.closest('.wprp-note').getAttribute('data-id');
					for (var i = 0; i < lastNotes.length; i++) {
						if (String(lastNotes[i].id) === String(eid)) { enterEdit(lastNotes[i]); break; }
					}
					return;
				}
				var btn = e.target.closest('.wprp-resolve');
			if (!btn) { return; }
			var wrap = btn.closest('.wprp-note');
			var id = wrap.getAttribute('data-id');
			var resolved = !wrap.classList.contains('wprp-resolved');
			btn.disabled = true;
			api('/notes/' + id + '/status', { method: 'POST', body: JSON.stringify({ resolved: resolved }) })
				.then(function () {
					toast(resolved ? RESOLVED_MSG : REOPENED_MSG, UNDO_LABEL, function () {
						api('/notes/' + id + '/status', { method: 'POST', body: JSON.stringify({ resolved: !resolved }) }).then(function () { applyStatusLocally(id, !resolved); }).catch(function () { toast(SAVE_FAILED); });
					});
					applyStatusLocally(id, resolved);
				}).catch(function () { btn.disabled = false; toast(SAVE_FAILED); });
		});

		// Status dropdown on each note: change moves it between Open / In Progress / Resolved (with Undo).
		list.addEventListener('change', function (e) {
			var sel = e.target.closest('.wprp-status');
			if (!sel) { return; }
			var id = sel.closest('.wprp-note').getAttribute('data-id');
			var to = sel.value, from = 'open';
			for (var i = 0; i < lastNotes.length; i++) { if (String(lastNotes[i].id) === String(id)) { from = statusKeyOf(lastNotes[i]); break; } }
			if (to === from) { return; }
			sel.disabled = true;
			api('/notes/' + id + '/status', { method: 'POST', body: JSON.stringify({ status: to }) })
				.then(function () {
					toast(MARKED_MSG.replace('%s', STATUS_LABELS[to] || to), UNDO_LABEL, function () {
						api('/notes/' + id + '/status', { method: 'POST', body: JSON.stringify({ status: from }) }).then(function () { applyStatusLocally(id, from); }).catch(function () { toast(SAVE_FAILED); });
					});
					applyStatusLocally(id, to);
				}).catch(function () { sel.disabled = false; toast(SAVE_FAILED); });
		});

		// Reply boxes grow with their content (up to a cap) instead of staying a sliver.
		list.addEventListener('input', function (e) {
			var t = e.target;
			if (t && t.classList && t.classList.contains('wprp-replytext')) { t.style.height = 'auto'; t.style.height = Math.min(t.scrollHeight, 140) + 'px'; }
		});

		// Reply forms live inside each note; submit bubbles up to the list container.
		list.addEventListener('submit', function (e) {
			var rform = e.target.closest('.wprp-replyform');
			if (!rform) { return; }
			e.preventDefault();
			var ta = rform.querySelector('.wprp-replytext');
			var rtext = ta.value.trim();
			if (!rtext) { return; }
			var send = rform.querySelector('.wprp-replysend');
			send.disabled = true;
			var replyPayload = { body: rtext };
				if (isReviewer) { replyPayload.reviewer = reviewerName; }
				api('/notes/' + rform.getAttribute('data-id') + '/replies', { method: 'POST', body: JSON.stringify(replyPayload) })
				.then(function (data) { patchNoteInPlace(data); }).catch(function () { send.disabled = false; toast(SAVE_FAILED); });
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var text = body.value.trim();
			if (!text) { return; }
			var submit = form.querySelector('.wprp-submit');
			submit.disabled = true;
			if (editingId) {
				// Update: send body/type/priority/assignee always; screenshot + anchor only when
				// changed (a new payload) or explicitly removed - otherwise leave them untouched.
				var elvl = levelSel ? levelSel.value : 'page';
					var ectx = ctxForLevel(elvl);
					var eaa = splitAssignee(assigneeSel.value);
					var payload = { body: text, type: typeSel.value, priority: prioSel.value, severity: sevSel ? sevSel.value : '', assignee: eaa.assignee, agent: eaa.agent, codescope: codeScopeInput ? codeScopeInput.value : '', level: elvl, ctx_key: ectx.key, ctx_label: ectx.label, target: (elvl === 'page' ? (cfg.page.target || 0) : 0) };
					if (clientVisChk) { payload.client_visible = clientVisChk.checked ? 1 : 0; }
					if (pendingShot) { payload.shot = pendingShot; } else if (shotRemove) { payload.shot_remove = 1; }
					if (pendingAnchor) { payload.anchor = JSON.stringify(pendingAnchor); } else if (anchorRemove) { payload.anchor_remove = 1; }
				api('/notes/' + editingId, { method: 'POST', body: JSON.stringify(payload) })
					.then(function (data) { exitEdit(); submit.disabled = false; patchNoteInPlace(data); })
					.catch(function () { submit.disabled = false; toast(SAVE_FAILED); });
				return;
			}
			var lvl = levelSel ? levelSel.value : 'page';
			var lctx = ctxForLevel(lvl);
			// Reviewers have no assignee select (server never renders it); they always post unassigned
			// with the reviewer name. The server ignores any smuggled assignee/agent/shot regardless.
			var caa = assigneeSel ? splitAssignee(assigneeSel.value) : { assignee: '0', agent: '' };
			var createPayload = { body: text, type: typeSel.value, url: cfg.url, shot: pendingShot || '', ctx: buildCtx(), priority: prioSel.value, severity: sevSel ? sevSel.value : '', assignee: caa.assignee, anchor: pendingAnchor ? JSON.stringify(pendingAnchor) : '', level: lvl, ctx_key: lctx.key, ctx_label: lctx.label, target: (lvl === 'page' ? (cfg.page.target || 0) : 0), agent: caa.agent, codescope: codeScopeInput ? codeScopeInput.value : '' };
			if (clientVisChk) { createPayload.client_visible = clientVisChk.checked ? 1 : 0; }
			if (isReviewer) { createPayload.reviewer = reviewerName; }
			api('/notes', { method: 'POST', body: JSON.stringify(createPayload) })
				.then(function (data) { body.value = ''; if (sevSel) { sevSel.value = ''; } if (clientVisChk) { clientVisChk.checked = false; } clearShot(); clearAnchor(); submit.disabled = false; applyNewNote(data); })
				.catch(function () { submit.disabled = false; toast(SAVE_FAILED); });
		});

		// ---- edit mode: prefill the form from an existing note; submit PATCHes it ----
		function enterEdit(n) {
			editingId = n.id;
			shotRemove = false; anchorRemove = false;
			pendingShot = null; pendingAnchor = null;
			typeSel.value = n.type || 'note';
			prioSel.value = n.priority || 'normal';
			if (sevSel) { sevSel.value = n.severity || ''; }
			assigneeSel.value = n.agent ? ('agent:' + n.agent) : String(n.assignee || 0);
			if (levelSel) { levelSel.value = (n.level === 'global') ? 'global' : ((n.level === 'template' && cfg.template && cfg.template.key) ? 'template' : 'page'); }
			if (codeScopeInput) { codeScopeInput.value = n.codeScope || ''; }
			if (clientVisChk) { clientVisChk.checked = !!n.clientVisible; }
			body.value = (n.raw != null ? n.raw : '').trim();
			// existing screenshot: show it; kept unless the user replaces or clears it
			if (n.shot) { shotThumb.src = n.shot; shotPrev.hidden = false; } else { shotPrev.hidden = true; shotThumb.removeAttribute('src'); }
			// existing element pin: show the indicator; kept unless replaced or cleared
			if (n.anchor) { pinLabel.textContent = '<?php echo esc_js( __( 'Pinned to element', 'wp-red-pen' ) ); ?>'; pinInfo.hidden = false; } else { pinInfo.hidden = true; pinLabel.textContent = ''; }
			editBar.hidden = false;
			if (moreBox) { moreBox.hidden = false; }
			if (moreToggle) { moreToggle.setAttribute('aria-expanded', 'true'); }
			if (submitBtn) { submitBtn.textContent = SAVE_LABEL; }
			panel.hidden = false;
			fab.setAttribute('aria-expanded', 'true');
			body.focus();
			form.scrollIntoView({ block: 'nearest' });
		}

		function exitEdit() {
			editingId = null;
			shotRemove = false; anchorRemove = false;
			pendingShot = null; pendingAnchor = null;
			body.value = '';
			typeSel.value = 'note'; prioSel.value = 'normal'; assigneeSel.value = '0';
			if (levelSel) { levelSel.value = 'page'; }
			if (codeScopeInput) { codeScopeInput.value = ''; }
			if (clientVisChk) { clientVisChk.checked = false; }
			applyPrefs();
			shotPrev.hidden = true; shotThumb.removeAttribute('src');
			pinInfo.hidden = true; pinLabel.textContent = '';
			editBar.hidden = true;
			if (moreBox) { moreBox.hidden = true; }
			if (moreToggle) { moreToggle.setAttribute('aria-expanded', 'false'); }
			if (submitBtn) { submitBtn.textContent = ADD_LABEL; }
		}
		if (editCancel) { editCancel.addEventListener('click', exitEdit); }

		// ---- screenshot: drag a box, html2canvas the region, store as WebP ----
		function clearShot() {
			pendingShot = null;
			shotPrev.hidden = true;
			shotThumb.removeAttribute('src');
				if (editingId) { shotRemove = true; } // editing: clearing removes the saved screenshot
		}
		shotClear.addEventListener('click', clearShot);

		// Click the thumbnail to re-open the drawing layer and add more marks (flattened, so old marks stay).
		if (!isReviewer && shotThumb) {
			shotThumb.title = MK_REOPEN;
			shotThumb.addEventListener('click', function () {
				var src = shotThumb.getAttribute('src');
				if (!src) { return; }
				panel.hidden = true;
				openMarkup(src, commitMarkup);
			});
		}

		// Reviewers get no screenshot capture in v1 (html2canvas is never enqueued for them); hide
		// the whole shot row so the dead button never appears.
		if (isReviewer) {
			var shotRow = shotBtn ? shotBtn.closest('.wprp-shotrow') : null;
			if (shotRow) { shotRow.hidden = true; }
		}

		shotBtn.addEventListener('click', function () {
			if (typeof html2canvas === 'undefined') { toast(SHOT_UNAVAILABLE); return; }
			startCapture();
		});

		function startCapture() {
			panel.hidden = true; // keep our own UI out of the shot
			var overlay = document.createElement('div');
			overlay.id = 'wprp-capture';
			var hint = document.createElement('div');
			hint.className = 'wprp-hint';
			hint.textContent = '<?php echo esc_js( __( 'Drag a box around the area to capture. Esc to cancel.', 'wp-red-pen' ) ); ?>';
			var sel = document.createElement('div');
			sel.className = 'wprp-selbox';
			sel.style.display = 'none';
			overlay.appendChild(hint);
			overlay.appendChild(sel);
			document.body.appendChild(overlay);

			var sx = 0, sy = 0, dragging = false;
			function down(e) { dragging = true; sx = e.clientX; sy = e.clientY; sel.style.display = 'block'; sel.style.left = sx + 'px'; sel.style.top = sy + 'px'; sel.style.width = '0'; sel.style.height = '0'; e.preventDefault(); }
			function move(e) { if (!dragging) { return; } var x = Math.min(e.clientX, sx), y = Math.min(e.clientY, sy); sel.style.left = x + 'px'; sel.style.top = y + 'px'; sel.style.width = Math.abs(e.clientX - sx) + 'px'; sel.style.height = Math.abs(e.clientY - sy) + 'px'; }
			function up(e) {
				if (!dragging) { return; }
				dragging = false;
				var r = { x: Math.min(e.clientX, sx), y: Math.min(e.clientY, sy), w: Math.abs(e.clientX - sx), h: Math.abs(e.clientY - sy) };
				teardown();
				if (r.w < 8 || r.h < 8) { panel.hidden = false; return; }
				capture(r);
			}
			function key(e) { if (e.key === 'Escape') { teardown(); panel.hidden = false; } }
			function teardown() {
				overlay.removeEventListener('mousedown', down);
					overlay.removeEventListener('touchstart', tdown);
					window.removeEventListener('touchmove', tmove);
					window.removeEventListener('touchend', tup);
				window.removeEventListener('mousemove', move);
				window.removeEventListener('mouseup', up);
				window.removeEventListener('keydown', key);
				if (overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
			}
			overlay.addEventListener('mousedown', down);
				// Touch floor: map touch events onto the same drag handlers so capture works on tablets/phones.
				function tc(e) { return (e.touches && e.touches[0]) ? e.touches[0] : (e.changedTouches && e.changedTouches[0]) ? e.changedTouches[0] : e; }
				function tdown(e) { var t = tc(e); down({ clientX: t.clientX, clientY: t.clientY, preventDefault: function () { if (e.cancelable) { e.preventDefault(); } } }); }
				function tmove(e) { var t = tc(e); move({ clientX: t.clientX, clientY: t.clientY }); if (dragging && e.cancelable) { e.preventDefault(); } }
				function tup(e) { var t = tc(e); up({ clientX: t.clientX, clientY: t.clientY }); }
				overlay.addEventListener('touchstart', tdown, { passive: false });
				window.addEventListener('touchmove', tmove, { passive: false });
				window.addEventListener('touchend', tup);
			window.addEventListener('mousemove', move);
			window.addEventListener('mouseup', up);
			window.addEventListener('keydown', key);
		}

		function capture(r) {
			var busy = document.createElement('div');
			busy.id = 'wprp-busy';
			busy.innerHTML = '<span><?php echo esc_js( __( 'Capturing...', 'wp-red-pen' ) ); ?></span>';
			document.body.appendChild(busy);
			html2canvas(document.body, {
				x: window.scrollX + r.x,
				y: window.scrollY + r.y,
				width: r.w,
				height: r.h,
				scale: 1,
				useCORS: true,
				backgroundColor: '#ffffff',
				logging: false,
				ignoreElements: function (el) { return el.id === 'wprp-busy' || el.id === 'wprp-root'; }
			}).then(function (canvas) {
				var data = canvas.toDataURL('image/webp', 0.82);
				if (data.indexOf('data:image/webp') !== 0) { data = canvas.toDataURL('image/png'); }
				// Accept the raw capture first (so Cancel in markup keeps it), then open the drawing layer.
				commitMarkup(data);
				if (busy.parentNode) { busy.parentNode.removeChild(busy); }
				openMarkup(data, commitMarkup); // shows the panel again when it closes
			}).catch(function () {
				if (busy.parentNode) { busy.parentNode.removeChild(busy); }
				panel.hidden = false;
			});
		}

		// ---- screenshot markup: draw arrow/box/pen on the capture, flatten back to one WebP ----
		// Annotations are composited onto the image and re-exported; no separate vector store, so the
		// save/decode path, the _wprp_shot meta, the gated reader and delete-cleanup are all untouched.
		function commitMarkup(url) {
			pendingShot = url;
			shotRemove = false;       // a marked-up image supersedes any pending removal
			shotThumb.src = url;
			shotPrev.hidden = false;
		}

		function openMarkup(baseUrl, onCommit) {
			var MK_COLOR = '#D32F2F'; // the deliberate Red Pen accent; fixed colour + weight in v1
			// Inline SVG icons (static markup, no user input) so the toolbar needs no dashicons/font dependency.
			function svg(paths) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>'; }
			var ICON_ARROW  = svg('<line x1="5" y1="19" x2="19" y2="5"/><polyline points="9 5 19 5 19 15"/>');
			var ICON_RECT   = svg('<rect x="4" y="6" width="16" height="12" rx="1"/>');
			var ICON_PEN    = svg('<path d="M14.5 5.5l4 4L8 20l-4.5.5L4 16z"/><line x1="13" y1="7" x2="17" y2="11"/>');
			var ICON_UNDO   = svg('<polyline points="9 7 4 12 9 17"/><path d="M4 12h11a5 5 0 0 1 5 5v1"/>');
			var ICON_CANCEL = svg('<line x1="6" y1="6" x2="18" y2="18"/><line x1="18" y1="6" x2="6" y2="18"/>');
			var ICON_SAVE   = svg('<path d="M5 4h11l3 3v13H5z"/><path d="M8 4v5h7"/><rect x="8" y="13" width="8" height="6"/>');
			var overlay = document.createElement('div');
			overlay.id = 'wprp-markup';
			overlay.innerHTML =
				'<div class="wprp-mk-bar">' +
					'<button type="button" class="wprp-mk-tool wprp-active" data-tool="pen">' + ICON_PEN + '<span class="wprp-mk-lbl"></span></button>' +
					'<button type="button" class="wprp-mk-tool" data-tool="arrow">' + ICON_ARROW + '<span class="wprp-mk-lbl"></span></button>' +
					'<button type="button" class="wprp-mk-tool" data-tool="rect">' + ICON_RECT + '<span class="wprp-mk-lbl"></span></button>' +
					'<span class="wprp-mk-sp"></span>' +
					'<button type="button" class="wprp-mk-tool" data-act="undo">' + ICON_UNDO + '<span class="wprp-mk-lbl"></span></button>' +
					'<span class="wprp-mk-sp"></span>' +
					'<button type="button" class="wprp-mk-tool" data-act="cancel">' + ICON_CANCEL + '<span class="wprp-mk-lbl"></span></button>' +
					'<button type="button" class="wprp-mk-tool wprp-mk-done" data-act="done">' + ICON_SAVE + '<span class="wprp-mk-lbl"></span></button>' +
				'</div>' +
				'<div class="wprp-mk-stage"><canvas class="wprp-mk-canvas"></canvas></div>';
			document.body.appendChild(overlay);
			// Labels set as text (not innerHTML) so translated strings are never interpreted as markup; the icon SVG stays.
			var bar = overlay.querySelector('.wprp-mk-bar');
			bar.querySelector('[data-tool="arrow"] .wprp-mk-lbl').textContent = MK_ARROW;
			bar.querySelector('[data-tool="rect"] .wprp-mk-lbl').textContent  = MK_BOX;
			bar.querySelector('[data-tool="pen"] .wprp-mk-lbl').textContent   = MK_PEN;
			bar.querySelector('[data-act="undo"] .wprp-mk-lbl').textContent   = MK_UNDO;
			bar.querySelector('[data-act="cancel"] .wprp-mk-lbl').textContent = MK_CANCEL;
			bar.querySelector('[data-act="done"] .wprp-mk-lbl').textContent   = MK_DONE;

			var canvas = overlay.querySelector('.wprp-mk-canvas');
			var ctx = canvas.getContext('2d');
			var img = new Image();
			var strokes = [];          // committed marks; the stack that Undo pops
			var cur = null;            // the mark being drawn right now
			var tool = 'pen';
			var lw = 3;
			var drawing = false;

			img.onload = function () {
				canvas.width = img.naturalWidth;
				canvas.height = img.naturalHeight;
				lw = Math.max(2, Math.round(Math.min(canvas.width, canvas.height) / 220));
				redraw();
			};
			img.onerror = function () { teardown(); panel.hidden = false; };
			img.src = baseUrl;

			function redraw() {
				ctx.clearRect(0, 0, canvas.width, canvas.height);
				ctx.drawImage(img, 0, 0);
				for (var i = 0; i < strokes.length; i++) { drawStroke(strokes[i]); }
				if (cur) { drawStroke(cur); }
			}
			function drawStroke(s) {
				var p = s.points;
				if (!p.length) { return; }
				ctx.strokeStyle = MK_COLOR; ctx.fillStyle = MK_COLOR;
				ctx.lineWidth = lw; ctx.lineCap = 'round'; ctx.lineJoin = 'round';
				if (s.tool === 'pen') {
					ctx.beginPath(); ctx.moveTo(p[0].x, p[0].y);
					for (var i = 1; i < p.length; i++) { ctx.lineTo(p[i].x, p[i].y); }
					ctx.stroke();
				} else if (s.tool === 'rect') {
					var a = p[0], b = p[p.length - 1];
					ctx.strokeRect(Math.min(a.x, b.x), Math.min(a.y, b.y), Math.abs(b.x - a.x), Math.abs(b.y - a.y));
				} else { // arrow
					var a0 = p[0], b0 = p[p.length - 1];
					ctx.beginPath(); ctx.moveTo(a0.x, a0.y); ctx.lineTo(b0.x, b0.y); ctx.stroke();
					var ang = Math.atan2(b0.y - a0.y, b0.x - a0.x);
					var hl = lw * 4 + 6;
					ctx.beginPath();
					ctx.moveTo(b0.x, b0.y);
					ctx.lineTo(b0.x - hl * Math.cos(ang - Math.PI / 7), b0.y - hl * Math.sin(ang - Math.PI / 7));
					ctx.lineTo(b0.x - hl * Math.cos(ang + Math.PI / 7), b0.y - hl * Math.sin(ang + Math.PI / 7));
					ctx.closePath(); ctx.fill();
				}
			}
			function pt(e) {
				var r = canvas.getBoundingClientRect();
				return { x: (e.clientX - r.left) * (canvas.width / r.width), y: (e.clientY - r.top) * (canvas.height / r.height) };
			}
			function down(e) {
				if (e.button !== undefined && e.button !== 0) { return; }
				drawing = true; cur = { tool: tool, points: [pt(e)] };
				redraw(); e.preventDefault();
			}
			function move(e) {
				if (!drawing) { return; }
				var q = pt(e);
				if (tool === 'pen') { cur.points.push(q); } else { cur.points[1] = q; }
				redraw(); e.preventDefault();
			}
			function up() {
				if (!drawing) { return; }
				drawing = false;
				if (cur) {
					var a = cur.points[0], b = cur.points[cur.points.length - 1];
					// keep pen scribbles; drop accidental zero-length rect/arrow clicks
					if (tool === 'pen' ? cur.points.length > 1 : (Math.abs(b.x - a.x) > 2 || Math.abs(b.y - a.y) > 2)) {
						strokes.push(cur);
					}
				}
				cur = null; redraw();
			}
			canvas.addEventListener('pointerdown', down);
			window.addEventListener('pointermove', move);
			window.addEventListener('pointerup', up);

			bar.addEventListener('click', function (e) {
				var btn = e.target.closest('button'); if (!btn) { return; }
				var t = btn.getAttribute('data-tool');
				if (t) {
					tool = t;
					var all = bar.querySelectorAll('.wprp-mk-tool[data-tool]');
					for (var i = 0; i < all.length; i++) { all[i].classList.toggle('wprp-active', all[i] === btn); }
					return;
				}
				var act = btn.getAttribute('data-act');
				if (act === 'undo') { strokes.pop(); redraw(); }
				else if (act === 'cancel') { teardown(); panel.hidden = false; }
				else if (act === 'done') { commit(); }
			});
			function key(e) { if (e.key === 'Escape') { teardown(); panel.hidden = false; } }
			window.addEventListener('keydown', key);

			function commit() {
				var out;
				try {
					out = canvas.toDataURL('image/webp', 0.82);
					if (out.indexOf('data:image/webp') !== 0) { out = canvas.toDataURL('image/png'); }
				} catch (err) { out = baseUrl; }
				teardown();
				onCommit(out);
				panel.hidden = false;
			}
			function teardown() {
				canvas.removeEventListener('pointerdown', down);
				window.removeEventListener('pointermove', move);
				window.removeEventListener('pointerup', up);
				window.removeEventListener('keydown', key);
				if (overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
			}
		}

		// ---- element pinning: pick an element, drop a numbered marker, click to open ----
		function clearAnchor() {
			pendingAnchor = null;
			pinInfo.hidden = true;
			pinLabel.textContent = '';
				if (editingId) { anchorRemove = true; } // editing: clearing removes the saved pin
		}
		pinClear.addEventListener('click', clearAnchor);
		pinBtn.addEventListener('click', startPin);

		function cssEsc(s) { return (window.CSS && CSS.escape) ? CSS.escape(s) : String(s).replace(/[^a-zA-Z0-9_-]/g, '\\$&'); }

		// Build a querySelector path from <body> down to el, anchoring on an id when present.
		function cssPath(el) {
			if (!el || el.nodeType !== 1) { return ''; }
			var path = [];
			while (el && el.nodeType === 1 && el !== document.body && el !== document.documentElement) {
				if (el.id) { path.unshift('#' + cssEsc(el.id)); break; }
				var tag = el.tagName.toLowerCase();
				var nth = 1, sib = el;
				while ((sib = sib.previousElementSibling)) { if (sib.tagName === el.tagName) { nth++; } }
				path.unshift(tag + ':nth-of-type(' + nth + ')');
				el = el.parentElement;
			}
			return path.join(' > ');
		}

		function showPinInfo(el) {
			pinLabel.textContent = '<?php echo esc_js( __( 'Pinned to', 'wp-red-pen' ) ); ?> ' + (el.tagName ? el.tagName.toLowerCase() : 'element');
			pinInfo.hidden = false;
		}

		function startPin() {
			panel.hidden = true;
			var ov = document.createElement('div');
			ov.id = 'wprp-pinmode';
			var hl = document.createElement('div');
			hl.className = 'wprp-pinhl';
			hl.style.display = 'none';
			var hint = document.createElement('div');
			hint.className = 'wprp-hint';
			hint.textContent = '<?php echo esc_js( __( 'Click an element to pin this note to it. Esc to cancel.', 'wp-red-pen' ) ); ?>';
			ov.appendChild(hl);
			ov.appendChild(hint);
			document.body.appendChild(ov);

			// The overlay is on top, so drop pointer-events for the hit-test then restore.
			function elAt(e) {
				ov.style.pointerEvents = 'none';
				var el = document.elementFromPoint(e.clientX, e.clientY);
				ov.style.pointerEvents = 'auto';
				return el;
			}
			function mv(e) {
				var el = elAt(e);
				if (!el || el === ov) { hl.style.display = 'none'; return; }
				var r = el.getBoundingClientRect();
				hl.style.display = 'block';
				hl.style.left = r.left + 'px'; hl.style.top = r.top + 'px';
				hl.style.width = r.width + 'px'; hl.style.height = r.height + 'px';
			}
			function clk(e) {
				e.preventDefault(); e.stopPropagation();
				var el = elAt(e);
				teardown();
				if (!el || el === ov || el === document.body || el === document.documentElement) { panel.hidden = false; return; }
				var r = el.getBoundingClientRect();
				var x = r.width ? (e.clientX - r.left) / r.width : 0.5;
				var y = r.height ? (e.clientY - r.top) / r.height : 0.5;
				var sel = cssPath(el);
				if (!sel) { panel.hidden = false; return; }
				pendingAnchor = { sel: sel, x: Math.max(0, Math.min(1, x)), y: Math.max(0, Math.min(1, y)) };
				anchorRemove = false; // a fresh pin supersedes any pending removal
				showPinInfo(el);
				panel.hidden = false;
			}
			function key(e) { if (e.key === 'Escape') { teardown(); panel.hidden = false; } }
			function teardown() {
				ov.removeEventListener('mousemove', mv);
					ov.removeEventListener('touchmove', tmv);
					ov.removeEventListener('touchend', tend);
				ov.removeEventListener('click', clk);
				window.removeEventListener('keydown', key);
				if (ov.parentNode) { ov.parentNode.removeChild(ov); }
			}
			ov.addEventListener('mousemove', mv);
				// Touch floor: drag a finger to highlight, lift to pin.
				function tmv(e) { var t = e.touches && e.touches[0]; if (t) { mv({ clientX: t.clientX, clientY: t.clientY }); if (e.cancelable) { e.preventDefault(); } } }
				function tend(e) { var t = e.changedTouches && e.changedTouches[0]; if (t) { clk({ clientX: t.clientX, clientY: t.clientY, preventDefault: function () {}, stopPropagation: function () {} }); } }
				ov.addEventListener('touchmove', tmv, { passive: false });
				ov.addEventListener('touchend', tend);
			ov.addEventListener('click', clk);
			window.addEventListener('keydown', key);
		}

		function openToNote(id) {
			if (panel.hidden) { wprpLastFocus = document.activeElement; }
			currentTab = 'open';
			panel.hidden = false;
			fab.setAttribute('aria-expanded', 'true');
			focusId = id;
			load();
		}

		function buildPins(notes) {
			if (!pinLayer) { pinLayer = document.createElement('div'); pinLayer.id = 'wprp-pinlayer'; document.body.appendChild(pinLayer); }
			pinLayer.innerHTML = '';
			pins = [];
			var i = 0;
			notes.forEach(function (n) {
				if (statusKeyOf(n) === 'resolved') { return; } // resolved notes drop off the active list, so their pin marker goes too (open + in-progress keep pins)
				if (!n.anchor) { return; }
				var a; try { a = JSON.parse(n.anchor); } catch (e) { return; }
				if (!a || !a.sel) { return; }
				i++;
				var marker = document.createElement('button');
				marker.type = 'button';
				marker.className = 'wprp-pin' + (statusKeyOf(n) === 'progress' ? ' wprp-pin-progress' : '');
				marker.textContent = i;
				var pinSnippet = (n.typeLabel ? n.typeLabel + ': ' : '') + (n.body ? n.body.replace(/<[^>]*>/g, '').slice(0, 80) : '');
				marker.title = pinSnippet;
				marker.setAttribute('aria-label', PIN_PREFIX + ' ' + i + ': ' + pinSnippet + (n.resolved ? ' (' + RESOLVED_WORD + ')' : ''));
				(function (noteId) { marker.addEventListener('click', function () { openToNote(noteId); }); })(n.id);
				pinLayer.appendChild(marker);
				pins.push({ sel: a.sel, x: typeof a.x === 'number' ? a.x : 0.5, y: typeof a.y === 'number' ? a.y : 0.5, el: marker });
			});
			positionPins();
		}

		// ---- locate: from a note's magnifying glass, scroll to + outline its pinned element ----
		var locHl = null, locTarget = null, locTimer = null;
		var LOC_PAD = 6;
		var LOCATE_MISSING = '<?php echo esc_js( __( 'That pinned element is not on this page right now.', 'wp-red-pen' ) ); ?>';
		function positionLocateHl() {
			if (!locHl || !locTarget) { return; }
			if (!document.body.contains(locTarget)) { hideLocateHl(); return; }
			var r = locTarget.getBoundingClientRect();
			locHl.style.left = (r.left - LOC_PAD) + 'px';
			locHl.style.top = (r.top - LOC_PAD) + 'px';
			locHl.style.width = (r.width + LOC_PAD * 2) + 'px';
			locHl.style.height = (r.height + LOC_PAD * 2) + 'px';
		}
		function showLocateHl(el) {
			if (!locHl) { locHl = document.createElement('div'); locHl.id = 'wprp-locate-hl'; document.body.appendChild(locHl); }
			locTarget = el;
			locHl.style.display = 'block';
			locHl.style.opacity = '1';
			positionLocateHl();
			if (locTimer) { clearTimeout(locTimer); }
			locTimer = setTimeout(hideLocateHl, 2800);
		}
		function hideLocateHl() {
			if (locTimer) { clearTimeout(locTimer); locTimer = null; }
			if (!locHl) { return; }
			locHl.style.opacity = '0';
			setTimeout(function () { if (locHl && locHl.style.opacity === '0') { locHl.style.display = 'none'; locTarget = null; } }, 280);
		}
		function locateNote(id) {
			var note = null;
			for (var i = 0; i < lastNotes.length; i++) { if (String(lastNotes[i].id) === String(id)) { note = lastNotes[i]; break; } }
			if (!note || !note.anchor) { return; }
			var a; try { a = JSON.parse(note.anchor); } catch (e) { return; }
			if (!a || !a.sel) { return; }
			var el; try { el = document.querySelector(a.sel); } catch (e) { el = null; }
			if (!el) { toast(LOCATE_MISSING); return; }
			el.scrollIntoView({ block: 'center', behavior: reduceMotion() ? 'auto' : 'smooth' });
			showLocateHl(el);
		}

		// ---- jump to next open note: cycle the on-page pins, scroll + flash each (admin-bar item + "J") ----
		// Reuses the locate highlight. Works off the live `pins` array (built from priming on load, then from
		// lastNotes once the panel opens), so it functions before the panel is ever opened. Notes without an
		// on-page anchor aren't jumpable - they're not in `pins`.
		var JUMP_NONE = '<?php echo esc_js( __( 'No open notes are pinned on this page.', 'wp-red-pen' ) ); ?>';
		var jumpIdx = -1;
		function jumpToNextOpen() {
			var targets = [];
			for (var i = 0; i < pins.length; i++) {
				var t = pins[i].target;
				if (!t || !document.body.contains(t)) { try { t = document.querySelector(pins[i].sel); } catch (e) { t = null; } }
				if (!t) { continue; }
				var r = t.getBoundingClientRect();
				if (r.width || r.height) { targets.push(t); }
			}
			if (!targets.length) { toast(JUMP_NONE); return; }
			jumpIdx = (jumpIdx + 1) % targets.length;
			var el = targets[jumpIdx];
			el.scrollIntoView({ block: 'center', behavior: reduceMotion() ? 'auto' : 'smooth' });
			showLocateHl(el);
		}
		// Delegated click (robust to admin-bar render order) + a "J" keyboard shortcut outside of form fields.
		document.addEventListener('click', function (e) {
			if (e.target.closest && e.target.closest('#wp-admin-bar-wprp-jump')) { e.preventDefault(); jumpToNextOpen(); }
		});
		document.addEventListener('keydown', function (e) {
			if (e.defaultPrevented || e.altKey || e.ctrlKey || e.metaKey) { return; }
			var t = e.target;
			if (t && (t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT' || t.isContentEditable)) { return; }
			if (e.key === 'j' || e.key === 'J') { jumpToNextOpen(); }
		});

		function positionPins() {
			for (var k = 0; k < pins.length; k++) {
				var p = pins[k], t = p.target;
				if (!t || !document.body.contains(t)) { try { t = document.querySelector(p.sel); } catch (e) { t = null; } p.target = t; }
				if (!t) { p.el.style.setProperty('display', 'none', 'important'); continue; }
				var r = t.getBoundingClientRect();
				if (r.width === 0 && r.height === 0) { p.el.style.setProperty('display', 'none', 'important'); continue; }
				p.el.style.setProperty('display', 'flex', 'important');
				p.el.style.left = (r.left + p.x * r.width) + 'px';
				p.el.style.top = (r.top + p.y * r.height) + 'px';
			}
		}

		var pinTick = false;
		function repositionSoon() {
			if (pinTick) { return; }
			pinTick = true;
			window.requestAnimationFrame(function () { positionPins(); positionLocateHl(); pinTick = false; });
		}
		window.addEventListener('scroll', repositionSoon, true);
		window.addEventListener('resize', repositionSoon);

		// Prime the FAB badge + element pins from the server-embedded data (cfg.priming);
		// the full notes payload (bodies, replies, screenshots) is fetched only when the panel opens.
		function primeFromCfg() {
			var p = cfg.priming || {};
			var oc = p.openCount || 0;
			if (oc > 0) { countEl.textContent = oc; countEl.hidden = false; } else { countEl.hidden = true; }
			updateFabLabel(oc);
			buildPins(p.pins || []);
		}
		primeFromCfg();
	})();
	</script>
	<?php
}

/** The dashicons font on the front end (admin bar already loads it when present). */
add_action(
	'wp_enqueue_scripts',
	function () {
		$reviewer = ! wprp_user_can() && wprp_can_review();
		if ( wprp_devmode_on() || $reviewer ) {
			wp_enqueue_style( 'dashicons' );
			// Vendored html2canvas (MIT) for client-side region screenshots. Reviewers do NOT
			// get it - screenshots are dev-only in v1 (avoids the private-page capture surface).
			if ( ! $reviewer ) {
				wp_enqueue_script( 'wprp-html2canvas', WPRP_PLUGIN_URL . 'assets/vendor/html2canvas.min.js', array(), '1.4.1', true );
			}
		}
	}
);

// ---------------------------------------------------------------------------
// Edit-screen meta box: the notes attached to this post
// ---------------------------------------------------------------------------
add_action(
	'add_meta_boxes',
	function ( $post_type ) {
		if ( ! wprp_user_can() ) {
			return;
		}
		$obj = get_post_type_object( $post_type );
		if ( ! $obj || ! $obj->public || WPRP_CPT === $post_type ) {
			return;
		}
		add_meta_box( 'wprp-notes', __( 'Red Pen Notes', 'wp-red-pen' ), 'wprp_render_metabox', $post_type, 'side', 'default' );
	}
);

function wprp_render_metabox( $post ) {
	$notes = wprp_get_notes_for( $post->ID, 'any' );
	$types = wprp_note_types();
	if ( ! $notes ) {
		echo '<p class="wprp-muted">' . esc_html__( 'No notes on this post yet. Turn on Dev Mode and use the floating button on the front end to add one.', 'wp-red-pen' ) . '</p>';
	} else {
		echo '<ul style="margin:0">';
		foreach ( $notes as $n ) {
			$type     = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
			$resolved = WPRP_STATUS_DONE === $n->post_status;
			$author   = get_userdata( $n->post_author );
			echo '<li style="border-left:3px solid ' . ( $resolved ? '#3A3A3C' : '#D32F2F' ) . ';padding:.25rem .5rem;margin:0 0 .5rem;background:#f7f9fa;' . ( $resolved ? 'opacity:.6' : '' ) . '">';
			$tc = wprp_note_type_color( $type ); echo '<span style="background:' . esc_attr( $tc ? $tc : '#D32F2F' ) . ';color:#fff;border-radius:3px;padding:0 .3rem;font-size:.7rem;font-weight:600">' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</span> ';
			$priority = (string) get_post_meta( $n->ID, WPRP_META_PRIORITY, true );
			$prios    = wprp_priorities();
			if ( isset( $prios[ $priority ] ) && 'normal' !== $priority ) {
				echo '<span style="border:1px solid #dfe3e6;border-radius:3px;padding:0 .3rem;font-size:.68rem;font-weight:600;color:#3A3A3C">' . esc_html( $prios[ $priority ] ) . '</span> ';
			}
			$assignee = (int) get_post_meta( $n->ID, WPRP_META_ASSIGNEE, true );
			$au       = $assignee ? get_userdata( $assignee ) : false;
			echo '<small>' . esc_html( $author ? $author->display_name : '' ) . ' &middot; ' . esc_html( get_the_time( get_option( 'date_format' ), $n ) ) . ( $au ? ' &middot; &rarr; ' . esc_html( $au->display_name ) : '' ) . '</small>';
			echo '<div style="font-size:.85rem;margin-top:.2rem">' . wprp_kses_note( wpautop( $n->post_content ) ) . '</div>';
			$ctx = (string) get_post_meta( $n->ID, WPRP_META_CTX, true );
			if ( $ctx ) {
				echo '<div style="font-size:.7rem;color:#3A3A3C;font-family:monospace;margin-top:.2rem">' . esc_html( $ctx ) . '</div>';
			}
			if ( get_post_meta( $n->ID, WPRP_META_ANCHOR, true ) ) {
				echo '<div style="font-size:.7rem;color:#D32F2F;margin-top:.2rem"><span class="dashicons dashicons-location" style="font-size:13px;width:13px;height:13px;vertical-align:text-top"></span> ' . esc_html__( 'Pinned to an element', 'wp-red-pen' ) . '</div>';
			}
			$shot = wprp_shot_url( (string) get_post_meta( $n->ID, WPRP_META_SHOT, true ) );
			if ( $shot ) {
				echo '<a href="' . esc_url( $shot ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $shot ) . '" alt="" style="max-width:100%;margin-top:.3rem;border:1px solid #e6e9ec;border-radius:4px;display:block"></a>';
			}
			$replies = wprp_get_replies( $n->ID );
			foreach ( $replies as $r ) {
				$ra = get_userdata( $r->post_author );
				echo '<div style="margin:.3rem 0 0 .5rem;padding:.2rem .4rem;background:#fff;border-left:2px solid #cfd4d8;font-size:.8rem">';
				echo '<small style="color:#3A3A3C">' . esc_html( $ra ? $ra->display_name : '' ) . ' &middot; ' . esc_html( get_the_time( get_option( 'date_format' ), $r ) ) . '</small>';
				echo '<div>' . wprp_kses_note( wpautop( $r->post_content ) ) . '</div></div>';
			}
			echo '</li>';
		}
		echo '</ul>';
	}
	echo '<p style="margin:.4rem 0 0"><a href="' . esc_url( admin_url( 'tools.php?page=wp-red-pen' ) ) . '">' . esc_html__( 'Open the notes repository &rarr;', 'wp-red-pen' ) . '</a></p>';
}

// ---------------------------------------------------------------------------
// Admin repository page: every note across the site (the shared to-do list)
// ---------------------------------------------------------------------------
add_action(
	'admin_menu',
	function () {
		// Lives under Tools (not a top-level menu) to keep the main admin sidebar uncluttered.
		// The menu title carries a red pen dashicon (the brand accent), mirroring how Smooth Moves badges its Tools item.
		$wprp_menu_title = '<svg viewBox="0 0 24 24" aria-hidden="true" style="width:16px;height:16px;vertical-align:-3px;margin-right:5px"><path fill="#D32F2F" fill-rule="evenodd" d="M12 2l6.5 6.5-4.6 11.8a2 2 0 0 1-3.8 0L5.5 8.5 12 2zM13.7 11.4a1.7 1.7 0 1 1-3.4 0 1.7 1.7 0 1 1 3.4 0zM11.3 4.6h1.4v5h-1.4zM11.6 12.9h.8v6.7h-.8z"/></svg>' . esc_html__( 'Red Pen', 'wp-red-pen' );
		add_submenu_page(
			'tools.php',
			__( 'Red Pen', 'wp-red-pen' ),
			$wprp_menu_title,
			WPRP_CAP,
			'wp-red-pen',
			'wprp_render_repo_page'
		);
	}
);

function wprp_render_repo_page() {
	if ( ! wprp_user_can() ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'wp-red-pen' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter state
	$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'open';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$filter   = in_array( $filter, array( 'open', 'progress', 'resolved', 'all' ), true ) ? $filter : 'open';
	$statuses = 'all' === $filter ? wprp_all_statuses() : ( 'resolved' === $filter ? array( WPRP_STATUS_DONE ) : ( 'progress' === $filter ? array( WPRP_STATUS_PROGRESS ) : array( WPRP_STATUS_OPEN ) ) );

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter state
	$who = isset( $_GET['assignee'] ) ? sanitize_key( wp_unslash( $_GET['assignee'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$who = in_array( $who, array( 'me', 'none' ), true ) ? $who : '';

	$query_args = array(
		'post_type'      => WPRP_CPT,
		'post_status'    => $statuses,
		'post_parent'    => 0, // top-level notes only; replies are children
		'posts_per_page' => 500,
		'orderby'        => 'date',
		'order'          => 'DESC',
	);
	// Audience filter: '' = humans (agent notes excluded), 'agents' = any agent, or a specific agent slug.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter state
	$audience = isset( $_GET['audience'] ) ? sanitize_key( wp_unslash( $_GET['audience'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$enabled_agents = wprp_enabled_agents();
	$audience = ( 'agents' === $audience || isset( $enabled_agents[ $audience ] ) ) ? $audience : '';

	// Source filter: '' = all, 'review' = client-reviewer notes only, 'dev' = dev notes (exclude reviewer).
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filter state
	$source = isset( $_GET['wprp_src'] ) ? sanitize_key( wp_unslash( $_GET['wprp_src'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	$source = in_array( $source, array( 'review', 'dev' ), true ) ? $source : '';

	$meta = array( 'relation' => 'AND' );
	if ( 'me' === $who ) {
		$meta[] = array( 'key' => WPRP_META_ASSIGNEE, 'value' => (int) get_current_user_id() );
	} elseif ( 'none' === $who ) {
		$meta[] = array( 'relation' => 'OR', array( 'key' => WPRP_META_ASSIGNEE, 'compare' => 'NOT EXISTS' ), array( 'key' => WPRP_META_ASSIGNEE, 'value' => 0 ) );
	}
	if ( 'agents' === $audience ) {
		$meta[] = array( 'key' => WPRP_META_AGENT, 'value' => '', 'compare' => '!=' );
	} elseif ( '' !== $audience ) {
		$meta[] = array( 'key' => WPRP_META_AGENT, 'value' => $audience );
	} else {
		$meta[] = array( 'relation' => 'OR', array( 'key' => WPRP_META_AGENT, 'compare' => 'NOT EXISTS' ), array( 'key' => WPRP_META_AGENT, 'value' => '', 'compare' => '=' ) );
	}
	if ( 'review' === $source ) {
		$meta[] = array( 'key' => WPRP_META_VIA_REVIEW, 'value' => '1' );
	} elseif ( 'dev' === $source ) {
		$meta[] = array( 'key' => WPRP_META_VIA_REVIEW, 'compare' => 'NOT EXISTS' );
	}
	$query_args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query

	$notes = get_posts( $query_args );
	$types = wprp_note_types();
	$prios = wprp_priorities();

	$wprp_dark = (bool) get_option( WPRP_DARK_OPT );
	if ( $wprp_dark ) {
		// Dark mode for the repository page (wp-admin). Scoped to .wrap.wprp-dark so it
		// only restyles Red Pen's own content, never the rest of the WordPress dashboard.
		echo '<style id="wprp-repo-dark">.wrap.wprp-dark{background:#1e2125;color:#e6e9ec;padding:10px 16px 24px;border-radius:6px;margin-top:10px}.wrap.wprp-dark h1,.wrap.wprp-dark h2,.wrap.wprp-dark h3,.wrap.wprp-dark strong{color:#e6e9ec}.wrap.wprp-dark a{color:#6db3ff}.wrap.wprp-dark p,.wrap.wprp-dark label,.wrap.wprp-dark .subsubsub,.wrap.wprp-dark .subsubsub a{color:#b9c0c7}.wrap.wprp-dark .subsubsub a.current{color:#fff;font-weight:600}.wrap.wprp-dark .wp-list-table{background:#23272b;border-color:#3a3f44}.wrap.wprp-dark .wp-list-table th,.wrap.wprp-dark .wp-list-table td{color:#e6e9ec;border-color:#3a3f44;background:transparent}.wrap.wprp-dark .wp-list-table thead th,.wrap.wprp-dark .wp-list-table tfoot th{background:#2a2f34;color:#aeb4ba}.wrap.wprp-dark .wp-list-table.striped>tbody>:nth-child(odd){background:#262b30}.wrap.wprp-dark details{background:#23272b!important;border:1px solid #3a3f44!important;color:#e6e9ec}.wrap.wprp-dark details summary,.wrap.wprp-dark details p,.wrap.wprp-dark details label{color:#c8ced4!important}.wrap.wprp-dark details strong{color:#e6e9ec!important}.wrap.wprp-dark details hr{border-top-color:#3a3f44!important}.wrap.wprp-dark input,.wrap.wprp-dark select,.wrap.wprp-dark textarea{background:#1a1d20;color:#e6e9ec;border-color:#3a3f44}.wrap.wprp-dark .button:not(.button-primary){background:#2a2f34;color:#e6e9ec;border-color:#4a4f55;box-shadow:none}.wrap.wprp-dark .button:not(.button-primary):hover{background:#333941;color:#fff;border-color:#5a616a}.wrap.wprp-dark .notice{background:#23272b;color:#e6e9ec;border-color:#3a3f44}.wrap.wprp-dark code{background:#15181b;color:#e6e9ec}</style>';
	}
	echo '<div class="wrap' . ( $wprp_dark ? ' wprp-dark' : '' ) . '"><h1 style="display:flex;align-items:center;gap:.5rem"><svg viewBox="0 0 24 24" aria-hidden="true" style="width:22px;height:22px;flex:none"><path fill="#D32F2F" fill-rule="evenodd" d="M12 2l6.5 6.5-4.6 11.8a2 2 0 0 1-3.8 0L5.5 8.5 12 2zM13.7 11.4a1.7 1.7 0 1 1-3.4 0 1.7 1.7 0 1 1 3.4 0zM11.3 4.6h1.4v5h-1.4zM11.6 12.9h.8v6.7h-.8z"/></svg>' . esc_html__( 'Red Pen - Notes Repository', 'wp-red-pen' ) . '</h1>';
	echo '<p>' . esc_html__( 'Every note, flag, and suggested edit dropped across the site. Shared with all editors and admins.', 'wp-red-pen' ) . '</p>';

	// Red Pen Hub connection result (set by the save handler after a blocking test push).
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect flag
	$hub_notice = isset( $_GET['hub'] ) ? sanitize_key( wp_unslash( $_GET['hub'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( $hub_notice ) {
		$hub_msgs = array(
			'ok'      => array( 'updated', __( 'Connected to the Red Pen Hub - this site\'s notes were pushed to the combined board.', 'wp-red-pen' ) ),
			'token'   => array( 'error', __( 'The Hub rejected the connect token (401). Copy the exact token from the Hub\'s "Connect a Site" panel and save again.', 'wp-red-pen' ) ),
			'err'     => array( 'error', __( 'Could not reach the Red Pen Hub. Check the Hub is running and the Hub URL is correct (for example http://localhost:3900), then save again.', 'wp-red-pen' ) ),
			'partial' => array( 'error', __( 'The Hub connection needs BOTH a Hub URL and a connect token. Fill in the missing field and save again.', 'wp-red-pen' ) ),
		);
		if ( isset( $hub_msgs[ $hub_notice ] ) ) {
			echo '<div class="notice notice-' . esc_attr( $hub_msgs[ $hub_notice ][0] ) . ' is-dismissible"><p>' . esc_html( $hub_msgs[ $hub_notice ][1] ) . '</p></div>';
		}
	}

	// Display settings: which front-end views show the widget + the pin colour (global).
	$show_on   = wprp_show_on();
	$pin_color = sanitize_hex_color( (string) get_option( WPRP_PINCOLOR_OPT, '' ) );
	$save_url = admin_url( 'admin-post.php?action=wprp_save_visibility' );
	echo '<details style="margin:.5rem 0 1rem;border:1px solid #dcdcde;border-radius:5px;padding:.4rem .8rem;background:#fff;max-width:640px">';
	echo '<summary style="cursor:pointer;font-weight:600"><span class="dashicons dashicons-visibility" style="vertical-align:text-top"></span> ' . esc_html__( 'Display settings', 'wp-red-pen' ) . '</summary>';
	echo '<form method="post" action="' . esc_url( $save_url ) . '" style="margin-top:.6rem">';
	wp_nonce_field( 'wprp_save_visibility' ); // nonce in the POST body (the form posts; the handler reads $_POST['_wpnonce'])
	echo '<p style="margin:.2rem 0 .6rem;color:#646970">' . esc_html__( 'Choose which front-end views show the floating button for users in Dev Mode.', 'wp-red-pen' ) . '</p>';
	foreach ( wprp_view_scopes() as $key => $label ) {
		echo '<label style="display:block;margin:.2rem 0"><input type="checkbox" name="scopes[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $show_on, true ), true, false ) . '> ' . wp_kses_post( $label ) . '</label>';
	}
	echo '<hr style="margin:.8rem 0;border:none;border-top:1px solid #eee">';
	echo '<p style="margin:.2rem 0 .4rem;color:#646970">' . esc_html__( 'Colour of the numbered element pins on the front end. The app red is used by default; pick a custom colour if it does not stand out on a particular site.', 'wp-red-pen' ) . '</p>';
	echo '<label style="display:block;margin:.2rem 0"><input type="checkbox" name="pin_custom" value="1"' . checked( '' !== $pin_color, true, false ) . '> ' . esc_html__( 'Use a custom pin colour:', 'wp-red-pen' ) . ' <input type="color" name="pin_color" value="' . esc_attr( '' !== $pin_color ? $pin_color : '#D32F2F' ) . '" style="vertical-align:middle"></label>';
	echo '<hr style="margin:.8rem 0;border:none;border-top:1px solid #eee">';
	echo '<label style="display:block;margin:.2rem 0"><input type="checkbox" name="dark_mode" value="1"' . checked( (bool) get_option( WPRP_DARK_OPT ), true, false ) . '> ' . esc_html__( 'Dark mode (front-end notes panel)', 'wp-red-pen' ) . '</label>';
	echo '<hr style="margin:.8rem 0;border:none;border-top:1px solid #eee">';
	echo '<p style="margin:.2rem 0 .4rem;color:#646970"><strong>' . esc_html__( 'Custom note types', 'wp-red-pen' ) . '</strong> &mdash; ' . esc_html__( 'one per line as "Label" or "Label|#hexcolor". They appear in every note-type dropdown and show their colour as the note flag.', 'wp-red-pen' ) . '</p>';
	echo '<textarea name="custom_types" rows="3" style="width:100%;max-width:440px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85rem" placeholder="Design nit|#9C27B0&#10;Content|#2E7D32">' . esc_textarea( (string) get_option( WPRP_CUSTOM_TYPES_OPT, '' ) ) . '</textarea>';

	echo '<hr style="margin:.8rem 0;border:none;border-top:1px solid #eee">';
	echo '<p style="margin:.2rem 0 .4rem;color:#646970"><strong>' . esc_html__( 'Connect to Red Pen Hub', 'wp-red-pen' ) . '</strong> &mdash; ' . esc_html__( 'push this site\'s notes to your local Red Pen Hub so they show on the combined board. Copy the Hub URL + token from the Hub\'s "Connect a Site" panel. Notes sync automatically whenever they change, and saving here runs a test push and reports the result.', 'wp-red-pen' ) . '</p>';
	echo '<label style="display:block;margin:.2rem 0">' . esc_html__( 'Hub URL', 'wp-red-pen' ) . '<br><input type="text" name="hub_url" value="' . esc_attr( (string) get_option( WPRP_HUB_URL_OPT, '' ) ) . '" placeholder="http://localhost:3900" style="width:100%;max-width:360px"></label>';
	echo '<label style="display:block;margin:.2rem 0">' . esc_html__( 'Connect token', 'wp-red-pen' ) . '<br><input type="text" name="hub_token" value="' . esc_attr( (string) get_option( WPRP_HUB_TOKEN_OPT, '' ) ) . '" placeholder="' . esc_attr__( 'paste token', 'wp-red-pen' ) . '" style="width:100%;max-width:360px;font-family:ui-monospace,Menlo,Consolas,monospace"></label>';

	// Agent feedback: pick which AI agents/platforms notes can be targeted at.
	// NOTE: a distinct var from $enabled_agents (the slug=>label map used by the audience filter) - this is the raw enabled-slug list for the checkboxes.
	$enabled_agent_slugs = get_option( WPRP_AGENTS_OPT, array() );
	$enabled_agent_slugs = is_array( $enabled_agent_slugs ) ? $enabled_agent_slugs : array();
	$custom_agent        = (string) get_option( WPRP_AGENT_CUSTOM_OPT, '' );
	echo '<hr style="margin:.8rem 0;border:none;border-top:1px solid #eee">';
	$agent_help = __( 'Delegate feedback to an AI coding agent such as Claude or Codex. Tick the agents you use and Save, then choose an agent from any note Assigned menu (new or existing notes). The note moves to the For agents queue for that agent to work on via the REST API or wp-cli. Everything stays local - no AI keys, nothing leaves your site.', 'wp-red-pen' );
	echo '<p style="margin:.2rem 0 .4rem;color:#646970"><strong>' . esc_html__( 'Agent feedback', 'wp-red-pen' ) . '</strong> <span class="dashicons dashicons-editor-help" style="cursor:help;color:#787c82;vertical-align:text-bottom" tabindex="0" role="img" aria-label="' . esc_attr( $agent_help ) . '" title="' . esc_attr( $agent_help ) . '"></span> &mdash; ' . esc_html__( 'enable the AI agents/platforms you use.', 'wp-red-pen' ) . '</p>';
	foreach ( wprp_agent_platforms() as $slug => $label ) {
		echo '<label style="display:inline-block;margin:.1rem 1rem .1rem 0"><input type="checkbox" name="agents[]" value="' . esc_attr( $slug ) . '"' . checked( in_array( $slug, $enabled_agent_slugs, true ), true, false ) . '> ' . esc_html( $label ) . '</label>';
	}
	echo '<p style="margin:.4rem 0 .2rem"><label>' . esc_html__( 'Custom agent name (optional):', 'wp-red-pen' ) . ' <input type="text" name="agent_custom" value="' . esc_attr( $custom_agent ) . '" maxlength="40" style="width:14rem"></label></p>';

	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'wp-red-pen' ) . '</button></p>';
	echo '</form></details>';

	// Client report: a printable, client-facing summary of the notes (the deliverable a
	// freelancer sends). Both the report and its branding (logo, colour, title) are free -
	// wprp_render_client_report() always applies branding in this open-source build.
	$rep_all   = wp_nonce_url( admin_url( 'admin-post.php?action=wprp_report&scope=all' ), 'wprp_report' );
	$rep_open  = wp_nonce_url( admin_url( 'admin-post.php?action=wprp_report&scope=open' ), 'wprp_report' );
	$brand_url = admin_url( 'admin-post.php?action=wprp_save_brand' );
	$brand     = wprp_report_brand();
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect flag
	$brand_saved = isset( $_GET['brand'] ) && 'saved' === sanitize_key( wp_unslash( $_GET['brand'] ) );
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	echo '<details style="margin:.5rem 0 1rem;border:1px solid #dcdcde;border-radius:5px;padding:.4rem .8rem;background:#fff;max-width:760px">';
	echo '<summary style="cursor:pointer;font-weight:600"><span class="dashicons dashicons-media-document" style="vertical-align:text-top"></span> ' . esc_html__( 'Client report', 'wp-red-pen' ) . '</summary>';
	if ( $brand_saved ) {
		echo '<div class="notice notice-success inline" style="margin:.4rem 0"><p>' . esc_html__( 'Report settings saved.', 'wp-red-pen' ) . '</p></div>';
	}
	echo '<p style="margin:.4rem 0 .6rem;color:#646970">' . esc_html__( 'Generate a clean, printable report of the notes to hand a client - open items only, or everything including what is resolved. It opens in a new tab; use your browser Print / Save as PDF to send it.', 'wp-red-pen' ) . '</p>';
	echo '<p style="margin:.2rem 0 .8rem">';
	echo '<a class="button button-primary" href="' . esc_url( $rep_all ) . '" target="_blank" rel="noopener">' . esc_html__( 'Generate report (all)', 'wp-red-pen' ) . '</a> ';
	echo '<a class="button" href="' . esc_url( $rep_open ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open items only', 'wp-red-pen' ) . '</a>';
	echo '</p>';

	// Branding - your logo, an accent colour, and a custom title on the report. Free for all.
	echo '<hr style="margin:.6rem 0;border:none;border-top:1px solid #eee">';
	echo '<form method="post" action="' . esc_url( $brand_url ) . '">';
	wp_nonce_field( 'wprp_save_brand' );
	echo '<p style="margin:.2rem 0 .4rem"><strong>' . esc_html__( 'Branding', 'wp-red-pen' ) . '</strong> &mdash; ' . esc_html__( 'your logo, an accent colour, and a custom title on the report.', 'wp-red-pen' ) . '</p>';
	echo '<label style="display:block;margin:.3rem 0">' . esc_html__( 'Report title', 'wp-red-pen' ) . '<br><input type="text" name="brand_title" value="' . esc_attr( $brand['title'] ) . '" placeholder="' . esc_attr__( 'Acme Co - Website Review', 'wp-red-pen' ) . '" style="width:100%;max-width:360px"></label>';
	echo '<label style="display:block;margin:.3rem 0">' . esc_html__( 'Logo URL', 'wp-red-pen' ) . '<br><input type="text" name="brand_logo" value="' . esc_attr( $brand['logo'] ) . '" placeholder="https://example.com/logo.png" style="width:100%;max-width:360px"></label>';
	echo '<label style="display:block;margin:.3rem 0">' . esc_html__( 'Accent colour', 'wp-red-pen' ) . ' <input type="color" name="brand_color" value="' . esc_attr( $brand['color'] ) . '" style="vertical-align:middle"></label>';
	echo '<label style="display:block;margin:.3rem 0"><input type="checkbox" name="brand_hide_credit" value="1"' . checked( $brand['hide_credit'], true, false ) . '> ' . esc_html__( 'Hide the "Generated with Red Pen" credit on the report', 'wp-red-pen' ) . '</label>';
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save report settings', 'wp-red-pen' ) . '</button></p>';
	echo '</form>';
	echo '</details>';

	// Client review links: generate an unguessable link that lets a non-logged-in client
	// leave notes (read-only on existing ones). Hash-only storage means a link's URL is
	// shown exactly once at creation - the only later action is Revoke.
	$rev_gen_url = admin_url( 'admin-post.php?action=wprp_review_generate' );
	echo '<details style="margin:.5rem 0 1rem;border:1px solid #dcdcde;border-radius:5px;padding:.4rem .8rem;background:#fff;max-width:760px">';
	echo '<summary style="cursor:pointer;font-weight:600"><span class="dashicons dashicons-admin-links" style="vertical-align:text-top"></span> ' . esc_html__( 'Client review links', 'wp-red-pen' ) . '</summary>';
	echo '<p style="margin:.4rem 0 .6rem;color:#646970">' . esc_html__( 'Generate a private link that lets a client leave feedback notes on the front end without a WordPress login. Share it only with people you trust. The link is shown once at creation (the token is stored hashed, like a password) - if you lose it, revoke the link and make a new one. Revoke is the kill switch: it stops the link working immediately.', 'wp-red-pen' ) . '</p>';

	// One-time display of a freshly created link's full URL (carried via a short-lived transient, keyed in the redirect).
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only redirect flag; the URL lives in a server-side transient, not the query string
	$new_key = isset( $_GET['wprp_new_link'] ) ? sanitize_text_field( wp_unslash( $_GET['wprp_new_link'] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
	if ( $new_key ) {
		$new_link = get_transient( 'wprp_new_link_' . $new_key );
		if ( $new_link && is_array( $new_link ) ) {
			delete_transient( 'wprp_new_link_' . $new_key ); // show it once, then it is gone forever
			echo '<div class="notice notice-success inline" style="margin:.4rem 0;max-width:720px"><p style="margin:.4rem 0"><strong>' . esc_html__( 'New review link for', 'wp-red-pen' ) . ' &ldquo;' . esc_html( $new_link['label'] ) . '&rdquo;</strong></p>';
			echo '<p style="margin:.2rem 0;color:#b32d2e"><strong>' . esc_html__( 'Copy this now - it will not be shown again.', 'wp-red-pen' ) . '</strong></p>';
			echo '<p style="margin:.2rem 0"><input type="text" readonly value="' . esc_attr( $new_link['url'] ) . '" onclick="this.select()" style="width:100%;max-width:680px;font-family:ui-monospace,Menlo,Consolas,monospace;font-size:.85rem"></p>';
			echo '</div>';
		}
	}

	echo '<form method="post" action="' . esc_url( $rev_gen_url ) . '" style="margin-top:.4rem;display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end">';
	wp_nonce_field( 'wprp_review_generate' );
	echo '<label style="display:block">' . esc_html__( 'Label (client or project name)', 'wp-red-pen' ) . '<br><input type="text" name="label" required maxlength="80" placeholder="' . esc_attr__( 'Acme Co - new site review', 'wp-red-pen' ) . '" style="width:18rem"></label>';
	echo '<label style="display:block">' . esc_html__( 'Expiry', 'wp-red-pen' ) . '<br><select name="expiry">';
	$expiry_opts = array(
		'0'  => __( 'Never (revoke manually)', 'wp-red-pen' ),
		'7'  => __( '7 days', 'wp-red-pen' ),
		'30' => __( '30 days', 'wp-red-pen' ),
		'90' => __( '90 days', 'wp-red-pen' ),
	);
	foreach ( $expiry_opts as $days => $opt_label ) {
		echo '<option value="' . esc_attr( $days ) . '">' . esc_html( $opt_label ) . '</option>';
	}
	echo '</select></label>';
	echo '<button type="submit" class="button button-primary">' . esc_html__( 'Generate link', 'wp-red-pen' ) . '</button>';
	echo '</form>';

	// Active links table.
	$rev_tokens = wprp_review_tokens();
	if ( $rev_tokens ) {
		echo '<table class="wp-list-table widefat fixed striped" style="margin-top:.8rem;max-width:720px"><thead><tr>';
		echo '<th>' . esc_html__( 'Label', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Created', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Expires', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Status', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Action', 'wp-red-pen' ) . '</th>';
		echo '</tr></thead><tbody>';
		$now = time();
		foreach ( $rev_tokens as $rt ) {
			$expired = ! empty( $rt['expires'] ) && (int) $rt['expires'] <= $now;
			if ( empty( $rt['enabled'] ) ) {
				$status = '<span style="color:#646970">' . esc_html__( 'Revoked', 'wp-red-pen' ) . '</span>';
			} elseif ( $expired ) {
				$status = '<span style="color:#996800">' . esc_html__( 'Expired', 'wp-red-pen' ) . '</span>';
			} else {
				$status = '<span style="color:#197b30">' . esc_html__( 'Active', 'wp-red-pen' ) . '</span>';
			}
			$created_str = ! empty( $rt['created'] ) ? wp_date( get_option( 'date_format' ), (int) $rt['created'] ) : '&mdash;';
			$expires_str = empty( $rt['expires'] ) ? esc_html__( 'Never', 'wp-red-pen' ) : esc_html( wp_date( get_option( 'date_format' ), (int) $rt['expires'] ) );
			echo '<tr>';
			echo '<td>' . esc_html( $rt['label'] ) . '</td>';
			echo '<td>' . esc_html( $created_str ) . '</td>';
			echo '<td>' . $expires_str . '</td>'; // already escaped above
			echo '<td>' . $status . '</td>'; // markup built from translated literals only
			echo '<td>';
			if ( ! empty( $rt['enabled'] ) ) {
				$revoke_url = wp_nonce_url(
					add_query_arg(
						array( 'action' => 'wprp_review_revoke', 'id' => rawurlencode( (string) $rt['id'] ) ),
						admin_url( 'admin-post.php' )
					),
					'wprp_review_revoke_' . $rt['id']
				);
				echo '<a class="button button-small" href="' . esc_url( $revoke_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Revoke this review link? Anyone holding it will lose access immediately.', 'wp-red-pen' ) ) . '\')">' . esc_html__( 'Revoke', 'wp-red-pen' ) . '</a>';
			} else {
				echo '<span style="color:#646970">&mdash;</span>';
			}
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p style="margin:.4rem 0 0;color:#646970"><em>' . esc_html__( 'A link\'s URL is shown only once at creation and cannot be re-displayed (it is stored hashed). The only action on an existing link is Revoke.', 'wp-red-pen' ) . '</em></p>';
	}
	echo '</details>';

	// Status filter tabs.
	$tabs = array(
		'open'     => __( 'Open', 'wp-red-pen' ),
		'progress' => __( 'In Progress', 'wp-red-pen' ),
		'resolved' => __( 'Resolved', 'wp-red-pen' ),
		'all'      => __( 'All', 'wp-red-pen' ),
	);
	echo '<ul class="subsubsub">';
	$i = 0;
	foreach ( $tabs as $key => $label ) {
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $key, 'assignee' => $who, 'audience' => $audience, 'wprp_src' => $source ), admin_url( 'tools.php' ) ) );
		echo ( $i++ ? ' | ' : '' ) . '<li><a href="' . $url . '"' . ( $filter === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul><div style="clear:both"></div>';

	// Assignee filter (preserves the status filter).
	$who_tabs = array(
		''     => __( 'All assignees', 'wp-red-pen' ),
		'me'   => __( 'Assigned to me', 'wp-red-pen' ),
		'none' => __( 'Unassigned', 'wp-red-pen' ),
	);
	echo '<ul class="subsubsub">';
	$i = 0;
	foreach ( $who_tabs as $key => $label ) {
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $filter, 'assignee' => $key, 'audience' => $audience, 'wprp_src' => $source ), admin_url( 'tools.php' ) ) );
		echo ( $i++ ? ' | ' : '' ) . '<li><a href="' . $url . '"' . ( $who === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul><div style="clear:both"></div>';

	// Source filter: All / Client reviews / Dev notes. Lets the dev narrow to reviewer feedback at a glance.
	$src_tabs = array(
		''       => __( 'All sources', 'wp-red-pen' ),
		'review' => __( 'Client reviews', 'wp-red-pen' ),
		'dev'    => __( 'Dev notes', 'wp-red-pen' ),
	);
	echo '<ul class="subsubsub"><li style="font-weight:600;margin-right:.3rem">' . esc_html__( 'Source:', 'wp-red-pen' ) . '</li>';
	$i = 0;
	foreach ( $src_tabs as $key => $label ) {
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $filter, 'assignee' => $who, 'audience' => $audience, 'wprp_src' => $key ), admin_url( 'tools.php' ) ) );
		echo '<li>' . ( $i++ ? ' | ' : '' ) . '<a href="' . $url . '"' . ( $source === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul><div style="clear:both"></div>';

	// Audience filter (only when agent feedback is enabled): Humans / all agents / per-agent.
	if ( $enabled_agents ) {
		$aud_tabs = array(
			''       => __( 'Humans', 'wp-red-pen' ),
			'agents' => __( 'For agents (all)', 'wp-red-pen' ),
		);
		foreach ( $enabled_agents as $aslug => $alabel ) {
			$aud_tabs[ $aslug ] = $alabel;
		}
		echo '<ul class="subsubsub"><li style="font-weight:600;margin-right:.3rem">' . esc_html__( 'Audience:', 'wp-red-pen' ) . '</li>';
		$i = 0;
		foreach ( $aud_tabs as $key => $label ) {
			$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $filter, 'assignee' => $who, 'audience' => $key, 'wprp_src' => $source ), admin_url( 'tools.php' ) ) );
			echo '<li>' . ( $i++ ? ' | ' : '' ) . '<a href="' . $url . '"' . ( $audience === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
		}
		echo '</ul><div style="clear:both"></div>';

		// When a single agent is in focus, surface its two consumption paths (live REST + on-disk JSON brief).
		if ( '' !== $audience && 'agents' !== $audience && isset( $enabled_agents[ $audience ] ) ) {
			$brief_path = wprp_agent_brief_path( $audience );
			$rest_url   = rest_url( WPRP_REST_NS . '/notes?agent=' . rawurlencode( $audience ) . '&status=open' );
			$exists     = file_exists( $brief_path );
			/* translators: %s: agent name. */
			echo '<div class="notice notice-info inline" style="margin:.5rem 0;max-width:760px"><p style="margin:.4rem 0"><strong>' . esc_html( sprintf( __( 'Feeding %s', 'wp-red-pen' ), $enabled_agents[ $audience ] ) ) . '</strong></p>';
			echo '<p style="margin:.2rem 0;color:#646970">' . esc_html__( 'Two local ways for the agent to pull this queue - no keys, nothing leaves your site:', 'wp-red-pen' ) . '</p>';
			echo '<p style="margin:.2rem 0"><strong>' . esc_html__( 'Live REST', 'wp-red-pen' ) . '</strong> ' . esc_html__( '(reads with the caller\'s own credentials):', 'wp-red-pen' ) . ' <code style="user-select:all">' . esc_html( $rest_url ) . '</code></p>';
			echo '<p style="margin:.2rem 0"><strong>' . esc_html__( 'JSON brief', 'wp-red-pen' ) . '</strong> ' . esc_html__( '(read straight off disk):', 'wp-red-pen' ) . ' <code style="user-select:all">' . esc_html( $brief_path ) . '</code>';
			echo $exists ? ' <span style="color:#197b30">' . esc_html__( '(kept up to date automatically)', 'wp-red-pen' ) . '</span>' : ' <span style="color:#996800">' . esc_html__( '(written once this agent has open notes)', 'wp-red-pen' ) . '</span>';
			echo '</p></div>';
		}
	}

	// Local CSV export of the current filter (no external service - the user imports it wherever).
	$export_url = wp_nonce_url(
		add_query_arg(
			array( 'action' => 'wprp_export_csv', 'status' => $filter, 'assignee' => $who ),
			admin_url( 'admin-post.php' )
		),
		'wprp_export_csv'
	);
	echo '<p><a class="button" href="' . esc_url( $export_url ) . '"><span class="dashicons dashicons-download" style="vertical-align:text-top"></span> ' . esc_html__( 'Export CSV', 'wp-red-pen' ) . '</a></p>';

	if ( ! $notes ) {
		echo '<p>' . esc_html__( 'No notes here.', 'wp-red-pen' ) . '</p></div>';
		return;
	}

	// Edit panel: editing a note's text + type from the repo (priority/assignee are inline quick-edits already).
	// Rendered ABOVE the bulk form (its own top-level form) to avoid nesting <form> inside <form>.
	$edit_id = isset( $_GET['wprp_edit'] ) ? (int) $_GET['wprp_edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only view toggle; the save is a nonced POST
	if ( $edit_id ) {
		$en = get_post( $edit_id );
		if ( $en && WPRP_CPT === $en->post_type && 0 === (int) $en->post_parent ) {
			$etype      = (string) get_post_meta( $edit_id, WPRP_META_TYPE, true );
			$cancel_url = remove_query_arg( 'wprp_edit' );
			echo '<form id="wprp-editpanel" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="background:#fff;border:1px solid #dfe3e6;border-left:4px solid #D32F2F;border-radius:6px;padding:1rem 1.1rem;margin:0 0 1rem;max-width:680px">';
			wp_nonce_field( 'wprp_edit_note_' . $edit_id );
			echo '<input type="hidden" name="action" value="wprp_edit_note">';
			echo '<input type="hidden" name="note" value="' . (int) $edit_id . '">';
			echo '<h2 style="margin-top:0;font-size:1rem">' . esc_html__( 'Edit note', 'wp-red-pen' ) . '</h2>';
			echo '<p style="margin:.4rem 0"><textarea name="body" rows="4" required style="width:100%;box-sizing:border-box">' . esc_textarea( $en->post_content ) . '</textarea></p>';
			echo '<p style="margin:.4rem 0"><label>' . esc_html__( 'Type', 'wp-red-pen' ) . ' <select name="type">';
			foreach ( $types as $tk => $tl ) {
				echo '<option value="' . esc_attr( $tk ) . '"' . selected( $tk, ( '' !== $etype ? $etype : 'note' ), false ) . '>' . esc_html( $tl ) . '</option>';
			}
			echo '</select></label></p>';
			echo '<p style="margin:.4rem 0 0"><button type="submit" class="button button-primary">' . esc_html__( 'Save changes', 'wp-red-pen' ) . '</button> <a class="button" href="' . esc_url( $cancel_url ) . '">' . esc_html__( 'Cancel', 'wp-red-pen' ) . '</a></p>';
			echo '</form>';
		}
	}

	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( 'wprp_bulk' );
	echo '<input type="hidden" name="action" value="wprp_bulk">';
	echo '<div class="tablenav top"><div class="alignleft actions bulkactions">';
	echo '<select name="bulk"><option value="">' . esc_html__( 'Bulk actions', 'wp-red-pen' ) . '</option>';
	echo '<option value="open">' . esc_html__( 'Reopen', 'wp-red-pen' ) . '</option>';
	echo '<option value="progress">' . esc_html__( 'Mark In Progress', 'wp-red-pen' ) . '</option>';
	echo '<option value="done">' . esc_html__( 'Resolve', 'wp-red-pen' ) . '</option>';
	echo '<option value="delete">' . esc_html__( 'Delete', 'wp-red-pen' ) . '</option>';
	echo '</select> <button type="submit" class="button" onclick="return this.form.bulk.value!=\'delete\'||confirm(\'' . esc_js( __( 'Permanently delete the selected notes? This cannot be undone.', 'wp-red-pen' ) ) . '\')">' . esc_html__( 'Apply', 'wp-red-pen' ) . '</button>';
	echo '</div></div>';
	echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
	echo '<td class="manage-column check-column"><input type="checkbox" onclick="var c=document.getElementsByClassName(\'wprp-cb\');for(var i=0;i<c.length;i++){c[i].checked=this.checked;}"></td><th>' . esc_html__( 'Type', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Priority', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Note', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Where', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Assigned', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'By', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'When', 'wp-red-pen' ) . '</th><th></th></tr></thead><tbody>';

	$reply_counts = wprp_reply_counts( wp_list_pluck( $notes, 'ID' ) ); // one query, not one-per-row

	foreach ( $notes as $n ) {
		$type     = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
		$target   = (int) get_post_meta( $n->ID, WPRP_META_TARGET, true );
		$resolved = WPRP_STATUS_DONE === $n->post_status;
		$sk       = wprp_status_key( $n->post_status );
		$author   = get_userdata( $n->post_author );

		$resolve_url = wp_nonce_url(
			add_query_arg(
				array( 'action' => 'wprp_resolve', 'note' => $n->ID, 'to' => $resolved ? 'open' : 'done' ),
				admin_url( 'admin-post.php' )
			),
			'wprp_resolve_' . $n->ID
		);
		$delete_url = wp_nonce_url(
			add_query_arg(
				array( 'action' => 'wprp_delete', 'note' => $n->ID ),
				admin_url( 'admin-post.php' )
			),
			'wprp_delete_' . $n->ID
		);

		$priority  = (string) get_post_meta( $n->ID, WPRP_META_PRIORITY, true );
		$assignee  = (int) get_post_meta( $n->ID, WPRP_META_ASSIGNEE, true );
		$au        = $assignee ? get_userdata( $assignee ) : false;
		$prio_bg   = 'high' === $priority ? '#D32F2F' : ( 'low' === $priority ? '#9aa1a7' : '#6b7177' );
		$qe_nonce  = wp_create_nonce( 'wprp_quickedit_' . $n->ID );
		$qe_url    = function ( $field, $value ) use ( $n, $qe_nonce ) {
			return esc_url( add_query_arg( array( 'action' => 'wprp_quickedit', 'note' => $n->ID, 'field' => $field, 'value' => $value, '_wpnonce' => $qe_nonce ), admin_url( 'admin-post.php' ) ) );
		};

		echo '<tr' . ( $resolved ? ' style="opacity:.55"' : '' ) . '>';
		echo '<th scope="row" class="check-column"><input type="checkbox" class="wprp-cb" name="ids[]" value="' . (int) $n->ID . '"></th>';
		$tc = wprp_note_type_color( $type ); echo '<td><span style="background:' . esc_attr( $tc ? $tc : '#D32F2F' ) . ';color:#fff;border-radius:3px;padding:.05rem .35rem;font-size:.72rem;font-weight:600">' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</span></td>';
		echo '<td><select class="wprp-qe" onchange="if(this.value)location.href=this.value">';
		foreach ( wprp_priorities() as $pk => $plabel ) {
			echo '<option value="' . $qe_url( 'priority', $pk ) . '"' . selected( $pk, ( '' !== $priority ? $priority : 'normal' ), false ) . '>' . esc_html( $plabel ) . '</option>';
		}
		echo '</select></td>';
		$shot      = wprp_shot_url( (string) get_post_meta( $n->ID, WPRP_META_SHOT, true ) );
		$shot_html = $shot ? '<a href="' . esc_url( $shot ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $shot ) . '" alt="" style="max-width:180px;height:auto;margin-top:.35rem;border:1px solid #e0e0e0;border-radius:4px;display:block"></a>' : '';
		$ctx       = (string) get_post_meta( $n->ID, WPRP_META_CTX, true );
		$ctx_html  = $ctx ? '<div style="font-size:.7rem;color:#3A3A3C;font-family:monospace;margin-top:.35rem">' . esc_html( $ctx ) . '</div>' : '';
		$reply_n   = isset( $reply_counts[ $n->ID ] ) ? $reply_counts[ $n->ID ] : 0;
		/* translators: %d: number of replies */
		$reply_html = $reply_n ? '<div style="font-size:.72rem;color:#3A3A3C;margin-top:.35rem">' . esc_html( sprintf( _n( '%d reply', '%d replies', $reply_n, 'wp-red-pen' ), $reply_n ) ) . '</div>' : '';
		$anchor_html = get_post_meta( $n->ID, WPRP_META_ANCHOR, true ) ? '<div style="font-size:.72rem;color:#D32F2F;margin-top:.35rem"><span class="dashicons dashicons-location" style="font-size:14px;width:14px;height:14px;vertical-align:text-top"></span> ' . esc_html__( 'Pinned to an element', 'wp-red-pen' ) . '</div>' : '';
		$agent_slug = (string) get_post_meta( $n->ID, WPRP_META_AGENT, true );
		$agent_html = '' !== $agent_slug ? '<div style="font-size:.72rem;color:#7a4ad6;font-weight:600;margin-top:.35rem"><span class="dashicons dashicons-superhero-alt" style="font-size:14px;width:14px;height:14px;vertical-align:text-top"></span> ' . esc_html( wprp_agent_label( $agent_slug ) ? wprp_agent_label( $agent_slug ) : $agent_slug ) . '</div>' : '';
		$cs         = (string) get_post_meta( $n->ID, WPRP_META_CODESCOPE, true );
		$cs_html    = '' !== $cs ? '<div style="font-size:.72rem;color:#3A3A3C;margin-top:.2rem;font-family:monospace">' . esc_html__( 'Code:', 'wp-red-pen' ) . ' ' . esc_html( $cs ) . '</div>' : '';
		echo '<td>' . wprp_kses_note( wpautop( $n->post_content ) ) . $shot_html . $ctx_html . $reply_html . $anchor_html . $agent_html . $cs_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- *_html built with esc_* above
		$ctx_label = (string) get_post_meta( $n->ID, WPRP_META_CTXLABEL, true );
		$level     = (string) get_post_meta( $n->ID, WPRP_META_LEVEL, true );
		$level     = in_array( $level, array( 'template', 'global' ), true ) ? $level : 'page';
		if ( $target ) {
			$where = '<a href="' . esc_url( get_edit_post_link( $target ) ) . '">' . esc_html( '' !== $ctx_label ? $ctx_label : get_the_title( $target ) ) . '</a> <a href="' . esc_url( get_permalink( $target ) ) . '" title="' . esc_attr__( 'View', 'wp-red-pen' ) . '">&#8599;</a>';
		} else {
			$ctx_url = (string) get_post_meta( $n->ID, WPRP_META_URL, true );
			$where   = esc_html( '' !== $ctx_label ? $ctx_label : __( '(no page)', 'wp-red-pen' ) );
			if ( $ctx_url ) {
				$where .= ' <a href="' . esc_url( $ctx_url ) . '" title="' . esc_attr__( 'View', 'wp-red-pen' ) . '" target="_blank" rel="noopener">&#8599;</a>';
			}
		}
		$lvl_label = 'template' === $level ? __( 'Template', 'wp-red-pen' ) : ( 'global' === $level ? __( 'Site-wide', 'wp-red-pen' ) : __( 'Page', 'wp-red-pen' ) );
		$lvl_badge = '<div style="margin-top:.25rem"><span style="font-size:.68rem;font-weight:600;color:#3A3A3C;border:1px solid #dfe3e6;border-radius:3px;padding:0 .3rem">' . esc_html( $lvl_label ) . '</span></div>';
		echo '<td>' . $where . $lvl_badge . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with esc_* above
		echo '<td><select class="wprp-qe" onchange="if(this.value)location.href=this.value">';
		echo '<option value="' . $qe_url( 'assignee', '0' ) . '"' . selected( '' === $agent_slug && 0 === $assignee, true, false ) . '>' . esc_html__( 'Unassigned', 'wp-red-pen' ) . '</option>';
		foreach ( wprp_assignable_users() as $uid => $uname ) {
			echo '<option value="' . $qe_url( 'assignee', $uid ) . '"' . selected( '' === $agent_slug && $uid === $assignee, true, false ) . '>' . esc_html( $uname ) . '</option>';
		}
		$row_agents = wprp_enabled_agents();
		if ( '' !== $agent_slug && ! isset( $row_agents[ $agent_slug ] ) ) {
			$row_agents[ $agent_slug ] = wprp_agent_label( $agent_slug ) ? wprp_agent_label( $agent_slug ) : $agent_slug; // keep a now-disabled agent visible
		}
		if ( $row_agents ) {
			echo '<optgroup label="' . esc_attr__( 'Agents', 'wp-red-pen' ) . '">';
			foreach ( $row_agents as $aslug => $alabel ) {
				echo '<option value="' . $qe_url( 'assignee', 'agent:' . $aslug ) . '"' . selected( $aslug, $agent_slug, false ) . '>' . esc_html( $alabel ) . '</option>';
			}
			echo '</optgroup>';
		}
		echo '</select></td>';
		$via_review = (bool) get_post_meta( $n->ID, WPRP_META_VIA_REVIEW, true );
		if ( $via_review ) {
			$rev_name = (string) get_post_meta( $n->ID, WPRP_META_REVIEWER, true );
			$by_label = '' !== $rev_name ? $rev_name : __( 'Anonymous reviewer', 'wp-red-pen' );
			echo '<td><span style="display:inline-block;background:#D32F2F;color:#fff;border-radius:3px;padding:.05rem .35rem;font-size:.68rem;font-weight:600;margin-bottom:.2rem">' . esc_html__( 'Client', 'wp-red-pen' ) . '</span><br><span>' . esc_html( $by_label ) . '</span></td>';
		} else {
			echo '<td>' . esc_html( $author ? $author->display_name : '' ) . '</td>';
		}
		echo '<td>' . esc_html( get_the_time( get_option( 'date_format' ), $n ) ) . '</td>';
		$statuses_lbl = wprp_statuses();
		$rs_nonce     = wp_create_nonce( 'wprp_resolve_' . $n->ID );
		$mk_status    = function ( $to ) use ( $n, $rs_nonce ) {
			return esc_url( add_query_arg( array( 'action' => 'wprp_resolve', 'note' => $n->ID, 'to' => $to, '_wpnonce' => $rs_nonce ), admin_url( 'admin-post.php' ) ) );
		};
		echo '<td><span style="display:inline-block;margin-bottom:.25rem;font-size:.7rem;font-weight:600;color:#3A3A3C">' . esc_html( $statuses_lbl[ $sk ] ) . '</span><br>';
		if ( 'open' === $sk ) {
			echo '<a class="button button-small" href="' . $mk_status( 'progress' ) . '">' . esc_html__( 'Start', 'wp-red-pen' ) . '</a> ';
			echo '<a class="button button-small" href="' . $mk_status( 'done' ) . '">' . esc_html__( 'Resolve', 'wp-red-pen' ) . '</a> ';
		} elseif ( 'progress' === $sk ) {
			echo '<a class="button button-small" href="' . $mk_status( 'done' ) . '">' . esc_html__( 'Resolve', 'wp-red-pen' ) . '</a> ';
			echo '<a class="button button-small" href="' . $mk_status( 'open' ) . '">' . esc_html__( 'Reopen', 'wp-red-pen' ) . '</a> ';
		} else {
			echo '<a class="button button-small" href="' . $mk_status( 'open' ) . '">' . esc_html__( 'Reopen', 'wp-red-pen' ) . '</a> ';
		}
		echo '<a class="button button-small" href="' . esc_url( add_query_arg( 'wprp_edit', $n->ID ) . '#wprp-editpanel' ) . '">' . esc_html__( 'Edit', 'wp-red-pen' ) . '</a> ';
		echo '<a class="button button-small button-link-delete" style="color:#b32d2e" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this note permanently, including its replies and screenshot? This cannot be undone.', 'wp-red-pen' ) ) . '\');">' . esc_html__( 'Delete', 'wp-red-pen' ) . '</a></td>';
		echo '</tr>';
	}
	echo '</tbody></table></form></div>';
}

/** admin-post handler: bulk Resolve / In Progress / Reopen / Delete on the selected notes. */
add_action(
	'admin_post_wprp_bulk',
	function () {
		if ( ! wprp_user_can()
			|| ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wprp_bulk' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		$bulk = isset( $_POST['bulk'] ) ? sanitize_key( wp_unslash( $_POST['bulk'] ) ) : '';
		$ids  = isset( $_POST['ids'] ) ? array_filter( array_map( 'intval', (array) wp_unslash( $_POST['ids'] ) ) ) : array();
		if ( $ids && $bulk ) {
			$delete = ( 'delete' === $bulk );
			if ( $delete && ! current_user_can( 'delete_others_posts' ) ) {
				wp_die( esc_html__( 'You do not have permission to delete notes.', 'wp-red-pen' ) );
			}
			$status = ( 'done' === $bulk ) ? WPRP_STATUS_DONE : ( 'progress' === $bulk ? WPRP_STATUS_PROGRESS : ( 'open' === $bulk ? WPRP_STATUS_OPEN : '' ) );
			foreach ( $ids as $id ) {
				if ( WPRP_CPT !== get_post_type( $id ) ) {
					continue;
				}
				if ( $delete ) {
					wp_delete_post( $id, true );
				} elseif ( '' !== $status ) {
					wprp_set_status( $id, $status );
				}
			}
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=wp-red-pen' ) );
		exit;
	}
);

/** admin-post handler: inline quick-edit of a single note's assignee or priority from the repo table. */
add_action(
	'admin_post_wprp_quickedit',
	function () {
		$note = isset( $_GET['note'] ) ? (int) $_GET['note'] : 0;
		if ( ! $note || ! wprp_user_can()
			|| ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wprp_quickedit_' . $note )
			|| WPRP_CPT !== get_post_type( $note ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		$field = isset( $_GET['field'] ) ? sanitize_key( wp_unslash( $_GET['field'] ) ) : '';
		$value = isset( $_GET['value'] ) ? sanitize_text_field( wp_unslash( $_GET['value'] ) ) : '';
		if ( 'assignee' === $field ) {
			if ( 0 === strpos( $value, 'agent:' ) ) {
				// assigning to an agent: set the agent, clear the human assignee (mutually exclusive)
				$slug = substr( $value, 6 );
				if ( '' !== wprp_agent_label( $slug ) ) {
					update_post_meta( $note, WPRP_META_AGENT, $slug );
					delete_post_meta( $note, WPRP_META_ASSIGNEE );
				}
			} else {
				// assigning to a human (or unassigning): clear any agent
				$uid = (int) $value;
				delete_post_meta( $note, WPRP_META_AGENT );
				if ( $uid > 0 && user_can( $uid, WPRP_CAP ) ) {
					update_post_meta( $note, WPRP_META_ASSIGNEE, $uid );
				} else {
					delete_post_meta( $note, WPRP_META_ASSIGNEE );
				}
			}
		} elseif ( 'priority' === $field ) {
			$prios = wprp_priorities();
			update_post_meta( $note, WPRP_META_PRIORITY, isset( $prios[ $value ] ) ? $value : 'normal' );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=wp-red-pen' ) );
		exit;
	}
);

/** admin-post handler: save the repo edit-panel (note text + type) via wprp_update_note. */
add_action(
	'admin_post_wprp_edit_note',
	function () {
		$note = isset( $_POST['note'] ) ? (int) $_POST['note'] : 0;
		if ( ! $note || ! wprp_user_can()
			|| ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wprp_edit_note_' . $note )
			|| WPRP_CPT !== get_post_type( $note ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		// Raw (unslashed) body - wprp_update_note runs wprp_kses_note + the empty-body guard itself.
		$body = isset( $_POST['body'] ) ? wp_unslash( $_POST['body'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in wprp_update_note via wprp_kses_note
		$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'note';
		wprp_update_note( $note, array( 'body' => $body, 'type' => $type ) );
		$ref = wp_get_referer();
		wp_safe_redirect( $ref ? remove_query_arg( 'wprp_edit', $ref ) : admin_url( 'tools.php?page=wp-red-pen' ) );
		exit;
	}
);

/** admin-post handler for the repository Resolve/Reopen buttons. */
add_action(
	'admin_post_wprp_resolve',
	function () {
		$note = isset( $_GET['note'] ) ? (int) $_GET['note'] : 0;
		if ( ! $note || ! wprp_user_can()
			|| ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wprp_resolve_' . $note ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		$to_param = isset( $_GET['to'] ) ? sanitize_key( wp_unslash( $_GET['to'] ) ) : 'open';
		$to       = ( 'done' === $to_param ) ? WPRP_STATUS_DONE : ( 'progress' === $to_param ? WPRP_STATUS_PROGRESS : WPRP_STATUS_OPEN );
		wprp_set_status( $note, $to );
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=wp-red-pen' ) );
		exit;
	}
);

/** admin-post handler for the repository Delete button (permanent; cleans up replies + screenshot via before_delete_post). */
add_action(
	'admin_post_wprp_delete',
	function () {
		$note = isset( $_GET['note'] ) ? (int) $_GET['note'] : 0;
		if ( ! $note || ! wprp_user_can()
			|| ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wprp_delete_' . $note ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		// Permanent delete is destructive + irreversible, so it needs more than the broad
			// edit_posts cap that gates add/resolve/reply - require the delete-others capability.
			if ( ! current_user_can( 'delete_others_posts' ) ) {
				wp_die( esc_html__( 'You do not have permission to delete notes.', 'wp-red-pen' ) );
			}
			// Only ever delete our own note CPT. Force-delete fires before_delete_post, which
		// sweeps the screenshot file and any child replies.
		if ( WPRP_CPT === get_post_type( $note ) ) {
			wp_delete_post( $note, true );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'tools.php?page=wp-red-pen' ) );
		exit;
	}
);

/** admin-post handler: save the global "where Red Pen appears" visibility setting. */
add_action(
	'admin_post_wprp_save_visibility',
	function () {
		if ( ! wprp_user_can()
			|| ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wprp_save_visibility' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		$valid    = array_keys( wprp_view_scopes() );
		$submitted = isset( $_POST['scopes'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['scopes'] ) ) : array();
		$scopes   = array_values( array_intersect( $submitted, $valid ) );
		update_option( WPRP_SHOW_OPT, $scopes, false ); // empty array = show nowhere (a valid choice); not autoloaded

		// Custom pin colour: only stored when the box is ticked AND it's a valid hex, else cleared (use the app default).
		$pin = ( ! empty( $_POST['pin_custom'] ) && isset( $_POST['pin_color'] ) ) ? sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['pin_color'] ) ) ) : '';
		update_option( WPRP_PINCOLOR_OPT, $pin ? $pin : '', false );

		// Dark mode for the front-end panel (global toggle).
		update_option( WPRP_DARK_OPT, ! empty( $_POST['dark_mode'] ) ? 1 : 0, false );

		// Custom note types: raw "Label|#color" lines, parsed by wprp_custom_note_types() on read.
		$custom_types = isset( $_POST['custom_types'] ) ? sanitize_textarea_field( wp_unslash( $_POST['custom_types'] ) ) : '';
		update_option( WPRP_CUSTOM_TYPES_OPT, $custom_types, false );

		// Red Pen Hub connection (push target). Saving with both set triggers an immediate sync.
		// A schemeless host like "localhost:3900" makes esc_url_raw() read "localhost:" as a
		// disallowed protocol and return '' - which silently breaks the connection (token saves,
		// URL blanks, push no-ops forever). Default a missing scheme to http:// before sanitizing.
		$hub_url_raw = isset( $_POST['hub_url'] ) ? trim( (string) wp_unslash( $_POST['hub_url'] ) ) : '';
		if ( '' !== $hub_url_raw && ! preg_match( '#^https?://#i', $hub_url_raw ) ) {
			$hub_url_raw = 'http://' . $hub_url_raw;
		}
		$hub_url = esc_url_raw( $hub_url_raw );
		update_option( WPRP_HUB_URL_OPT, $hub_url, false );
		$hub_token = isset( $_POST['hub_token'] ) ? sanitize_text_field( wp_unslash( $_POST['hub_token'] ) ) : '';
		update_option( WPRP_HUB_TOKEN_OPT, $hub_token, false );
		// Confirm the connection on save with a BLOCKING push so a silent failure is impossible.
		$hub_status = '';
		if ( $hub_url && $hub_token ) {
			$res = wprp_push_to_hub( true );
			if ( is_wp_error( $res ) ) {
				$hub_status = 'err';
			} else {
				$code = (int) wp_remote_retrieve_response_code( $res );
				if ( 401 === $code ) {
					$hub_status = 'token';
				} elseif ( $code >= 200 && $code < 300 ) {
					$hub_status = 'ok';
				} else {
					$hub_status = 'err';
				}
			}
		} elseif ( $hub_url || $hub_token ) {
			$hub_status = 'partial';
		}

		// Agent feedback: enabled platforms (intersected with the known set) + one optional custom agent label.
		$known_agents = array_keys( wprp_agent_platforms() );
		$sub_agents   = isset( $_POST['agents'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['agents'] ) ) : array();
		update_option( WPRP_AGENTS_OPT, array_values( array_intersect( $sub_agents, $known_agents ) ), false );
		$custom_agent = isset( $_POST['agent_custom'] ) ? sanitize_text_field( wp_unslash( $_POST['agent_custom'] ) ) : '';
		update_option( WPRP_AGENT_CUSTOM_OPT, mb_substr( $custom_agent, 0, 40 ), false );

		// Rebuild every enabled agent's JSON brief so enabling/renaming/reassigning is reflected at once.
		foreach ( array_keys( wprp_enabled_agents() ) as $brief_slug ) {
			wprp_write_agent_brief( $brief_slug );
		}

		$redirect = admin_url( 'tools.php?page=wp-red-pen' );
		if ( $hub_status ) {
			$redirect = add_query_arg( 'hub', $hub_status, $redirect );
		}
		wp_safe_redirect( $redirect );
		exit;
	}
);

/** admin-post handler: generate a client review link. Shows the full URL exactly once via a short-lived transient. */
add_action(
	'admin_post_wprp_review_generate',
	function () {
		if ( ! wprp_user_can()
			|| ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wprp_review_generate' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		$label = isset( $_POST['label'] ) ? sanitize_text_field( wp_unslash( $_POST['label'] ) ) : '';
		$label = mb_substr( $label, 0, 80 );
		if ( '' === $label ) {
			wp_die( esc_html__( 'A label is required for a review link.', 'wp-red-pen' ) );
		}
		// Expiry is a whitelist of day-durations; anything else means never.
		$days    = isset( $_POST['expiry'] ) ? (int) wp_unslash( $_POST['expiry'] ) : 0;
		$expires = in_array( $days, array( 7, 30, 90 ), true ) ? ( time() + ( $days * DAY_IN_SECONDS ) ) : 0;

		$gen = wprp_generate_review_token( $label, $expires );
		$url = home_url( '/?' . WPRP_REVIEW_COOKIE . '=' . rawurlencode( $gen['token'] ) );

		// Stash the one-time URL in a short-lived transient keyed by a random handle, so the raw
		// token never travels in a redirect query string (it would land in server/browser logs).
		$key = wp_generate_password( 16, false );
		set_transient(
			'wprp_new_link_' . $key,
			array( 'url' => $url, 'label' => $label ),
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'wprp_new_link', $key, admin_url( 'tools.php?page=wp-red-pen' ) ) );
		exit;
	}
);

/** admin-post handler: revoke a client review link (flips enabled to false; record kept for the audit trail). */
add_action(
	'admin_post_wprp_review_revoke',
	function () {
		$id = isset( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : '';
		if ( '' === $id || ! wprp_user_can()
			|| ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wprp_review_revoke_' . $id ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		wprp_revoke_review_token( $id );
		wp_safe_redirect( admin_url( 'tools.php?page=wp-red-pen' ) );
		exit;
	}
);

/**
 * Neutralise CSV formula injection. A cell whose first character is one of = + - @
 * (or a leading tab/CR) is treated as a formula by Excel/Sheets; prefix it with a
 * single quote so it imports as literal text. Applied to every user-supplied cell.
 */
function wprp_csv_cell( $value ) {
	$value   = (string) $value;
	$trimmed = ltrim( $value ); // a leading space before =/+/-/@ still triggers the formula parser
	if ( '' !== $trimmed && false !== strpos( "=+-@\t\r", $trimmed[0] ) ) {
		return "'" . $value;
	}
	return $value;
}

/** admin-post handler: stream the repository as a CSV download (respects the status filter). */
add_action(
	'admin_post_wprp_export_csv',
	function () {
		if ( ! wprp_user_can()
			|| ! isset( $_GET['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wprp_export_csv' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}

		$filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'open';
		$filter = in_array( $filter, array( 'open', 'progress', 'resolved', 'all' ), true ) ? $filter : 'open';
		// 'all' must include In Progress too, or those notes vanish from every export.
		$statuses = 'all' === $filter
			? wprp_all_statuses()
			: array( 'resolved' === $filter ? WPRP_STATUS_DONE : ( 'progress' === $filter ? WPRP_STATUS_PROGRESS : WPRP_STATUS_OPEN ) );

		$who = isset( $_GET['assignee'] ) ? sanitize_key( wp_unslash( $_GET['assignee'] ) ) : '';
		$who = in_array( $who, array( 'me', 'none' ), true ) ? $who : '';

		$query_args = array(
			'post_type'      => WPRP_CPT,
			'post_status'    => $statuses,
			'post_parent'    => 0, // top-level notes only, not replies
			'posts_per_page' => 5000,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( 'me' === $who ) {
			$query_args['meta_key']   = WPRP_META_ASSIGNEE; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			$query_args['meta_value'] = (int) get_current_user_id(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		} elseif ( 'none' === $who ) {
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'relation' => 'OR',
				array( 'key' => WPRP_META_ASSIGNEE, 'compare' => 'NOT EXISTS' ),
				array( 'key' => WPRP_META_ASSIGNEE, 'value' => 0 ),
			);
		}
		$notes = get_posts( $query_args );
		$types        = wprp_note_types();
		$priorities   = wprp_priorities();
		$severities   = wprp_severities();
		$statuses_lbl = wprp_statuses();

		$filename = 'wp-red-pen-' . $filter . '-' . gmdate( 'Ymd' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Type', 'Priority', 'Severity', 'Level', 'Status', 'Note', 'Where', 'URL', 'Assignee', 'Author', 'Environment', 'When' ) );
		foreach ( $notes as $n ) {
			$type      = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
			$priority  = (string) get_post_meta( $n->ID, WPRP_META_PRIORITY, true );
			$severity  = (string) get_post_meta( $n->ID, WPRP_META_SEVERITY, true );
			$target    = (int) get_post_meta( $n->ID, WPRP_META_TARGET, true );
			$assignee  = (int) get_post_meta( $n->ID, WPRP_META_ASSIGNEE, true );
			$au        = $assignee ? get_userdata( $assignee ) : false;
			$author    = get_userdata( $n->post_author );
			$ctx_label = (string) get_post_meta( $n->ID, WPRP_META_CTXLABEL, true );
			$lvl_raw   = (string) get_post_meta( $n->ID, WPRP_META_LEVEL, true );
			$level     = 'template' === $lvl_raw ? 'Template' : ( 'global' === $lvl_raw ? 'Site-wide' : 'Page' );
			fputcsv(
				$out,
				array(
					$n->ID,
					isset( $types[ $type ] ) ? $types[ $type ] : $type,
					isset( $priorities[ $priority ] ) ? $priorities[ $priority ] : '',
					isset( $severities[ $severity ] ) ? $severities[ $severity ] : '',
					$level,
					$statuses_lbl[ wprp_status_key( $n->post_status ) ],
					wprp_csv_cell( wp_strip_all_tags( $n->post_content ) ),
					wprp_csv_cell( '' !== $ctx_label ? $ctx_label : ( $target ? get_the_title( $target ) : '' ) ),
					wprp_csv_cell( $target ? get_permalink( $target ) : (string) get_post_meta( $n->ID, WPRP_META_URL, true ) ),
					wprp_csv_cell( $au ? $au->display_name : '' ),
					wprp_csv_cell( $author ? $author->display_name : '' ),
					wprp_csv_cell( (string) get_post_meta( $n->ID, WPRP_META_CTX, true ) ),
					get_the_time( 'Y-m-d H:i', $n ),
				)
			);
		}
		fclose( $out );
		exit;
	}
);

// ---------------------------------------------------------------------------
// Client report - the branded, printable client deliverable (free for all)
// ---------------------------------------------------------------------------

/** admin-post handler: save the client-report branding. */
add_action(
	'admin_post_wprp_save_brand',
	function () {
		if ( ! wprp_user_can()
			|| ! isset( $_POST['_wpnonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'wprp_save_brand' ) ) {
			wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
		}
		$color = isset( $_POST['brand_color'] ) ? sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['brand_color'] ) ) ) : '';
		$brand = array(
			'title'       => isset( $_POST['brand_title'] ) ? sanitize_text_field( wp_unslash( $_POST['brand_title'] ) ) : '',
			'logo'        => isset( $_POST['brand_logo'] ) ? esc_url_raw( trim( (string) wp_unslash( $_POST['brand_logo'] ) ) ) : '',
			'color'       => $color ? $color : '',
			'hide_credit' => ! empty( $_POST['brand_hide_credit'] ) ? 1 : 0,
		);
		update_option( WPRP_BRAND_OPT, $brand, false );
		wp_safe_redirect( admin_url( 'tools.php?page=wp-red-pen&brand=saved' ) );
		exit;
	}
);

add_action( 'admin_post_wprp_report', 'wprp_render_client_report' );

/**
 * Render the client report: a standalone, printable HTML document of the site's
 * notes grouped by status - the deliverable a freelancer hands a client ("here is
 * what you asked for, here is what is done"). The PLAIN report is FREE (export is
 * never gated); the branded layer (logo, accent colour, custom title, hidden Red
 * Pen credit) is applied only when wprp_is_pro(). Capability-gated; meant to be
 * printed to PDF from the browser via the Print button. Agent-queue notes and
 * internal-only fields (assignee, agent, code scope, browser context) are omitted
 * so it stays a clean, client-appropriate deliverable.
 */
function wprp_render_client_report() {
	if ( ! wprp_user_can()
		|| ! isset( $_GET['_wpnonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wprp_report' ) ) {
		wp_die( esc_html__( 'Invalid request.', 'wp-red-pen' ) );
	}

	$scope    = isset( $_GET['scope'] ) ? sanitize_key( wp_unslash( $_GET['scope'] ) ) : 'all';
	$scope    = in_array( $scope, array( 'all', 'open' ), true ) ? $scope : 'all';
	$statuses = 'open' === $scope ? array( WPRP_STATUS_OPEN, WPRP_STATUS_PROGRESS ) : wprp_all_statuses();

	// Human notes only - a client never sees the agent queue.
	$notes = get_posts(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => $statuses,
			'post_parent'    => 0,
			'posts_per_page' => 1000,
			'orderby'        => 'date',
			'order'          => 'ASC',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'relation' => 'OR',
					array( 'key' => WPRP_META_AGENT, 'compare' => 'NOT EXISTS' ),
					array( 'key' => WPRP_META_AGENT, 'value' => '', 'compare' => '=' ),
				),
			),
		)
	);

	$pro    = wprp_is_pro();
	$brand  = wprp_report_brand();
	$accent = '#D32F2F';
	if ( $pro ) {
		$c      = sanitize_hex_color( $brand['color'] );
		$accent = $c ? $c : '#D32F2F';
	}
	$site  = get_bloginfo( 'name' );
	/* translators: %s: site name. */
	$title = ( $pro && '' !== $brand['title'] ) ? $brand['title'] : sprintf( __( '%s - Review Report', 'wp-red-pen' ), $site );

	$buckets = array( 'open' => array(), 'progress' => array(), 'resolved' => array() );
	foreach ( $notes as $n ) {
		$a = wprp_note_to_array( $n );
		if ( isset( $buckets[ $a['statusKey'] ] ) ) {
			$buckets[ $a['statusKey'] ][] = $a;
		}
	}
	$labels    = wprp_statuses();
	$total     = count( $notes );
	$generated = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
	$pill      = array( 'open' => $accent, 'progress' => '#996800', 'resolved' => '#197b30' );

	nocache_headers();
	if ( ! headers_sent() ) {
		header( 'Content-Type: text/html; charset=utf-8' );
	}

	echo '<!doctype html><html lang="' . esc_attr( get_bloginfo( 'language' ) ) . '"><head><meta charset="utf-8">';
	echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
	echo '<meta name="robots" content="noindex,nofollow">';
	echo '<title>' . esc_html( $title ) . '</title>';
	echo '<style>'
		. ':root{--accent:' . esc_html( $accent ) . '}'
		. '*{box-sizing:border-box}'
		. 'body{margin:0;background:#f3f4f6;color:#1e2227;font:15px/1.6 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif}'
		. '.page{max-width:820px;margin:0 auto;background:#fff;padding:0 0 3rem}'
		. '.bar{display:flex;gap:1rem;justify-content:center;padding:.7rem;background:#e9ebef;position:sticky;top:0}'
		. '.bar button,.bar a{font:inherit;font-weight:600;cursor:pointer;border-radius:7px;padding:.5rem 1rem;border:1px solid #c7ccd3;background:#fff;color:#1e2227;text-decoration:none}'
		. '.bar .print{background:var(--accent);color:#fff;border-color:var(--accent)}'
		. '.head{border-top:6px solid var(--accent);padding:1.8rem 2rem 1.4rem}'
		. '.head .logo{max-height:64px;max-width:260px;margin-bottom:.9rem}'
		. '.head h1{margin:.1rem 0;font-size:1.7rem;letter-spacing:-.01em}'
		. '.head .sub{color:#5b616a;font-size:.92rem}'
		. '.summary{display:flex;flex-wrap:wrap;gap:.6rem;padding:0 2rem 1.2rem}'
		. '.stat{flex:1;min-width:120px;border:1px solid #e4e6ea;border-radius:9px;padding:.7rem .9rem}'
		. '.stat .n{font-size:1.5rem;font-weight:800}.stat .l{font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7178}'
		. 'section{padding:0 2rem}'
		. 'h2.sec{font-size:1.05rem;margin:1.4rem 0 .5rem;padding-bottom:.3rem;border-bottom:2px solid var(--accent)}'
		. '.note{border:1px solid #e4e6ea;border-radius:10px;padding:.9rem 1rem;margin:.6rem 0;page-break-inside:avoid}'
		. '.note .top{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-bottom:.35rem}'
		. '.tag{font-size:.72rem;font-weight:700;border-radius:999px;padding:.12rem .55rem;border:1px solid #d3d7dd;color:#3a3f46}'
		. '.tag.status{color:#fff;border:none}.tag.sev{border-color:var(--accent);color:var(--accent)}'
		. '.note .loc{font-size:.82rem;color:#6b7178;margin-bottom:.35rem}.note .loc a{color:inherit}'
		. '.note .body{font-size:.95rem}.note .body p{margin:.3rem 0}'
		. '.note img.shot{max-width:100%;border:1px solid #e0e2e6;border-radius:7px;margin-top:.5rem}'
		. '.note .when{font-size:.76rem;color:#8a9099;margin-top:.5rem}'
		. '.empty{padding:2.5rem 2rem;color:#6b7178;text-align:center}'
		. '.credit{margin-top:2rem;padding:1rem 2rem 0;border-top:1px solid #e4e6ea;color:#8a9099;font-size:.8rem;text-align:center}'
		. '@media print{body{background:#fff}.page{max-width:none}.bar{display:none}.head{border-top-width:6px}.note{border-color:#d7dade}}'
		. '</style></head><body><div class="page">';

	// Toolbar (screen only).
	echo '<div class="bar">';
	echo '<button type="button" class="print" onclick="window.print()">' . esc_html__( 'Print / Save as PDF', 'wp-red-pen' ) . '</button>';
	$other_scope = 'open' === $scope ? 'all' : 'open';
	$other_url   = wp_nonce_url( admin_url( 'admin-post.php?action=wprp_report&scope=' . $other_scope ), 'wprp_report' );
	$other_label = 'open' === $scope ? __( 'Show all (incl. resolved)', 'wp-red-pen' ) : __( 'Show open items only', 'wp-red-pen' );
	echo '<a href="' . esc_url( $other_url ) . '">' . esc_html( $other_label ) . '</a>';
	echo '</div>';

	// Header.
	echo '<div class="head">';
	if ( $pro && '' !== $brand['logo'] ) {
		echo '<img class="logo" src="' . esc_url( $brand['logo'] ) . '" alt="' . esc_attr( $site ) . '">';
	}
	echo '<h1>' . esc_html( $title ) . '</h1>';
	echo '<div class="sub">' . esc_html( $site ) . ' &middot; ' . esc_html( home_url() ) . ' &middot; ' . esc_html( $generated ) . '</div>';
	echo '</div>';

	// Summary tiles.
	echo '<div class="summary">';
	$tiles = array(
		'open'     => count( $buckets['open'] ),
		'progress' => count( $buckets['progress'] ),
	);
	if ( 'open' !== $scope ) {
		$tiles['resolved'] = count( $buckets['resolved'] );
	}
	echo '<div class="stat"><div class="n">' . (int) $total . '</div><div class="l">' . esc_html__( 'Total', 'wp-red-pen' ) . '</div></div>';
	foreach ( $tiles as $k => $c ) {
		echo '<div class="stat"><div class="n">' . (int) $c . '</div><div class="l">' . esc_html( $labels[ $k ] ) . '</div></div>';
	}
	echo '</div>';

	if ( 0 === $total ) {
		echo '<div class="empty">' . esc_html__( 'No notes to report yet.', 'wp-red-pen' ) . '</div>';
	}

	// One section per status bucket (in workflow order), skipping empties.
	$order = ( 'open' === $scope ) ? array( 'open', 'progress' ) : array( 'open', 'progress', 'resolved' );
	foreach ( $order as $key ) {
		$items = $buckets[ $key ];
		if ( ! $items ) {
			continue;
		}
		echo '<section><h2 class="sec">' . esc_html( $labels[ $key ] ) . ' (' . count( $items ) . ')</h2>';
		foreach ( $items as $a ) {
			echo '<div class="note"><div class="top">';
			echo '<span class="tag">' . esc_html( $a['typeLabel'] ) . '</span>';
			if ( '' !== $a['severityLabel'] ) {
				echo '<span class="tag sev">' . esc_html( $a['severityLabel'] ) . '</span>';
			}
			$pc = isset( $pill[ $a['statusKey'] ] ) ? $pill[ $a['statusKey'] ] : $accent;
			echo '<span class="tag status" style="background:' . esc_attr( $pc ) . '">' . esc_html( $a['statusLabel'] ) . '</span>';
			echo '</div>';

			$loc_label = '' !== $a['ctxLabel'] ? $a['ctxLabel'] : ( $a['target'] ? get_the_title( $a['target'] ) : '' );
			$loc_url   = $a['target'] ? get_permalink( $a['target'] ) : '';
			if ( '' === $loc_label && 'global' === $a['level'] ) {
				$loc_label = __( 'Site-wide', 'wp-red-pen' );
			}
			if ( '' !== $loc_label ) {
				echo '<div class="loc">' . esc_html__( 'On:', 'wp-red-pen' ) . ' ';
				echo $loc_url ? '<a href="' . esc_url( $loc_url ) . '">' . esc_html( $loc_label ) . '</a>' : esc_html( $loc_label );
				echo '</div>';
			}

			echo '<div class="body">' . wp_kses_post( $a['body'] ) . '</div>';
			if ( '' !== $a['shot'] ) {
				echo '<img class="shot" src="' . esc_url( $a['shot'] ) . '" alt="' . esc_attr__( 'Screenshot', 'wp-red-pen' ) . '">';
			}

			$when = sprintf( /* translators: %s: date. */ esc_html__( 'Added %s', 'wp-red-pen' ), esc_html( $a['date'] ) );
			if ( 'resolved' === $a['statusKey'] && '' !== $a['resolvedAt'] ) {
				$rts = strtotime( $a['resolvedAt'] );
				if ( $rts ) {
					$when .= ' &middot; ' . sprintf( /* translators: %s: date. */ esc_html__( 'Resolved %s', 'wp-red-pen' ), esc_html( wp_date( get_option( 'date_format' ), $rts ) ) );
				}
			}
			echo '<div class="when">' . $when . '</div>'; // built from escaped pieces above
			echo '</div>';
		}
		echo '</section>';
	}

	if ( ! ( $pro && $brand['hide_credit'] ) ) {
		echo '<div class="credit">' . esc_html__( 'Generated with Red Pen', 'wp-red-pen' ) . '</div>';
	}

	echo '</div></body></html>';
	exit;
}

// ---------------------------------------------------------------------------
// Admin: load dashicons on our screens (for the menu icon + meta box chrome)
// ---------------------------------------------------------------------------
add_action(
	'admin_enqueue_scripts',
	function () {
		wp_enqueue_style( 'dashicons' );
	}
);
