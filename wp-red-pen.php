<?php
/**
 * Plugin Name:       WP Red Pen
 * Plugin URI:        https://tracydigitalmedia.com/wp-red-pen/
 * Description:       A logged-in review layer. Editors and admins flip on Dev Mode and drop notes, flags, and suggested edits on any post or page from a floating button. Notes collect on the post's edit screen and in a shared to-do repository.
 * Version:           0.10.8
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

define( 'WPRP_VERSION',     '0.10.8' );
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
define( 'WPRP_META_ASSIGNEE', '_wprp_assignee' ); // assigned user id (0 = unassigned)
define( 'WPRP_META_ANCHOR', '_wprp_anchor' );   // element-pin anchor (JSON: selector + relative x/y)
define( 'WPRP_META_CTXKEY', '_wprp_ctx_key' );  // context key: post:ID | term:tax:ID | pt_archive:slug | tpl:* | home | search | 404 ...
define( 'WPRP_META_CTXLABEL', '_wprp_ctx_label' ); // human label for the context
define( 'WPRP_META_LEVEL',  '_wprp_level' );    // page | template
define( 'WPRP_SHOT_DIR',    'wp-red-pen' );     // uploads subfolder for screenshots
define( 'WPRP_REST_NS',     'wprp/v1' );
define( 'WPRP_DBVER_OPT',   'wprp_db_version' ); // schema version (for one-time data migrations)
define( 'WPRP_SHOW_OPT',    'wprp_show_on' );    // global: which view scopes show the widget
define( 'WPRP_PINCOLOR_OPT', 'wprp_pin_color' ); // global: custom hex for the numbered element pins ('' = app accent)
define( 'WPRP_DARK_OPT',     'wprp_dark' );       // global: dark mode for the front-end panel ('' / 1)
define( 'WPRP_CUSTOM_TYPES_OPT', 'wprp_custom_types' ); // global: user-defined note types ("Label|#hexcolor" per line)
define( 'WPRP_META_AGENT',     '_wprp_agent' );     // agent feedback: target agent slug ('' = a human note)
define( 'WPRP_META_CODESCOPE', '_wprp_codescope' ); // agent feedback: optional code scope / file reference (free text)
define( 'WPRP_AGENTS_OPT',     'wprp_agents' );      // global: enabled agent platform slugs (agent feedback dormant when empty)
define( 'WPRP_AGENT_CUSTOM_OPT', 'wprp_agent_custom' ); // global: one optional custom agent label

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
		'suggestion' => __( 'Suggested edit', 'wp-red-pen' ),
		'bug'        => __( 'Bug / problem', 'wp-red-pen' ),
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
	$level   = ( isset( $context['level'] ) && 'template' === $context['level'] ) ? 'template' : 'page';
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

function wprp_create_note( $target_id, $body, $type = 'note', $url = '', $shot = '', $ctx = '', $priority = 'normal', $assignee = 0, $anchor = '', $context = array(), $agent = '', $codescope = '' ) {
	if ( ! wprp_user_can() ) {
		return new WP_Error( 'wprp_forbidden', __( 'You cannot add notes.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	$body = trim( wprp_kses_note( $body ) );
	if ( '' === $body ) {
		return new WP_Error( 'wprp_empty', __( 'The note is empty.', 'wp-red-pen' ), array( 'status' => 400 ) );
	}
	$types = wprp_note_types();
	$type  = isset( $types[ $type ] ) ? $type : 'note';

	$id = wp_insert_post(
		array(
			'post_type'    => WPRP_CPT,
			'post_status'  => WPRP_STATUS_OPEN,
			'post_author'  => get_current_user_id(),
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

	$anchor = wprp_sanitize_anchor( $anchor );
	if ( '' !== $anchor ) {
		update_post_meta( $id, WPRP_META_ANCHOR, $anchor );
	}

	if ( '' !== (string) $shot ) {
		$file = wprp_save_shot( $id, (string) $shot );
		if ( '' !== $file ) {
			update_post_meta( $id, WPRP_META_SHOT, $file );
		}
	}

	// Agent notes: rebuild the brief once, now that EVERY meta (codescope, url, anchor, shot) is
	// written. The meta hook above may have fired a partial snapshot mid-create; this supersedes it.
	if ( '' !== $agent && '' !== wprp_agent_label( $agent ) ) {
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
function wprp_create_reply( $parent_id, $body ) {
	if ( ! wprp_user_can() ) {
		return new WP_Error( 'wprp_forbidden', __( 'You cannot reply.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	$parent = get_post( $parent_id );
	if ( ! $parent || WPRP_CPT !== $parent->post_type || (int) $parent->post_parent !== 0 ) {
		return new WP_Error( 'wprp_missing', __( 'Note not found.', 'wp-red-pen' ), array( 'status' => 404 ) );
	}
	$body = trim( wprp_kses_note( $body ) );
	if ( '' === $body ) {
		return new WP_Error( 'wprp_empty', __( 'The reply is empty.', 'wp-red-pen' ), array( 'status' => 400 ) );
	}
	return wp_insert_post(
		array(
			'post_type'    => WPRP_CPT,
			'post_status'  => WPRP_STATUS_OPEN,
			'post_parent'  => (int) $parent_id,
			'post_author'  => get_current_user_id(),
			'post_content' => $body,
			'post_title'   => wp_trim_words( wp_strip_all_tags( $body ), 8, '...' ),
		),
		true
	);
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

/** Filesystem path of an agent's JSON brief inside the deny-protected screenshots folder. */
function wprp_agent_brief_path( $slug ) {
	return trailingslashit( wprp_shot_dir() ) . 'agent-' . sanitize_file_name( (string) $slug ) . '.json';
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
	$assignee = (int) get_post_meta( $note->ID, WPRP_META_ASSIGNEE, true );
	$au       = $assignee ? get_userdata( $assignee ) : false;
	$level    = (string) get_post_meta( $note->ID, WPRP_META_LEVEL, true );
	$level    = ( 'template' === $level ) ? 'template' : 'page';
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
		'target'     => (int) get_post_meta( $note->ID, WPRP_META_TARGET, true ),
		'shot'       => wprp_shot_url( (string) get_post_meta( $note->ID, WPRP_META_SHOT, true ) ),
		'ctx'         => (string) get_post_meta( $note->ID, WPRP_META_CTX, true ),
		'priority'    => $priority,
		'priorityLabel' => isset( $prios[ $priority ] ) ? $prios[ $priority ] : $prios['normal'],
		'assignee'    => $assignee,
		'assigneeName' => $au ? $au->display_name : '',
		'anchor'      => (string) get_post_meta( $note->ID, WPRP_META_ANCHOR, true ),
		'level'       => $level,
		'ctxKey'      => (string) get_post_meta( $note->ID, WPRP_META_CTXKEY, true ),
		'ctxLabel'    => (string) get_post_meta( $note->ID, WPRP_META_CTXLABEL, true ),
		'agent'       => (string) get_post_meta( $note->ID, WPRP_META_AGENT, true ),
		'agentLabel'  => wprp_agent_label( (string) get_post_meta( $note->ID, WPRP_META_AGENT, true ) ),
		'codeScope'   => (string) get_post_meta( $note->ID, WPRP_META_CODESCOPE, true ),
		'replies'     => array_map( 'wprp_reply_to_array', is_array( $replies ) ? $replies : wprp_get_replies( $note->ID ) ),
	);
}

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
		$perm = function () {
			return wprp_user_can();
		};

		register_rest_route(
			WPRP_REST_NS,
			'/notes',
			array(
				array(
					'methods'             => 'GET',
					'permission_callback' => $perm,
					'callback'            => function ( $req ) {
						$target = (int) $req->get_param( 'target' );
						$status = (string) $req->get_param( 'status' );
						$keys   = (string) $req->get_param( 'keys' );
							$agent  = (string) $req->get_param( 'agent' );
							$notes  = ( '' !== $agent )
									? wprp_get_notes_for_agent( $agent, $status ? $status : 'any' )
									: ( ( '' !== $keys )
								? wprp_get_notes_for_context( explode( ',', $keys ), $status ? $status : 'any' )
								: wprp_get_notes_for( $target, $status ? $status : 'any' ) );
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
								$out[] = wprp_note_to_array( $gnote, isset( $rep_map[ $gnote->ID ] ) ? $rep_map[ $gnote->ID ] : array() );
							}
							return rest_ensure_response( $out );
					},
				),
				array(
					'methods'             => 'POST',
					'permission_callback' => $perm,
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
							(string) $req->get_param( 'codescope' )
						);
						if ( is_wp_error( $id ) ) {
							return $id;
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
				'permission_callback' => $perm,
				'callback'            => function ( $req ) {
					$res = wprp_create_reply( (int) $req['id'], (string) $req->get_param( 'body' ) );
					if ( is_wp_error( $res ) ) {
						return $res;
					}
					return rest_ensure_response( wprp_note_to_array( get_post( (int) $req['id'] ) ) );
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
							'priority'      => $req->get_param( 'priority' ),
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
				'title' => '<span class="ab-icon dashicons dashicons-edit" style="top:2px"></span>' . esc_html( $label ),
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
		if ( ! wprp_devmode_on() ) {
			return;
		}
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
		// Assignee options: Unassigned + humans, plus (when agent feedback is on) an Agents group.
		// Agent options use value "agent:<slug>"; human options are the numeric user id.
		$agents    = wprp_enabled_agents();
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
		// Level options built from the current view: This page + (when distinct) This template.
		$level_opts = '<option value="page">' . esc_html( $ctx['page']['label'] ) . '</option>';
		if ( '' !== $ctx['template']['key'] ) {
			$level_opts .= '<option value="template">' . esc_html( $ctx['template']['label'] ) . '</option>';
		}
		$cfg = wp_json_encode(
			array(
				'root'     => esc_url_raw( rest_url( WPRP_REST_NS ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'url'      => esc_url_raw( home_url( add_query_arg( array() ) ) ),
				'page'     => $ctx['page'],     // { key, label, target }
				'template' => $ctx['template'], // { key, label }
				'priming'  => wprp_priming_data( array_values( array_filter( array( $ctx['page']['key'], $ctx['template']['key'] ) ) ) ),
			)
		);
		?>
		<div id="wprp-root"<?php echo get_option( WPRP_DARK_OPT ) ? ' class="wprp-dark"' : ''; ?> data-cfg='<?php echo esc_attr( $cfg ); ?>'>
			<button type="button" id="wprp-fab" aria-expanded="false" aria-haspopup="dialog" aria-controls="wprp-panel" aria-label="<?php esc_attr_e( 'Red Pen notes', 'wp-red-pen' ); ?>" title="<?php esc_attr_e( 'Red Pen notes', 'wp-red-pen' ); ?>">
				<span class="dashicons dashicons-edit" aria-hidden="true"></span>
				<span id="wprp-fab-count" class="wprp-count" aria-hidden="true" hidden></span>
			</button>
			<section id="wprp-panel" hidden tabindex="-1" role="dialog" aria-label="<?php esc_attr_e( 'Red Pen notes', 'wp-red-pen' ); ?>">
				<div id="wprp-resize" class="wprp-resize" role="separator" tabindex="0" aria-orientation="vertical" aria-valuemin="300" aria-valuenow="420" aria-label="<?php esc_attr_e( 'Resize the panel (arrow keys to widen or narrow)', 'wp-red-pen' ); ?>" title="<?php esc_attr_e( 'Drag or use arrow keys to resize', 'wp-red-pen' ); ?>"></div>
				<header class="wprp-head">
					<strong><?php esc_html_e( 'Red Pen', 'wp-red-pen' ); ?></strong>
					<span class="wprp-page"><?php echo esc_html( $ctx['page']['label'] ); ?></span>
					<a class="wprp-repo-link" href="<?php echo esc_url( admin_url( 'tools.php?page=wp-red-pen' ) ); ?>" target="_blank" rel="noopener" title="<?php esc_attr_e( 'Open the notes repository', 'wp-red-pen' ); ?>" aria-label="<?php esc_attr_e( 'Open the notes repository', 'wp-red-pen' ); ?>"><span class="dashicons dashicons-list-view" aria-hidden="true"></span></a>
						<button type="button" class="wprp-x" id="wprp-close" aria-label="<?php esc_attr_e( 'Close', 'wp-red-pen' ); ?>">&times;</button>
				</header>
				<div class="wprp-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Filter notes', 'wp-red-pen' ); ?>">
						<button type="button" class="wprp-tab is-active" id="wprp-tab-open" role="tab" aria-selected="true" aria-controls="wprp-list"><?php esc_html_e( 'Open', 'wp-red-pen' ); ?></button>
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
						<select id="wprp-assignee" aria-label="<?php esc_attr_e( 'Assign to', 'wp-red-pen' ); ?>"><?php echo $user_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
						<label class="wprp-levellabel"><?php esc_html_e( 'Reporting level', 'wp-red-pen' ); ?>
							<select id="wprp-level" aria-label="<?php esc_attr_e( 'Reporting level', 'wp-red-pen' ); ?>"><?php echo $level_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
						</label>
						<?php if ( $agents ) : ?>
						<input type="text" id="wprp-codescope" maxlength="300" placeholder="<?php esc_attr_e( 'Code scope for agent (optional) e.g. includes/foo.php:42', 'wp-red-pen' ); ?>" aria-label="<?php esc_attr_e( 'Code scope', 'wp-red-pen' ); ?>">
						<?php endif; ?>
					</div>
					<textarea id="wprp-body" rows="3" placeholder="<?php esc_attr_e( 'Add a note, flag, or suggested edit...', 'wp-red-pen' ); ?>" required></textarea>
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
		#wprp-root{--wprp-red:#D32F2F;--wprp-red-dark:#B71C1C;--wprp-accent:#FF5252;--wprp-ink:#1E2225;--wprp-gray:#3A3A3C;position:fixed;right:20px;bottom:20px;z-index:99990;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
			/* The HTML [hidden] attribute is the weakest possible style, so an id/class rule that
			   sets display (e.g. #wprp-panel{display:flex}) silently defeats it and the toggle does
			   nothing. This rule (specificity 1,1,0) outranks those and keeps [hidden] authoritative. */
			#wprp-root [hidden]{display:none}
			/* Dark mode (Display settings toggle) - front-end panel only; scoped to #wprp-root.wprp-dark. */
			#wprp-root.wprp-dark #wprp-panel{background:#23272b;color:#e6e9ec;border-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-note{background:#2a2f34;border-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-muted,#wprp-root.wprp-dark .wprp-meta,#wprp-root.wprp-dark .wprp-ctx,#wprp-root.wprp-dark .wprp-reply-meta,#wprp-root.wprp-dark .wprp-assignee,#wprp-root.wprp-dark .wprp-levellabel{color:#9aa0a6}
			#wprp-root.wprp-dark .wprp-form{background:#1f2327;border-top-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-form select,#wprp-root.wprp-dark .wprp-form textarea,#wprp-root.wprp-dark .wprp-replytext{background:#1a1d20;color:#e6e9ec;border-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-reply{background:#1f2327}
			#wprp-root.wprp-dark .wprp-replies{border-top-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-resolve,#wprp-root.wprp-dark .wprp-locate,#wprp-root.wprp-dark .wprp-edit,#wprp-root.wprp-dark .wprp-replysend,#wprp-root.wprp-dark .wprp-shotbtn{background:#2a2f34;color:#aeb4ba;border-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-prio-normal,#wprp-root.wprp-dark .wprp-note .wprp-level{background:#3a3f44;color:#cfd4d8;border-color:#4a4f55}
			#wprp-root.wprp-dark .wprp-tabs{background:#23272b;border-bottom-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-status,#wprp-root.wprp-dark .wprp-more-toggle,#wprp-root.wprp-dark .wprp-shotbtn{background:#1a1d20;color:#cfd4d8;border-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-pin-info{background:#2a2f34;color:#cfd4d8;border-color:#3a3f44}
			#wprp-root.wprp-dark .wprp-editbar{background:#3a2526;border-color:#6b3a3c;color:#ff8a80}
		#wprp-fab{width:52px;height:52px;border-radius:50%;border:none;background:var(--wprp-red);color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(211,47,47,.45);display:flex;align-items:center;justify-content:center;position:relative;transition:transform .12s,background .12s}
		#wprp-fab:hover{transform:translateY(-2px);background:var(--wprp-red-dark)}
		#wprp-fab .dashicons{width:26px;height:26px;font-size:26px}
		.wprp-count{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:#fff;color:var(--wprp-red);font-size:11px;font-weight:700;line-height:18px;text-align:center;box-shadow:0 1px 3px rgba(30,34,37,.3)}
		#wprp-panel{position:absolute;right:0;bottom:64px;width:420px;max-width:calc(100vw - 40px);max-height:70vh;display:flex;flex-direction:column;background:#fff;color:var(--wprp-ink);border:1px solid #d6dade;border-radius:10px;box-shadow:0 10px 34px rgba(30,34,37,.28);overflow:hidden}
			/* left-edge drag handle: the panel is right-anchored, so dragging the left edge widens it */
			.wprp-resize{position:absolute;left:0;top:0;width:9px;height:100%;cursor:ew-resize;z-index:6;touch-action:none}
			.wprp-resize::before{content:"";position:absolute;left:2px;top:50%;transform:translateY(-50%);width:3px;height:36px;border-radius:2px;background:#cfd4d8;transition:background .12s}
			.wprp-resize:hover::before{background:var(--wprp-red);height:54px}
			#wprp-panel.is-resizing{user-select:none}
		.wprp-head{display:flex;align-items:center;gap:.5rem;padding:.6rem .75rem;background:var(--wprp-red);color:#fff}
		.wprp-head .wprp-page{font-size:.78rem;opacity:.85;margin-left:auto;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.wprp-x{background:none;border:none;color:#fff;font-size:20px;line-height:1;cursor:pointer;padding:0 0 0 .25rem}
		.wprp-repo-link{color:#fff;display:inline-flex;align-items:center;text-decoration:none;opacity:.9;padding:0 .1rem}
		.wprp-repo-link:hover{opacity:1}
		.wprp-repo-link .dashicons{font-size:18px;width:18px;height:18px}
		.wprp-list{padding:.5rem .75rem;overflow-y:auto;flex:1;min-height:60px}
		.wprp-muted{color:var(--wprp-gray);font-size:.85rem;margin:.4rem 0}
		.wprp-note{border:1px solid #e6e9ec;border-left:3px solid var(--wprp-red);border-radius:6px;padding:.45rem .6rem;margin-bottom:.5rem;font-size:.86rem}
		.wprp-note.is-resolved{opacity:.55;border-left-color:var(--wprp-gray)}
		.wprp-note .wprp-meta{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;font-size:.72rem;color:var(--wprp-gray);margin-bottom:.25rem}
		.wprp-tag{background:var(--wprp-red);color:#fff;border-radius:3px;padding:.02rem .3rem;font-weight:600}
		.wprp-note .wprp-body p{margin:.2rem 0}
			.wprp-ctx{margin-top:.3rem;font-size:.68rem;color:var(--wprp-gray);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
			.wprp-replies{margin-top:.4rem;border-top:1px dashed #e6e9ec;padding-top:.4rem}
			.wprp-reply{font-size:.8rem;padding:.2rem .4rem;margin-bottom:.3rem;background:#f3f5f6;border-radius:4px}
			.wprp-reply-meta{font-size:.68rem;color:var(--wprp-gray);margin-bottom:.1rem}
			.wprp-reply-body p{margin:.15rem 0}
			.wprp-replyform{display:flex;gap:.3rem;margin-top:.25rem}
			.wprp-replytext{flex:1;min-width:0;border:1px solid #cfd4d8;border-radius:4px;padding:.25rem .4rem;font:inherit;font-size:.8rem;resize:vertical;box-sizing:border-box}
			.wprp-replysend{background:#fff;border:1px solid #cfd4d8;border-radius:4px;color:var(--wprp-ink);font-size:.78rem;padding:.2rem .55rem;cursor:pointer;white-space:nowrap}
			.wprp-replysend:hover{border-color:var(--wprp-red);color:var(--wprp-red)}
		.wprp-meta .wprp-actions{margin-left:auto;display:inline-flex;align-items:center;gap:.35rem}
		.wprp-resolve{background:none;border:1px solid #cfd4d8;border-radius:4px;color:var(--wprp-gray);font-size:.72rem;cursor:pointer;padding:.1rem .4rem}
		.wprp-resolve:hover{border-color:var(--wprp-red);color:var(--wprp-red)}
		.wprp-locate{display:inline-flex;align-items:center;justify-content:center;background:none;border:1px solid #cfd4d8;border-radius:4px;color:var(--wprp-gray);cursor:pointer;padding:.1rem .3rem}
		.wprp-locate:hover{border-color:var(--wprp-red);color:var(--wprp-red)}
		.wprp-locate .dashicons{font-size:15px;width:15px;height:15px}
		/* locate highlight: a red bordered box drawn with padding around the pinned element */
		#wprp-locate-hl{position:fixed;z-index:99987;border:3px solid var(--wprp-red);border-radius:4px;background:rgba(211,47,47,.08);box-shadow:0 0 0 2px rgba(255,255,255,.5);pointer-events:none;transition:opacity .25s}
		.wprp-edit{background:none;border:1px solid #cfd4d8;border-radius:4px;color:var(--wprp-gray);font-size:.72rem;cursor:pointer;padding:.1rem .4rem}
			.wprp-edit:hover{border-color:var(--wprp-red);color:var(--wprp-red)}
			.wprp-editbar{display:flex;align-items:center;justify-content:space-between;gap:.5rem;font-size:.78rem;color:var(--wprp-red);background:#fff4f4;border:1px solid #f3c0c0;border-radius:5px;padding:.25rem .5rem}
			.wprp-edit-cancel{background:none;border:none;color:var(--wprp-gray);text-decoration:underline;cursor:pointer;font-size:.78rem;padding:0}
			.wprp-edit-cancel:hover{color:var(--wprp-red)}
			.wprp-form{display:flex;flex-direction:column;gap:.4rem;padding:.6rem .75rem;border-top:1px solid #e6e9ec;background:#f7f9fa}
		.wprp-form select,.wprp-form textarea{width:100%;border:1px solid #cfd4d8;border-radius:5px;padding:.35rem .5rem;font:inherit;font-size:.86rem;box-sizing:border-box}
		.wprp-form textarea{resize:vertical}
			.wprp-formrow{display:flex;gap:.4rem}
			.wprp-formrow select{flex:1;min-width:0}
			.wprp-prio{border-radius:3px;padding:.02rem .3rem;font-size:.68rem;font-weight:600;border:1px solid transparent}
			.wprp-prio-high{background:var(--wprp-red);color:#fff}
			.wprp-prio-normal{background:#eef1f3;color:var(--wprp-gray);border-color:#dfe3e6}
			.wprp-prio-low{background:transparent;color:var(--wprp-gray);border-color:#dfe3e6}
			.wprp-assignee{color:var(--wprp-gray);font-size:.72rem}
			.wprp-levellabel{display:flex;flex-direction:column;gap:.15rem;font-size:.7rem;color:var(--wprp-gray);font-weight:600}
			.wprp-levellabel select{font-weight:400}
			.wprp-note .wprp-level{background:#eef1f3;color:var(--wprp-gray);border:1px solid #dfe3e6;border-radius:3px;padding:.02rem .3rem;font-size:.66rem;font-weight:600}
		.wprp-submit{align-self:flex-end;background:var(--wprp-red);color:#fff;border:none;border-radius:5px;padding:.4rem .9rem;cursor:pointer;font-size:.86rem}
		.wprp-submit:hover{background:var(--wprp-red-dark)}
		.wprp-submit:disabled{opacity:.6;cursor:default}
		.wprp-shotrow{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
		.wprp-shotbtn{display:inline-flex;align-items:center;gap:.25rem;background:#fff;border:1px solid #cfd4d8;border-radius:5px;color:var(--wprp-ink);font-size:.82rem;padding:.3rem .6rem;cursor:pointer}
		.wprp-shotbtn:hover{border-color:var(--wprp-red);color:var(--wprp-red)}
		.wprp-shotbtn .dashicons{font-size:16px;width:16px;height:16px}
		.wprp-shot-preview{position:relative;display:inline-block}
		.wprp-shot-preview img{height:40px;width:auto;max-width:120px;border:1px solid #cfd4d8;border-radius:4px;display:block;object-fit:cover}
		.wprp-shot-clear{position:absolute;top:-7px;right:-7px;width:18px;height:18px;border-radius:50%;border:none;background:var(--wprp-ink);color:#fff;font-size:13px;line-height:1;cursor:pointer;padding:0}
		.wprp-note .wprp-shot{margin-top:.35rem;display:block}
		.wprp-note .wprp-shot img{max-width:100%;border:1px solid #e6e9ec;border-radius:5px;display:block}
		/* full-screen drag-to-capture overlay */
		#wprp-capture{position:fixed;inset:0;z-index:99999;cursor:crosshair;background:rgba(30,34,37,.28)}
		#wprp-capture .wprp-selbox{position:absolute;border:2px dashed var(--wprp-red);background:rgba(211,47,47,.12);pointer-events:none}
		#wprp-capture .wprp-hint,#wprp-pinmode .wprp-hint{position:fixed;top:14px;left:50%;transform:translateX(-50%);background:var(--wprp-ink);color:#fff;font-family:-apple-system,sans-serif;font-size:.82rem;padding:.4rem .8rem;border-radius:6px;pointer-events:none}
			/* element-pin: form row + indicator */
			.wprp-pinrow{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
			.wprp-pin-info{font-size:.78rem;color:var(--wprp-gray);display:inline-flex;align-items:center;gap:.3rem;background:#eef1f3;border:1px solid #dfe3e6;border-radius:4px;padding:.1rem .2rem .1rem .45rem}
			.wprp-pin-clear{border:none;background:var(--wprp-ink);color:#fff;width:16px;height:16px;border-radius:50%;font-size:12px;line-height:1;cursor:pointer;padding:0}
			/* element-pin: full-screen picker */
			#wprp-pinmode{position:fixed;inset:0;z-index:99999;cursor:crosshair;background:rgba(30,34,37,.10)}
			.wprp-pinhl{position:fixed;border:2px solid var(--wprp-red);background:rgba(211,47,47,.12);pointer-events:none;z-index:99999;box-sizing:border-box}
			/* element-pin: the placed markers */
			#wprp-pinlayer{position:fixed;inset:0;z-index:99988;pointer-events:none}
			<?php if ( $wprp_pin_color ) { echo '#wprp-pinlayer{--wprp-pin:' . $wprp_pin_color . '}'; } // custom pin colour, scoped to the layer the markers actually live in ?>
			.wprp-pin{position:fixed;transform:translate(-50%,-50%);min-width:22px;height:22px;padding:0 5px;border-radius:11px;background:var(--wprp-pin,#D32F2F);color:#fff;border:2px solid #fff;box-shadow:0 2px 6px rgba(30,34,37,.4);font-size:11px;font-weight:700;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;pointer-events:auto;box-sizing:border-box}
			.wprp-pin:hover{filter:brightness(0.9)}
			.wprp-pin.is-resolved{background:var(--wprp-gray);opacity:.65}
			.wprp-note.wprp-flash{animation:wprp-flash 1.3s ease}
			@keyframes wprp-flash{0%{background:rgba(211,47,47,.20)}100%{background:transparent}}
		#wprp-busy{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(30,34,37,.18);font-family:-apple-system,sans-serif}
		#wprp-busy span{background:var(--wprp-ink);color:#fff;font-size:.85rem;padding:.5rem 1rem;border-radius:6px}
		#wprp-toast{position:fixed;left:50%;bottom:84px;transform:translateX(-50%);z-index:100000;background:var(--wprp-red-dark);color:#fff;font-family:-apple-system,sans-serif;font-size:.82rem;padding:.45rem .9rem;border-radius:6px;box-shadow:0 4px 14px rgba(30,34,37,.4);max-width:80vw;text-align:center}
			/* Keyboard focus: a visible ring on every interactive surface, legible over any site background. */
			#wprp-root :focus-visible{outline:2px solid var(--wprp-red);outline-offset:2px}
			#wprp-fab:focus-visible,.wprp-pin:focus-visible{outline:none;box-shadow:0 0 0 2px #fff,0 0 0 5px var(--wprp-red)}
			.wprp-resize:focus-visible{outline:2px solid var(--wprp-red);outline-offset:-2px}
			/* Open / Resolved tab strip */
			.wprp-tabs{display:flex;gap:.25rem;padding:.4rem .75rem 0;background:#fff;border-bottom:1px solid #e6e9ec}
			.wprp-tab{flex:1;background:none;border:none;border-bottom:2px solid transparent;color:var(--wprp-gray);font:inherit;font-size:.8rem;font-weight:600;padding:.4rem .25rem;margin-bottom:-1px;cursor:pointer}
			.wprp-tab:hover{color:var(--wprp-red)}
			.wprp-tab.is-active{color:var(--wprp-red);border-bottom-color:var(--wprp-red)}
			/* on the Resolved tab the notes are the content, so don't dim them */
			.wprp-list-resolved .wprp-note.is-resolved{opacity:1}
			/* collapsible advanced fields in the add-note form */
			.wprp-more-toggle{background:#fff;border:1px solid #cfd4d8;border-radius:5px;color:var(--wprp-gray);font:inherit;font-size:.82rem;padding:.35rem .6rem;cursor:pointer;white-space:nowrap}
			.wprp-more-toggle:hover{border-color:var(--wprp-red);color:var(--wprp-red)}
			.wprp-more{display:flex;flex-direction:column;gap:.4rem}
			/* the Undo action inside a toast */
			.wprp-toast-action{background:none;border:1px solid rgba(255,255,255,.55);color:#fff;border-radius:4px;font:inherit;font-size:.78rem;font-weight:600;padding:.12rem .5rem;margin-left:.6rem;cursor:pointer}
			.wprp-toast-action:hover{background:rgba(255,255,255,.18)}
			.wprp-replytext{min-height:2.4em}
			/* 3-state status: per-note dropdown + In Progress accent (amber) */
			.wprp-status{background:#fff;border:1px solid #cfd4d8;border-radius:4px;color:var(--wprp-gray);font:inherit;font-size:.72rem;padding:.05rem .2rem;cursor:pointer;max-width:9.5em}
			.wprp-status:hover{border-color:var(--wprp-red)}
			.wprp-note.wprp-st-progress{border-left-color:#E8A100}
			.wprp-pin-progress{background:#E8A100}
			.wprp-pin-progress:hover{background:#C98A00}
			/* Respect the user's reduced-motion preference: kill transitions, the flash, and the resize accent grow. */
			@media (prefers-reduced-motion: reduce){
				#wprp-fab,#wprp-fab:hover{transition:none;transform:none}
				.wprp-resize::before,.wprp-resize:hover::before{transition:none}
				#wprp-locate-hl{transition:none}
				.wprp-note.wprp-flash{animation:none}
			}
	</style>
	<script id="wprp-js">
	(function () {
		var root = document.getElementById('wprp-root');
		if (!root) { return; }
		var cfg = JSON.parse(root.getAttribute('data-cfg'));
		var fab = document.getElementById('wprp-fab');
		var panel = document.getElementById('wprp-panel');
		var list = document.getElementById('wprp-list');
		var form = document.getElementById('wprp-form');
		var body = document.getElementById('wprp-body');
		var typeSel = document.getElementById('wprp-type');
			var prioSel = document.getElementById('wprp-priority');
			var assigneeSel = document.getElementById('wprp-assignee');
			var levelSel = document.getElementById('wprp-level');
			var codeScopeInput = document.getElementById('wprp-codescope'); // null when agent feedback is off
			// Agents live in the assignee dropdown as value "agent:<slug>"; split into {assignee, agent}.
			function splitAssignee(v) { return (v && v.indexOf('agent:') === 0) ? { assignee: 0, agent: v.slice(6) } : { assignee: (v || '0'), agent: '' }; }
			// The current view's contexts (key/label/target) the server computed for this page.
			function ctxForLevel(lvl) { return (lvl === 'template' && cfg.template && cfg.template.key) ? cfg.template : cfg.page; }
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
			opts.headers = Object.assign({ 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }, opts.headers || {});
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
			return '<div class="wprp-note wprp-st-' + sk + (sk === 'resolved' ? ' is-resolved' : '') + '" data-id="' + n.id + '">' +
				'<div class="wprp-meta"><span class="wprp-tag"' + (n.typeColor ? ' style="background:' + esc(n.typeColor) + '"' : '') + '>' + esc(n.typeLabel) + '</span>' +
				'<span class="wprp-prio wprp-prio-' + esc(n.priority || 'normal') + '">' + esc(n.priorityLabel) + '</span>' +
				(n.level === 'template' ? '<span class="wprp-level" title="' + esc(n.ctxLabel || '') + '"><?php echo esc_js( __( 'Template', 'wp-red-pen' ) ); ?></span>' : '') +
				'<span>' + esc(n.author) + '</span><span>' + esc(n.date) + '</span>' +
				(n.assigneeName ? '<span class="wprp-assignee">&rarr; ' + esc(n.assigneeName) + '</span>' : '') +
				'<span class="wprp-actions">' +
				(n.anchor ? '<button type="button" class="wprp-locate" title="<?php echo esc_js( __( 'Highlight the pinned element', 'wp-red-pen' ) ); ?>" aria-label="<?php echo esc_js( __( 'Highlight the pinned element', 'wp-red-pen' ) ); ?>"><span class="dashicons dashicons-search"></span></button>' : '') +
				'<select class="wprp-status" aria-label="<?php echo esc_js( __( 'Status', 'wp-red-pen' ) ); ?>">' +
					'<option value="open"' + (sk === 'open' ? ' selected' : '') + '>' + esc(TAB_OPEN) + '</option>' +
					'<option value="progress"' + (sk === 'progress' ? ' selected' : '') + '>' + esc(TAB_PROGRESS) + '</option>' +
					'<option value="resolved"' + (sk === 'resolved' ? ' selected' : '') + '>' + esc(TAB_RESOLVED) + '</option>' +
				'</select>' +
				'<button type="button" class="wprp-edit"><?php echo esc_js( __( 'Edit', 'wp-red-pen' ) ); ?></button></span></div>' +
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
				list.innerHTML = '<p class="wprp-muted">' + emptyMsgFor(currentTab) + '</p>';
			} else {
				list.innerHTML = notes.map(noteHtml).join('');
			}
			if (tabOpenBtn && tabResolvedBtn && tabProgressBtn) {
				tabOpenBtn.textContent = TAB_OPEN + ' (' + openCount + ')';
				tabProgressBtn.textContent = TAB_PROGRESS + ' (' + progressCount + ')';
				tabResolvedBtn.textContent = TAB_RESOLVED + ' (' + resolvedCount + ')';
				tabOpenBtn.classList.toggle('is-active', currentTab === 'open');
				tabProgressBtn.classList.toggle('is-active', currentTab === 'progress');
				tabResolvedBtn.classList.toggle('is-active', currentTab === 'resolved');
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
			var keys = [cfg.page && cfg.page.key, cfg.template && cfg.template.key].filter(Boolean).join(',');
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
		function showEmptyIfNeeded() { if (!list.querySelector('.wprp-note')) { list.innerHTML = '<p class="wprp-muted">' + (currentTab === 'resolved' ? EMPTY_RESOLVED : EMPTY_OPEN) + '</p>'; } }
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
			if (show) { wprpLastFocus = document.activeElement; load(); try { panel.focus(); } catch (e) {} }
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
		function applyW(w) { var cw = clampW(w); panel.style.width = cw + 'px'; if (resizeHandle) { resizeHandle.setAttribute('aria-valuenow', String(Math.round(cw))); resizeHandle.setAttribute('aria-valuemax', String(Math.round(maxW()))); } }
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
				panel.classList.remove('is-resizing');
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
				panel.classList.add('is-resizing');
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
			var resolved = !wrap.classList.contains('is-resolved');
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
			api('/notes/' + rform.getAttribute('data-id') + '/replies', { method: 'POST', body: JSON.stringify({ body: rtext }) })
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
					var payload = { body: text, type: typeSel.value, priority: prioSel.value, assignee: eaa.assignee, agent: eaa.agent, codescope: codeScopeInput ? codeScopeInput.value : '', level: elvl, ctx_key: ectx.key, ctx_label: ectx.label, target: (elvl === 'page' ? (cfg.page.target || 0) : 0) };
					if (pendingShot) { payload.shot = pendingShot; } else if (shotRemove) { payload.shot_remove = 1; }
					if (pendingAnchor) { payload.anchor = JSON.stringify(pendingAnchor); } else if (anchorRemove) { payload.anchor_remove = 1; }
				api('/notes/' + editingId, { method: 'POST', body: JSON.stringify(payload) })
					.then(function (data) { exitEdit(); submit.disabled = false; patchNoteInPlace(data); })
					.catch(function () { submit.disabled = false; toast(SAVE_FAILED); });
				return;
			}
			var lvl = levelSel ? levelSel.value : 'page';
			var lctx = ctxForLevel(lvl);
			var caa = splitAssignee(assigneeSel.value);
			api('/notes', { method: 'POST', body: JSON.stringify({ body: text, type: typeSel.value, url: cfg.url, shot: pendingShot || '', ctx: buildCtx(), priority: prioSel.value, assignee: caa.assignee, anchor: pendingAnchor ? JSON.stringify(pendingAnchor) : '', level: lvl, ctx_key: lctx.key, ctx_label: lctx.label, target: (lvl === 'page' ? (cfg.page.target || 0) : 0), agent: caa.agent, codescope: codeScopeInput ? codeScopeInput.value : '' }) })
				.then(function (data) { body.value = ''; clearShot(); clearAnchor(); submit.disabled = false; applyNewNote(data); })
				.catch(function () { submit.disabled = false; toast(SAVE_FAILED); });
		});

		// ---- edit mode: prefill the form from an existing note; submit PATCHes it ----
		function enterEdit(n) {
			editingId = n.id;
			shotRemove = false; anchorRemove = false;
			pendingShot = null; pendingAnchor = null;
			typeSel.value = n.type || 'note';
			prioSel.value = n.priority || 'normal';
			assigneeSel.value = n.agent ? ('agent:' + n.agent) : String(n.assignee || 0);
			if (levelSel) { levelSel.value = (n.level === 'template' && cfg.template && cfg.template.key) ? 'template' : 'page'; }
			if (codeScopeInput) { codeScopeInput.value = n.codeScope || ''; }
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
				pendingShot = data;
					shotRemove = false; // a fresh capture supersedes any pending removal
				shotThumb.src = data;
				shotPrev.hidden = false;
			}).catch(function () {}).then(function () {
				if (busy.parentNode) { busy.parentNode.removeChild(busy); }
				panel.hidden = false;
			});
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

		function positionPins() {
			for (var k = 0; k < pins.length; k++) {
				var p = pins[k], t = p.target;
				if (!t || !document.body.contains(t)) { try { t = document.querySelector(p.sel); } catch (e) { t = null; } p.target = t; }
				if (!t) { p.el.style.display = 'none'; continue; }
				var r = t.getBoundingClientRect();
				if (r.width === 0 && r.height === 0) { p.el.style.display = 'none'; continue; }
				p.el.style.display = 'flex';
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
		if ( wprp_devmode_on() ) {
			wp_enqueue_style( 'dashicons' );
			// Vendored html2canvas (MIT) for client-side region screenshots.
			wp_enqueue_script( 'wprp-html2canvas', WPRP_PLUGIN_URL . 'assets/vendor/html2canvas.min.js', array(), '1.4.1', true );
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
		$wprp_menu_title = '<span class="dashicons dashicons-edit" style="font-size:16px;width:16px;height:16px;vertical-align:-3px;margin-right:5px;color:#D32F2F"></span>' . esc_html__( 'Red Pen', 'wp-red-pen' );
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
	echo '<div class="wrap' . ( $wprp_dark ? ' wprp-dark' : '' ) . '"><h1 style="display:flex;align-items:center;gap:.5rem"><span class="dashicons dashicons-edit" style="color:#D32F2F"></span>' . esc_html__( 'Red Pen - Notes Repository', 'wp-red-pen' ) . '</h1>';
	echo '<p>' . esc_html__( 'Every note, flag, and suggested edit dropped across the site. Shared with all editors and admins.', 'wp-red-pen' ) . '</p>';

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
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $key, 'assignee' => $who, 'audience' => $audience ), admin_url( 'tools.php' ) ) );
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
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $filter, 'assignee' => $key, 'audience' => $audience ), admin_url( 'tools.php' ) ) );
		echo ( $i++ ? ' | ' : '' ) . '<li><a href="' . $url . '"' . ( $who === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
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
			$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $filter, 'assignee' => $who, 'audience' => $key ), admin_url( 'tools.php' ) ) );
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
		echo '<td><span style="background:#D32F2F;color:#fff;border-radius:3px;padding:.05rem .35rem;font-size:.72rem;font-weight:600">' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</span></td>';
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
		$level     = ( 'template' === (string) get_post_meta( $n->ID, WPRP_META_LEVEL, true ) ) ? 'template' : 'page';
		if ( $target ) {
			$where = '<a href="' . esc_url( get_edit_post_link( $target ) ) . '">' . esc_html( '' !== $ctx_label ? $ctx_label : get_the_title( $target ) ) . '</a> <a href="' . esc_url( get_permalink( $target ) ) . '" title="' . esc_attr__( 'View', 'wp-red-pen' ) . '">&#8599;</a>';
		} else {
			$ctx_url = (string) get_post_meta( $n->ID, WPRP_META_URL, true );
			$where   = esc_html( '' !== $ctx_label ? $ctx_label : __( '(no page)', 'wp-red-pen' ) );
			if ( $ctx_url ) {
				$where .= ' <a href="' . esc_url( $ctx_url ) . '" title="' . esc_attr__( 'View', 'wp-red-pen' ) . '" target="_blank" rel="noopener">&#8599;</a>';
			}
		}
		$lvl_badge = '<div style="margin-top:.25rem"><span style="font-size:.68rem;font-weight:600;color:#3A3A3C;border:1px solid #dfe3e6;border-radius:3px;padding:0 .3rem">' . esc_html( 'template' === $level ? __( 'Template', 'wp-red-pen' ) : __( 'Page', 'wp-red-pen' ) ) . '</span></div>';
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
		echo '<td>' . esc_html( $author ? $author->display_name : '' ) . '</td>';
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
		$filter = in_array( $filter, array( 'open', 'resolved', 'all' ), true ) ? $filter : 'open';
		$statuses = 'all' === $filter ? array( WPRP_STATUS_OPEN, WPRP_STATUS_DONE ) : array( 'resolved' === $filter ? WPRP_STATUS_DONE : WPRP_STATUS_OPEN );

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
		$types      = wprp_note_types();
		$priorities = wprp_priorities();

		$filename = 'wp-red-pen-' . $filter . '-' . gmdate( 'Ymd' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' );
		fputcsv( $out, array( 'ID', 'Type', 'Priority', 'Level', 'Status', 'Note', 'Where', 'URL', 'Assignee', 'Author', 'Environment', 'When' ) );
		foreach ( $notes as $n ) {
			$type      = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
			$priority  = (string) get_post_meta( $n->ID, WPRP_META_PRIORITY, true );
			$target    = (int) get_post_meta( $n->ID, WPRP_META_TARGET, true );
			$assignee  = (int) get_post_meta( $n->ID, WPRP_META_ASSIGNEE, true );
			$au        = $assignee ? get_userdata( $assignee ) : false;
			$author    = get_userdata( $n->post_author );
			$ctx_label = (string) get_post_meta( $n->ID, WPRP_META_CTXLABEL, true );
			$level     = ( 'template' === (string) get_post_meta( $n->ID, WPRP_META_LEVEL, true ) ) ? 'Template' : 'Page';
			fputcsv(
				$out,
				array(
					$n->ID,
					isset( $types[ $type ] ) ? $types[ $type ] : $type,
					isset( $priorities[ $priority ] ) ? $priorities[ $priority ] : '',
					$level,
					WPRP_STATUS_DONE === $n->post_status ? 'Resolved' : 'Open',
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
// Admin: load dashicons on our screens (for the menu icon + meta box chrome)
// ---------------------------------------------------------------------------
add_action(
	'admin_enqueue_scripts',
	function () {
		wp_enqueue_style( 'dashicons' );
	}
);
