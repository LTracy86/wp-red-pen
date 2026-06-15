<?php
/**
 * Plugin Name:       WP Red Pen
 * Plugin URI:        https://tracydigitalmedia.com/wp-red-pen/
 * Description:       A logged-in review layer. Editors and admins flip on Dev Mode and drop notes, flags, and suggested edits on any post or page from a floating button. Notes collect on the post's edit screen and in a shared to-do repository.
 * Version:           0.7.5
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

define( 'WPRP_VERSION',     '0.7.5' );
define( 'WPRP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'WPRP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'WPRP_CPT',         'wprp_note' );      // private note CPT
define( 'WPRP_STATUS_OPEN', 'wprp_open' );      // custom post statuses
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

/** Note types -> human labels. The single source of truth for the dropdowns. */
function wprp_note_types() {
	return array(
		'note'       => __( 'Note', 'wp-red-pen' ),
		'suggestion' => __( 'Suggested edit', 'wp-red-pen' ),
		'bug'        => __( 'Bug / problem', 'wp-red-pen' ),
		'question'   => __( 'Question', 'wp-red-pen' ),
	);
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
	return $out;
}

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
	}
	return $dir;
}

/** Public URL for a stored screenshot filename. */
function wprp_shot_url( $file ) {
	if ( ! $file ) {
		return '';
	}
	$up = wp_upload_dir();
	return trailingslashit( $up['baseurl'] ) . WPRP_SHOT_DIR . '/' . rawurlencode( basename( $file ) );
}

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
		wprp_delete_shot( (string) get_post_meta( $post_id, WPRP_META_SHOT, true ) );

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

function wprp_create_note( $target_id, $body, $type = 'note', $url = '', $shot = '', $ctx = '', $priority = 'normal', $assignee = 0, $anchor = '', $context = array() ) {
	if ( ! wprp_user_can() ) {
		return new WP_Error( 'wprp_forbidden', __( 'You cannot add notes.', 'wp-red-pen' ), array( 'status' => 403 ) );
	}
	$body = trim( wp_kses_post( (string) $body ) );
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
	$body = trim( wp_kses_post( (string) $body ) );
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
		'body'   => wpautop( wp_kses_post( $reply->post_content ) ),
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
	if ( ! $note || WPRP_CPT !== $note->post_type ) {
		return new WP_Error( 'wprp_missing', __( 'Note not found.', 'wp-red-pen' ), array( 'status' => 404 ) );
	}
	$status = ( WPRP_STATUS_DONE === $status ) ? WPRP_STATUS_DONE : WPRP_STATUS_OPEN;
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

	$body = trim( wp_kses_post( (string) ( isset( $args['body'] ) ? $args['body'] : '' ) ) );
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
	$statuses = array( WPRP_STATUS_OPEN, WPRP_STATUS_DONE );
	if ( 'open' === $status ) {
		$statuses = array( WPRP_STATUS_OPEN );
	} elseif ( 'resolved' === $status ) {
		$statuses = array( WPRP_STATUS_DONE );
	}
	return get_posts(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => $statuses,
			'posts_per_page' => 200,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_key'       => WPRP_META_TARGET, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'     => (int) $target_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
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
	$statuses = array( WPRP_STATUS_OPEN, WPRP_STATUS_DONE );
	if ( 'open' === $status ) {
		$statuses = array( WPRP_STATUS_OPEN );
	} elseif ( 'resolved' === $status ) {
		$statuses = array( WPRP_STATUS_DONE );
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
				array(
					'key'     => WPRP_META_CTXKEY,
					'value'   => $keys,
					'compare' => 'IN',
				),
			),
		)
	);
}

/** Count of open notes across the whole site (for the admin-bar badge). */
function wprp_open_count() {
	$q = new WP_Query(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => WPRP_STATUS_OPEN,
			'post_parent'    => 0, // count notes, not replies
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		)
	);
	return (int) $q->found_posts;
}

/** Shape a note post into the plain array the JS + REST consume. */
function wprp_note_to_array( $note ) {
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
		'body'       => wpautop( wp_kses_post( $note->post_content ) ),
		'raw'        => $note->post_content, // unformatted, for the edit textarea
		'type'       => $type,
		'typeLabel'  => isset( $types[ $type ] ) ? $types[ $type ] : $types['note'],
		'status'     => $note->post_status,
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
		'replies'     => array_map( 'wprp_reply_to_array', wprp_get_replies( $note->ID ) ),
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
		update_option( WPRP_DBVER_OPT, '0.5.0' );
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
							$notes  = ( '' !== $keys )
								? wprp_get_notes_for_context( explode( ',', $keys ), $status ? $status : 'any' )
								: wprp_get_notes_for( $target, $status ? $status : 'any' );
						return rest_ensure_response( array_map( 'wprp_note_to_array', $notes ) );
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
							)
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
					$resolved = (bool) $req->get_param( 'resolved' );
					$res      = wprp_set_status( (int) $req['id'], $resolved ? WPRP_STATUS_DONE : WPRP_STATUS_OPEN );
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
		$user_opts = '<option value="0">' . esc_html__( 'Unassigned', 'wp-red-pen' ) . '</option>';
		foreach ( wprp_assignable_users() as $uid => $uname ) {
			$user_opts .= '<option value="' . (int) $uid . '">' . esc_html( $uname ) . '</option>';
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
			)
		);
		?>
		<div id="wprp-root" data-cfg='<?php echo esc_attr( $cfg ); ?>'>
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
	?>
	<style id="wprp-css">
		#wprp-root{--wprp-red:#D32F2F;--wprp-red-dark:#B71C1C;--wprp-accent:#FF5252;--wprp-ink:#1E2225;--wprp-gray:#3A3A3C;position:fixed;right:20px;bottom:20px;z-index:99990;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
			/* The HTML [hidden] attribute is the weakest possible style, so an id/class rule that
			   sets display (e.g. #wprp-panel{display:flex}) silently defeats it and the toggle does
			   nothing. This rule (specificity 1,1,0) outranks those and keeps [hidden] authoritative. */
			#wprp-root [hidden]{display:none}
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
			.wprp-pin{position:fixed;transform:translate(-50%,-50%);min-width:22px;height:22px;padding:0 5px;border-radius:11px;background:var(--wprp-accent);color:#fff;border:2px solid #fff;box-shadow:0 2px 6px rgba(30,34,37,.4);font-size:11px;font-weight:700;line-height:1;display:flex;align-items:center;justify-content:center;cursor:pointer;pointer-events:auto;box-sizing:border-box}
			.wprp-pin:hover{background:var(--wprp-red)}
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
			// The current view's contexts (key/label/target) the server computed for this page.
			function ctxForLevel(lvl) { return (lvl === 'template' && cfg.template && cfg.template.key) ? cfg.template : cfg.page; }
		var countEl = document.getElementById('wprp-fab-count');
		var tabOpenBtn = document.getElementById('wprp-tab-open');
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
				var RESOLVED_MSG = '<?php echo esc_js( __( 'Note resolved.', 'wp-red-pen' ) ); ?>';
				var REOPENED_MSG = '<?php echo esc_js( __( 'Note reopened.', 'wp-red-pen' ) ); ?>';
				var UNDO_LABEL = '<?php echo esc_js( __( 'Undo', 'wp-red-pen' ) ); ?>';
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
			return '<div class="wprp-note ' + (n.resolved ? 'is-resolved' : '') + '" data-id="' + n.id + '">' +
				'<div class="wprp-meta"><span class="wprp-tag">' + esc(n.typeLabel) + '</span>' +
				'<span class="wprp-prio wprp-prio-' + esc(n.priority || 'normal') + '">' + esc(n.priorityLabel) + '</span>' +
				(n.level === 'template' ? '<span class="wprp-level" title="' + esc(n.ctxLabel || '') + '"><?php echo esc_js( __( 'Template', 'wp-red-pen' ) ); ?></span>' : '') +
				'<span>' + esc(n.author) + '</span><span>' + esc(n.date) + '</span>' +
				(n.assigneeName ? '<span class="wprp-assignee">&rarr; ' + esc(n.assigneeName) + '</span>' : '') +
				'<span class="wprp-actions">' +
				(n.anchor ? '<button type="button" class="wprp-locate" title="<?php echo esc_js( __( 'Highlight the pinned element', 'wp-red-pen' ) ); ?>" aria-label="<?php echo esc_js( __( 'Highlight the pinned element', 'wp-red-pen' ) ); ?>"><span class="dashicons dashicons-search"></span></button>' : '') +
				'<button type="button" class="wprp-resolve">' + (n.resolved ? '<?php echo esc_js( __( 'Reopen', 'wp-red-pen' ) ); ?>' : '<?php echo esc_js( __( 'Resolve', 'wp-red-pen' ) ); ?>') + '</button>' +
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
			var openCount = 0, resolvedCount = 0;
			for (var _i = 0; _i < lastNotes.length; _i++) { if (lastNotes[_i].resolved) { resolvedCount++; } else { openCount++; } }
			var notes = [];
			for (var _j = 0; _j < lastNotes.length; _j++) { if ((currentTab === 'resolved') === !!lastNotes[_j].resolved) { notes.push(lastNotes[_j]); } }
			if (!notes.length) {
				list.innerHTML = '<p class="wprp-muted">' + (currentTab === 'resolved' ? EMPTY_RESOLVED : EMPTY_OPEN) + '</p>';
			} else {
				list.innerHTML = notes.map(noteHtml).join('');
			}
			if (tabOpenBtn && tabResolvedBtn) {
				tabOpenBtn.textContent = TAB_OPEN + ' (' + openCount + ')';
				tabResolvedBtn.textContent = TAB_RESOLVED + ' (' + resolvedCount + ')';
				tabOpenBtn.classList.toggle('is-active', currentTab === 'open');
				tabResolvedBtn.classList.toggle('is-active', currentTab === 'resolved');
				tabOpenBtn.setAttribute('aria-selected', currentTab === 'open' ? 'true' : 'false');
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

		function load() {
			var keys = [cfg.page && cfg.page.key, cfg.template && cfg.template.key].filter(Boolean).join(',');
			list.setAttribute('aria-busy', 'true');
				return api('/notes?keys=' + encodeURIComponent(keys)).then(function (notes) {
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
		function noteBelongsToTab(n) { return (currentTab === 'resolved') === !!n.resolved; }
		function clearPlaceholder() { if (!list.querySelector('.wprp-note')) { list.innerHTML = ''; } }
		function showEmptyIfNeeded() { if (!list.querySelector('.wprp-note')) { list.innerHTML = '<p class="wprp-muted">' + (currentTab === 'resolved' ? EMPTY_RESOLVED : EMPTY_OPEN) + '</p>'; } }
		function refreshCounts() {
			var o = 0, r = 0;
			for (var i = 0; i < lastNotes.length; i++) { if (lastNotes[i].resolved) { r++; } else { o++; } }
			if (tabOpenBtn) { tabOpenBtn.textContent = TAB_OPEN + ' (' + o + ')'; }
			if (tabResolvedBtn) { tabResolvedBtn.textContent = TAB_RESOLVED + ' (' + r + ')'; }
			if (o > 0) { countEl.textContent = o; countEl.hidden = false; } else { countEl.hidden = true; }
			updateFabLabel(o);
		}
		// Replace a single note's node in place (reply / edit) - leaves every other note untouched.
		function patchNoteInPlace(data) {
			var idx = lastNotesIndex(data.id);
			if (idx >= 0) { lastNotes[idx] = data; } else { lastNotes.unshift(data); }
			var node = noteNodeById(data.id);
			if (node) { node.parentNode.replaceChild(htmlToNode(noteHtml(data)), node); }
			else if (noteBelongsToTab(data)) { clearPlaceholder(); list.insertBefore(htmlToNode(noteHtml(data)), list.firstChild); }
			refreshCounts(); buildPins(lastNotes);
		}
		// Flip a note's status locally (resolve / reopen / undo) and move it on/off the active tab.
		function applyStatusLocally(nid, resolvedBool) {
			var idx = lastNotesIndex(nid);
			if (idx >= 0) { lastNotes[idx].resolved = resolvedBool; lastNotes[idx].status = resolvedBool ? 'wprp_resolved' : 'wprp_open'; }
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
		function setTab(tab) { currentTab = (tab === 'resolved') ? 'resolved' : 'open'; render(); }
		if (tabOpenBtn) { tabOpenBtn.addEventListener('click', function () { setTab('open'); }); }
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
					var payload = { body: text, type: typeSel.value, priority: prioSel.value, assignee: assigneeSel.value, level: elvl, ctx_key: ectx.key, ctx_label: ectx.label, target: (elvl === 'page' ? (cfg.page.target || 0) : 0) };
					if (pendingShot) { payload.shot = pendingShot; } else if (shotRemove) { payload.shot_remove = 1; }
					if (pendingAnchor) { payload.anchor = JSON.stringify(pendingAnchor); } else if (anchorRemove) { payload.anchor_remove = 1; }
				api('/notes/' + editingId, { method: 'POST', body: JSON.stringify(payload) })
					.then(function (data) { exitEdit(); submit.disabled = false; patchNoteInPlace(data); })
					.catch(function () { submit.disabled = false; toast(SAVE_FAILED); });
				return;
			}
			var lvl = levelSel ? levelSel.value : 'page';
			var lctx = ctxForLevel(lvl);
			api('/notes', { method: 'POST', body: JSON.stringify({ body: text, type: typeSel.value, url: cfg.url, shot: pendingShot || '', ctx: buildCtx(), priority: prioSel.value, assignee: assigneeSel.value, anchor: pendingAnchor ? JSON.stringify(pendingAnchor) : '', level: lvl, ctx_key: lctx.key, ctx_label: lctx.label, target: (lvl === 'page' ? (cfg.page.target || 0) : 0) }) })
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
			assigneeSel.value = String(n.assignee || 0);
			if (levelSel) { levelSel.value = (n.level === 'template' && cfg.template && cfg.template.key) ? 'template' : 'page'; }
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
				if (n.resolved) { return; } // resolved notes drop off the active list, so their pin marker goes too
				if (!n.anchor) { return; }
				var a; try { a = JSON.parse(n.anchor); } catch (e) { return; }
				if (!a || !a.sel) { return; }
				i++;
				var marker = document.createElement('button');
				marker.type = 'button';
				marker.className = 'wprp-pin' + (n.resolved ? ' is-resolved' : '');
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
				var p = pins[k], t = null;
				try { t = document.querySelector(p.sel); } catch (e) { t = null; }
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

		// Prime the badge without opening the panel.
		load();
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
			echo '<span style="background:#D32F2F;color:#fff;border-radius:3px;padding:0 .3rem;font-size:.7rem;font-weight:600">' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</span> ';
			$priority = (string) get_post_meta( $n->ID, WPRP_META_PRIORITY, true );
			$prios    = wprp_priorities();
			if ( isset( $prios[ $priority ] ) && 'normal' !== $priority ) {
				echo '<span style="border:1px solid #dfe3e6;border-radius:3px;padding:0 .3rem;font-size:.68rem;font-weight:600;color:#3A3A3C">' . esc_html( $prios[ $priority ] ) . '</span> ';
			}
			$assignee = (int) get_post_meta( $n->ID, WPRP_META_ASSIGNEE, true );
			$au       = $assignee ? get_userdata( $assignee ) : false;
			echo '<small>' . esc_html( $author ? $author->display_name : '' ) . ' &middot; ' . esc_html( get_the_time( get_option( 'date_format' ), $n ) ) . ( $au ? ' &middot; &rarr; ' . esc_html( $au->display_name ) : '' ) . '</small>';
			echo '<div style="font-size:.85rem;margin-top:.2rem">' . wp_kses_post( wpautop( $n->post_content ) ) . '</div>';
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
				echo '<div>' . wp_kses_post( wpautop( $r->post_content ) ) . '</div></div>';
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
	$filter   = in_array( $filter, array( 'open', 'resolved', 'all' ), true ) ? $filter : 'open';
	$statuses = 'all' === $filter ? array( WPRP_STATUS_OPEN, WPRP_STATUS_DONE ) : array( 'resolved' === $filter ? WPRP_STATUS_DONE : WPRP_STATUS_OPEN );

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
	$types = wprp_note_types();
	$prios = wprp_priorities();

	echo '<div class="wrap"><h1 style="display:flex;align-items:center;gap:.5rem"><span class="dashicons dashicons-edit" style="color:#D32F2F"></span>' . esc_html__( 'Red Pen - Notes Repository', 'wp-red-pen' ) . '</h1>';
	echo '<p>' . esc_html__( 'Every note, flag, and suggested edit dropped across the site. Shared with all editors and admins.', 'wp-red-pen' ) . '</p>';

	// Visibility settings: which front-end views show the Red Pen widget (global).
	$show_on  = wprp_show_on();
	$save_url = wp_nonce_url( admin_url( 'admin-post.php?action=wprp_save_visibility' ), 'wprp_save_visibility' );
	echo '<details style="margin:.5rem 0 1rem;border:1px solid #dcdcde;border-radius:5px;padding:.4rem .8rem;background:#fff;max-width:640px">';
	echo '<summary style="cursor:pointer;font-weight:600"><span class="dashicons dashicons-visibility" style="vertical-align:text-top"></span> ' . esc_html__( 'Where Red Pen appears', 'wp-red-pen' ) . '</summary>';
	echo '<form method="post" action="' . esc_url( $save_url ) . '" style="margin-top:.6rem">';
	echo '<p style="margin:.2rem 0 .6rem;color:#646970">' . esc_html__( 'Choose which front-end views show the floating button for users in Dev Mode.', 'wp-red-pen' ) . '</p>';
	foreach ( wprp_view_scopes() as $key => $label ) {
		echo '<label style="display:block;margin:.2rem 0"><input type="checkbox" name="scopes[]" value="' . esc_attr( $key ) . '"' . checked( in_array( $key, $show_on, true ), true, false ) . '> ' . wp_kses_post( $label ) . '</label>';
	}
	echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Save', 'wp-red-pen' ) . '</button></p>';
	echo '</form></details>';

	// Status filter tabs.
	$tabs = array(
		'open'     => __( 'Open', 'wp-red-pen' ),
		'resolved' => __( 'Resolved', 'wp-red-pen' ),
		'all'      => __( 'All', 'wp-red-pen' ),
	);
	echo '<ul class="subsubsub">';
	$i = 0;
	foreach ( $tabs as $key => $label ) {
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $key, 'assignee' => $who ), admin_url( 'tools.php' ) ) );
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
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $filter, 'assignee' => $key ), admin_url( 'tools.php' ) ) );
		echo ( $i++ ? ' | ' : '' ) . '<li><a href="' . $url . '"' . ( $who === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul><div style="clear:both"></div>';

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

	echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
	echo '<th>' . esc_html__( 'Type', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Priority', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Note', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Where', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Assigned', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'By', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'When', 'wp-red-pen' ) . '</th><th></th></tr></thead><tbody>';

	$reply_counts = wprp_reply_counts( wp_list_pluck( $notes, 'ID' ) ); // one query, not one-per-row

	foreach ( $notes as $n ) {
		$type     = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
		$target   = (int) get_post_meta( $n->ID, WPRP_META_TARGET, true );
		$resolved = WPRP_STATUS_DONE === $n->post_status;
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

		echo '<tr' . ( $resolved ? ' style="opacity:.55"' : '' ) . '>';
		echo '<td><span style="background:#D32F2F;color:#fff;border-radius:3px;padding:.05rem .35rem;font-size:.72rem;font-weight:600">' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</span></td>';
		echo '<td><span style="background:' . esc_attr( $prio_bg ) . ';color:#fff;border-radius:3px;padding:.05rem .35rem;font-size:.72rem;font-weight:600">' . esc_html( isset( $prios[ $priority ] ) ? $prios[ $priority ] : $prios['normal'] ) . '</span></td>';
		$shot      = wprp_shot_url( (string) get_post_meta( $n->ID, WPRP_META_SHOT, true ) );
		$shot_html = $shot ? '<a href="' . esc_url( $shot ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $shot ) . '" alt="" style="max-width:180px;height:auto;margin-top:.35rem;border:1px solid #e0e0e0;border-radius:4px;display:block"></a>' : '';
		$ctx       = (string) get_post_meta( $n->ID, WPRP_META_CTX, true );
		$ctx_html  = $ctx ? '<div style="font-size:.7rem;color:#3A3A3C;font-family:monospace;margin-top:.35rem">' . esc_html( $ctx ) . '</div>' : '';
		$reply_n   = isset( $reply_counts[ $n->ID ] ) ? $reply_counts[ $n->ID ] : 0;
		/* translators: %d: number of replies */
		$reply_html = $reply_n ? '<div style="font-size:.72rem;color:#3A3A3C;margin-top:.35rem">' . esc_html( sprintf( _n( '%d reply', '%d replies', $reply_n, 'wp-red-pen' ), $reply_n ) ) . '</div>' : '';
		$anchor_html = get_post_meta( $n->ID, WPRP_META_ANCHOR, true ) ? '<div style="font-size:.72rem;color:#D32F2F;margin-top:.35rem"><span class="dashicons dashicons-location" style="font-size:14px;width:14px;height:14px;vertical-align:text-top"></span> ' . esc_html__( 'Pinned to an element', 'wp-red-pen' ) . '</div>' : '';
		echo '<td>' . wp_kses_post( wpautop( $n->post_content ) ) . $shot_html . $ctx_html . $reply_html . $anchor_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- *_html built with esc_* above
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
		echo '<td>' . ( $au ? esc_html( $au->display_name ) : '<span style="color:#9aa1a7">&mdash;</span>' ) . '</td>';
		echo '<td>' . esc_html( $author ? $author->display_name : '' ) . '</td>';
		echo '<td>' . esc_html( get_the_time( get_option( 'date_format' ), $n ) ) . '</td>';
		echo '<td><a class="button button-small" href="' . esc_url( $resolve_url ) . '">' . esc_html( $resolved ? __( 'Reopen', 'wp-red-pen' ) : __( 'Resolve', 'wp-red-pen' ) ) . '</a> ';
		echo '<a class="button button-small button-link-delete" style="color:#b32d2e" href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this note permanently, including its replies and screenshot? This cannot be undone.', 'wp-red-pen' ) ) . '\');">' . esc_html__( 'Delete', 'wp-red-pen' ) . '</a></td>';
		echo '</tr>';
	}
	echo '</tbody></table></div>';
}

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
		$to = isset( $_GET['to'] ) && 'done' === $_GET['to'] ? WPRP_STATUS_DONE : WPRP_STATUS_OPEN;
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
		update_option( WPRP_SHOW_OPT, $scopes ); // empty array = show nowhere (a valid choice)
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
	$value = (string) $value;
	if ( '' !== $value && false !== strpos( "=+-@\t\r", $value[0] ) ) {
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
					$target ? get_permalink( $target ) : (string) get_post_meta( $n->ID, WPRP_META_URL, true ),
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
