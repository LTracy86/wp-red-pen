<?php
/**
 * Plugin Name:       WP Red Pen
 * Plugin URI:        https://tracydigitalmedia.com/wp-red-pen/
 * Description:       A logged-in review layer. Editors and admins flip on Dev Mode and drop notes, flags, and suggested edits on any post or page from a floating button. Notes collect on the post's edit screen and in a shared to-do repository.
 * Version:           0.2.0
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

define( 'WPRP_VERSION',     '0.2.0' );
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
define( 'WPRP_SHOT_DIR',    'wp-red-pen' );     // uploads subfolder for screenshots
define( 'WPRP_REST_NS',     'wprp/v1' );

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
	// Allowed in CSS selector paths we generate: tag/class/id chars + structural punctuation.
	if ( '' === $sel || strlen( $sel ) > 600 || preg_match( '/[<>"\']/', $sel ) ) {
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
function wprp_create_note( $target_id, $body, $type = 'note', $url = '', $shot = '', $ctx = '', $priority = 'normal', $assignee = 0, $anchor = '' ) {
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
	return array(
		'id'         => (int) $note->ID,
		'body'       => wpautop( wp_kses_post( $note->post_content ) ),
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
		'replies'     => array_map( 'wprp_reply_to_array', wprp_get_replies( $note->ID ) ),
	);
}

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
						$notes  = wprp_get_notes_for( $target, $status ? $status : 'any' );
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
							(string) $req->get_param( 'anchor' )
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
				'href'   => esc_url( admin_url( 'admin.php?page=wp-red-pen' ) ),
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
		if ( ! wprp_devmode_on() || ! is_singular() ) {
			return;
		}
		$target = (int) get_queried_object_id();
		if ( ! $target ) {
			return;
		}
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
		$cfg = wp_json_encode(
			array(
				'root'   => esc_url_raw( rest_url( WPRP_REST_NS ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'target' => $target,
				'url'    => esc_url_raw( home_url( add_query_arg( array() ) ) ),
				'title'  => get_the_title( $target ),
			)
		);
		?>
		<div id="wprp-root" data-cfg='<?php echo $cfg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode is safe in a single-quoted attr ?>'>
			<button type="button" id="wprp-fab" aria-expanded="false" title="<?php esc_attr_e( 'Red Pen notes', 'wp-red-pen' ); ?>">
				<span class="dashicons dashicons-edit"></span>
				<span id="wprp-fab-count" class="wprp-count" hidden></span>
			</button>
			<section id="wprp-panel" hidden aria-label="<?php esc_attr_e( 'Red Pen notes', 'wp-red-pen' ); ?>">
				<header class="wprp-head">
					<strong><?php esc_html_e( 'Red Pen', 'wp-red-pen' ); ?></strong>
					<span class="wprp-page"><?php echo esc_html( get_the_title( $target ) ); ?></span>
					<button type="button" class="wprp-x" id="wprp-close" aria-label="<?php esc_attr_e( 'Close', 'wp-red-pen' ); ?>">&times;</button>
				</header>
				<div id="wprp-list" class="wprp-list"><p class="wprp-muted"><?php esc_html_e( 'Loading notes...', 'wp-red-pen' ); ?></p></div>
				<form id="wprp-form" class="wprp-form">
					<div class="wprp-formrow">
						<select id="wprp-type" aria-label="<?php esc_attr_e( 'Note type', 'wp-red-pen' ); ?>"><?php echo $opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
						<select id="wprp-priority" aria-label="<?php esc_attr_e( 'Priority', 'wp-red-pen' ); ?>"><?php echo $prio_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
					</div>
					<select id="wprp-assignee" aria-label="<?php esc_attr_e( 'Assign to', 'wp-red-pen' ); ?>"><?php echo $user_opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
					<textarea id="wprp-body" rows="3" placeholder="<?php esc_attr_e( 'Add a note, flag, or suggested edit...', 'wp-red-pen' ); ?>" required></textarea>
					<div class="wprp-shotrow">
						<button type="button" id="wprp-shot-btn" class="wprp-shotbtn"><span class="dashicons dashicons-camera"></span> <?php esc_html_e( 'Screenshot', 'wp-red-pen' ); ?></button>
						<div id="wprp-shot-preview" class="wprp-shot-preview" hidden>
							<img id="wprp-shot-thumb" alt="<?php esc_attr_e( 'Screenshot preview', 'wp-red-pen' ); ?>">
							<button type="button" id="wprp-shot-clear" class="wprp-shot-clear" aria-label="<?php esc_attr_e( 'Remove screenshot', 'wp-red-pen' ); ?>">&times;</button>
						</div>
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
		#wprp-panel{position:absolute;right:0;bottom:64px;width:340px;max-width:calc(100vw - 40px);max-height:70vh;display:flex;flex-direction:column;background:#fff;color:var(--wprp-ink);border:1px solid #d6dade;border-radius:10px;box-shadow:0 10px 34px rgba(30,34,37,.28);overflow:hidden}
		.wprp-head{display:flex;align-items:center;gap:.5rem;padding:.6rem .75rem;background:var(--wprp-red);color:#fff}
		.wprp-head .wprp-page{font-size:.78rem;opacity:.85;margin-left:auto;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.wprp-x{background:none;border:none;color:#fff;font-size:20px;line-height:1;cursor:pointer;padding:0 0 0 .25rem}
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
		.wprp-resolve{background:none;border:1px solid #cfd4d8;border-radius:4px;color:var(--wprp-gray);font-size:.72rem;cursor:pointer;padding:.1rem .4rem;margin-left:auto}
		.wprp-resolve:hover{border-color:var(--wprp-red);color:var(--wprp-red)}
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
		#wprp-capture .wprp-hint{position:fixed;top:14px;left:50%;transform:translateX(-50%);background:var(--wprp-ink);color:#fff;font-family:-apple-system,sans-serif;font-size:.82rem;padding:.4rem .8rem;border-radius:6px;pointer-events:none}
		#wprp-busy{position:fixed;inset:0;z-index:99999;display:flex;align-items:center;justify-content:center;background:rgba(30,34,37,.18);font-family:-apple-system,sans-serif}
		#wprp-busy span{background:var(--wprp-ink);color:#fff;font-size:.85rem;padding:.5rem 1rem;border-radius:6px}
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
		var countEl = document.getElementById('wprp-fab-count');
		var shotBtn = document.getElementById('wprp-shot-btn');
		var shotPrev = document.getElementById('wprp-shot-preview');
		var shotThumb = document.getElementById('wprp-shot-thumb');
		var shotClear = document.getElementById('wprp-shot-clear');
		var pendingShot = null;

		function api(path, opts) {
			opts = opts || {};
			opts.headers = Object.assign({ 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }, opts.headers || {});
			return fetch(cfg.root + path, opts).then(function (r) {
				if (!r.ok) { throw new Error('HTTP ' + r.status); }
				return r.json();
			});
		}
		function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

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
				'<span>' + esc(n.author) + '</span><span>' + esc(n.date) + '</span>' +
				(n.assigneeName ? '<span class="wprp-assignee">&rarr; ' + esc(n.assigneeName) + '</span>' : '') +
				'<button type="button" class="wprp-resolve">' + (n.resolved ? '<?php echo esc_js( __( 'Reopen', 'wp-red-pen' ) ); ?>' : '<?php echo esc_js( __( 'Resolve', 'wp-red-pen' ) ); ?>') + '</button></div>' +
				'<div class="wprp-body">' + n.body + '</div>' +
				(n.shot ? '<a class="wprp-shot" href="' + esc(n.shot) + '" target="_blank" rel="noopener"><img src="' + esc(n.shot) + '" alt="screenshot"></a>' : '') +
				(n.ctx ? '<div class="wprp-ctx">' + esc(n.ctx) + '</div>' : '') +
				'<div class="wprp-replies">' +
					((n.replies && n.replies.length) ? n.replies.map(replyHtml).join('') : '') +
					'<form class="wprp-replyform" data-id="' + n.id + '">' +
						'<textarea class="wprp-replytext" rows="1" placeholder="<?php echo esc_js( __( 'Reply...', 'wp-red-pen' ) ); ?>" required></textarea>' +
						'<button type="submit" class="wprp-replysend"><?php echo esc_js( __( 'Reply', 'wp-red-pen' ) ); ?></button>' +
					'</form>' +
				'</div>' +
				'</div>';
			}

			function replyHtml(r) {
				return '<div class="wprp-reply"><div class="wprp-reply-meta">' + esc(r.author) + ' &middot; ' + esc(r.date) + '</div>' +
					'<div class="wprp-reply-body">' + r.body + '</div></div>';
		}

		function render(notes) {
			var open = 0;
			if (!notes.length) {
				list.innerHTML = '<p class="wprp-muted"><?php echo esc_js( __( 'No notes on this page yet.', 'wp-red-pen' ) ); ?></p>';
			} else {
				list.innerHTML = notes.map(noteHtml).join('');
			}
			notes.forEach(function (n) { if (!n.resolved) { open++; } });
			if (open > 0) { countEl.textContent = open; countEl.hidden = false; } else { countEl.hidden = true; }
		}

		function load() {
			api('/notes?target=' + encodeURIComponent(cfg.target)).then(render).catch(function () {
				list.innerHTML = '<p class="wprp-muted"><?php echo esc_js( __( 'Could not load notes.', 'wp-red-pen' ) ); ?></p>';
			});
		}

		fab.addEventListener('click', function () {
			var show = panel.hidden;
			panel.hidden = !show;
			fab.setAttribute('aria-expanded', show ? 'true' : 'false');
			if (show) { load(); }
		});
		document.getElementById('wprp-close').addEventListener('click', function () {
			panel.hidden = true; fab.setAttribute('aria-expanded', 'false');
		});

		list.addEventListener('click', function (e) {
			var btn = e.target.closest('.wprp-resolve');
			if (!btn) { return; }
			var wrap = btn.closest('.wprp-note');
			var id = wrap.getAttribute('data-id');
			var resolved = !wrap.classList.contains('is-resolved');
			btn.disabled = true;
			api('/notes/' + id + '/status', { method: 'POST', body: JSON.stringify({ resolved: resolved }) })
				.then(load).catch(function () { btn.disabled = false; });
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
				.then(load).catch(function () { send.disabled = false; });
		});

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var text = body.value.trim();
			if (!text) { return; }
			var submit = form.querySelector('.wprp-submit');
			submit.disabled = true;
			api('/notes', { method: 'POST', body: JSON.stringify({ target: cfg.target, body: text, type: typeSel.value, url: cfg.url, shot: pendingShot || '', ctx: buildCtx(), priority: prioSel.value, assignee: assigneeSel.value }) })
				.then(function () { body.value = ''; clearShot(); submit.disabled = false; load(); })
				.catch(function () { submit.disabled = false; });
		});

		// ---- screenshot: drag a box, html2canvas the region, store as WebP ----
		function clearShot() {
			pendingShot = null;
			shotPrev.hidden = true;
			shotThumb.removeAttribute('src');
		}
		shotClear.addEventListener('click', clearShot);

		shotBtn.addEventListener('click', function () {
			if (typeof html2canvas === 'undefined') { return; }
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
				window.removeEventListener('mousemove', move);
				window.removeEventListener('mouseup', up);
				window.removeEventListener('keydown', key);
				if (overlay.parentNode) { overlay.parentNode.removeChild(overlay); }
			}
			overlay.addEventListener('mousedown', down);
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
				shotThumb.src = data;
				shotPrev.hidden = false;
			}).catch(function () {}).then(function () {
				if (busy.parentNode) { busy.parentNode.removeChild(busy); }
				panel.hidden = false;
			});
		}

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
	echo '<p style="margin:.4rem 0 0"><a href="' . esc_url( admin_url( 'admin.php?page=wp-red-pen' ) ) . '">' . esc_html__( 'Open the notes repository &rarr;', 'wp-red-pen' ) . '</a></p>';
}

// ---------------------------------------------------------------------------
// Admin repository page: every note across the site (the shared to-do list)
// ---------------------------------------------------------------------------
add_action(
	'admin_menu',
	function () {
		add_menu_page(
			__( 'Red Pen', 'wp-red-pen' ),
			__( 'Red Pen', 'wp-red-pen' ),
			WPRP_CAP,
			'wp-red-pen',
			'wprp_render_repo_page',
			'dashicons-edit',
			58
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

	echo '<div class="wrap"><h1 style="display:flex;align-items:center;gap:.5rem"><span class="dashicons dashicons-edit"></span>' . esc_html__( 'Red Pen - Notes Repository', 'wp-red-pen' ) . '</h1>';
	echo '<p>' . esc_html__( 'Every note, flag, and suggested edit dropped across the site. Shared with all editors and admins.', 'wp-red-pen' ) . '</p>';

	// Status filter tabs.
	$tabs = array(
		'open'     => __( 'Open', 'wp-red-pen' ),
		'resolved' => __( 'Resolved', 'wp-red-pen' ),
		'all'      => __( 'All', 'wp-red-pen' ),
	);
	echo '<ul class="subsubsub">';
	$i = 0;
	foreach ( $tabs as $key => $label ) {
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $key, 'assignee' => $who ), admin_url( 'admin.php' ) ) );
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
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $filter, 'assignee' => $key ), admin_url( 'admin.php' ) ) );
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
	echo '<th>' . esc_html__( 'Type', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Priority', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Note', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'On page', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Assigned', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'By', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'When', 'wp-red-pen' ) . '</th><th></th></tr></thead><tbody>';

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
		$reply_n   = count( wprp_get_replies( $n->ID ) );
		/* translators: %d: number of replies */
		$reply_html = $reply_n ? '<div style="font-size:.72rem;color:#3A3A3C;margin-top:.35rem">' . esc_html( sprintf( _n( '%d reply', '%d replies', $reply_n, 'wp-red-pen' ), $reply_n ) ) . '</div>' : '';
		echo '<td>' . wp_kses_post( wpautop( $n->post_content ) ) . $shot_html . $ctx_html . $reply_html . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- *_html built with esc_* above
		echo '<td>' . ( $target ? '<a href="' . esc_url( get_edit_post_link( $target ) ) . '">' . esc_html( get_the_title( $target ) ) . '</a> <a href="' . esc_url( get_permalink( $target ) ) . '" title="' . esc_attr__( 'View', 'wp-red-pen' ) . '">&#8599;</a>' : '&mdash;' ) . '</td>';
		echo '<td>' . ( $au ? esc_html( $au->display_name ) : '<span style="color:#9aa1a7">&mdash;</span>' ) . '</td>';
		echo '<td>' . esc_html( $author ? $author->display_name : '' ) . '</td>';
		echo '<td>' . esc_html( get_the_time( get_option( 'date_format' ), $n ) ) . '</td>';
		echo '<td><a class="button button-small" href="' . esc_url( $resolve_url ) . '">' . esc_html( $resolved ? __( 'Reopen', 'wp-red-pen' ) : __( 'Resolve', 'wp-red-pen' ) ) . '</a></td>';
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
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=wp-red-pen' ) );
		exit;
	}
);

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
		fputcsv( $out, array( 'ID', 'Type', 'Priority', 'Status', 'Note', 'On page', 'URL', 'Assignee', 'Author', 'Context', 'When' ) );
		foreach ( $notes as $n ) {
			$type     = (string) get_post_meta( $n->ID, WPRP_META_TYPE, true );
			$priority = (string) get_post_meta( $n->ID, WPRP_META_PRIORITY, true );
			$target   = (int) get_post_meta( $n->ID, WPRP_META_TARGET, true );
			$assignee = (int) get_post_meta( $n->ID, WPRP_META_ASSIGNEE, true );
			$au       = $assignee ? get_userdata( $assignee ) : false;
			$author   = get_userdata( $n->post_author );
			fputcsv(
				$out,
				array(
					$n->ID,
					isset( $types[ $type ] ) ? $types[ $type ] : $type,
					isset( $priorities[ $priority ] ) ? $priorities[ $priority ] : '',
					WPRP_STATUS_DONE === $n->post_status ? 'Resolved' : 'Open',
					wp_strip_all_tags( $n->post_content ),
					$target ? get_the_title( $target ) : '',
					$target ? get_permalink( $target ) : (string) get_post_meta( $n->ID, WPRP_META_URL, true ),
					$au ? $au->display_name : '',
					$author ? $author->display_name : '',
					(string) get_post_meta( $n->ID, WPRP_META_CTX, true ),
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
