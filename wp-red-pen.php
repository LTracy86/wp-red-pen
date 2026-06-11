<?php
/**
 * Plugin Name:       WP Red Pen
 * Plugin URI:        https://tracydigitalmedia.com/wp-red-pen/
 * Description:       A logged-in review layer. Editors and admins flip on Dev Mode and drop notes, flags, and suggested edits on any post or page from a floating button. Notes collect on the post's edit screen and in a shared to-do repository.
 * Version:           0.1.0
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
 * Note: "Red Pen" is the editorial metaphor only. The UI follows the locked TDM
 * palette (deep blue / cyan, no warm/red).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPRP_VERSION',     '0.1.0' );
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

/** True when the current user is allowed to use Red Pen at all. */
function wprp_user_can() {
	return is_user_logged_in() && current_user_can( WPRP_CAP );
}

/** True when the current user has Dev Mode switched on. */
function wprp_devmode_on() {
	return wprp_user_can() && (bool) get_user_meta( get_current_user_id(), WPRP_USERMETA, true );
}

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
 */
function wprp_create_note( $target_id, $body, $type = 'note', $url = '' ) {
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
	return $id;
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
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => false,
		)
	);
	return (int) $q->found_posts;
}

/** Shape a note post into the plain array the JS + REST consume. */
function wprp_note_to_array( $note ) {
	$type  = (string) get_post_meta( $note->ID, WPRP_META_TYPE, true );
	$types = wprp_note_types();
	$author = get_userdata( $note->post_author );
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
							(string) $req->get_param( 'url' )
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
					<select id="wprp-type" aria-label="<?php esc_attr_e( 'Note type', 'wp-red-pen' ); ?>"><?php echo $opts; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_* above ?></select>
					<textarea id="wprp-body" rows="3" placeholder="<?php esc_attr_e( 'Add a note, flag, or suggested edit...', 'wp-red-pen' ); ?>" required></textarea>
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
		#wprp-root{--wprp-blue:#14569B;--wprp-cyan:#00AEEE;--wprp-ink:#1E2225;--wprp-gray:#3A3A3C;position:fixed;right:20px;bottom:20px;z-index:99990;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}
		#wprp-fab{width:52px;height:52px;border-radius:50%;border:none;background:var(--wprp-blue);color:#fff;cursor:pointer;box-shadow:0 4px 14px rgba(20,86,155,.4);display:flex;align-items:center;justify-content:center;position:relative;transition:transform .12s,background .12s}
		#wprp-fab:hover{transform:translateY(-2px);background:#0f4279}
		#wprp-fab .dashicons{width:26px;height:26px;font-size:26px}
		.wprp-count{position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 4px;border-radius:9px;background:var(--wprp-cyan);color:var(--wprp-ink);font-size:11px;font-weight:700;line-height:18px;text-align:center}
		#wprp-panel{position:absolute;right:0;bottom:64px;width:340px;max-width:calc(100vw - 40px);max-height:70vh;display:flex;flex-direction:column;background:#fff;color:var(--wprp-ink);border:1px solid #d6dade;border-radius:10px;box-shadow:0 10px 34px rgba(30,34,37,.28);overflow:hidden}
		.wprp-head{display:flex;align-items:center;gap:.5rem;padding:.6rem .75rem;background:var(--wprp-blue);color:#fff}
		.wprp-head .wprp-page{font-size:.78rem;opacity:.85;margin-left:auto;max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
		.wprp-x{background:none;border:none;color:#fff;font-size:20px;line-height:1;cursor:pointer;padding:0 0 0 .25rem}
		.wprp-list{padding:.5rem .75rem;overflow-y:auto;flex:1;min-height:60px}
		.wprp-muted{color:var(--wprp-gray);font-size:.85rem;margin:.4rem 0}
		.wprp-note{border:1px solid #e6e9ec;border-left:3px solid var(--wprp-cyan);border-radius:6px;padding:.45rem .6rem;margin-bottom:.5rem;font-size:.86rem}
		.wprp-note.is-resolved{opacity:.55;border-left-color:var(--wprp-gray)}
		.wprp-note .wprp-meta{display:flex;flex-wrap:wrap;gap:.35rem;align-items:center;font-size:.72rem;color:var(--wprp-gray);margin-bottom:.25rem}
		.wprp-tag{background:var(--wprp-blue);color:#fff;border-radius:3px;padding:.02rem .3rem;font-weight:600}
		.wprp-note .wprp-body p{margin:.2rem 0}
		.wprp-resolve{background:none;border:1px solid #cfd4d8;border-radius:4px;color:var(--wprp-gray);font-size:.72rem;cursor:pointer;padding:.1rem .4rem;margin-left:auto}
		.wprp-resolve:hover{border-color:var(--wprp-blue);color:var(--wprp-blue)}
		.wprp-form{display:flex;flex-direction:column;gap:.4rem;padding:.6rem .75rem;border-top:1px solid #e6e9ec;background:#f7f9fa}
		.wprp-form select,.wprp-form textarea{width:100%;border:1px solid #cfd4d8;border-radius:5px;padding:.35rem .5rem;font:inherit;font-size:.86rem;box-sizing:border-box}
		.wprp-form textarea{resize:vertical}
		.wprp-submit{align-self:flex-end;background:var(--wprp-blue);color:#fff;border:none;border-radius:5px;padding:.4rem .9rem;cursor:pointer;font-size:.86rem}
		.wprp-submit:hover{background:#0f4279}
		.wprp-submit:disabled{opacity:.6;cursor:default}
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
		var countEl = document.getElementById('wprp-fab-count');

		function api(path, opts) {
			opts = opts || {};
			opts.headers = Object.assign({ 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce }, opts.headers || {});
			return fetch(cfg.root + path, opts).then(function (r) {
				if (!r.ok) { throw new Error('HTTP ' + r.status); }
				return r.json();
			});
		}
		function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

		function noteHtml(n) {
			return '<div class="wprp-note ' + (n.resolved ? 'is-resolved' : '') + '" data-id="' + n.id + '">' +
				'<div class="wprp-meta"><span class="wprp-tag">' + esc(n.typeLabel) + '</span>' +
				'<span>' + esc(n.author) + '</span><span>' + esc(n.date) + '</span>' +
				'<button type="button" class="wprp-resolve">' + (n.resolved ? '<?php echo esc_js( __( 'Reopen', 'wp-red-pen' ) ); ?>' : '<?php echo esc_js( __( 'Resolve', 'wp-red-pen' ) ); ?>') + '</button></div>' +
				'<div class="wprp-body">' + n.body + '</div></div>';
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

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var text = body.value.trim();
			if (!text) { return; }
			var submit = form.querySelector('.wprp-submit');
			submit.disabled = true;
			api('/notes', { method: 'POST', body: JSON.stringify({ target: cfg.target, body: text, type: typeSel.value, url: cfg.url }) })
				.then(function () { body.value = ''; submit.disabled = false; load(); })
				.catch(function () { submit.disabled = false; });
		});

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
			echo '<li style="border-left:3px solid ' . ( $resolved ? '#3A3A3C' : '#00AEEE' ) . ';padding:.25rem .5rem;margin:0 0 .5rem;background:#f7f9fa;' . ( $resolved ? 'opacity:.6' : '' ) . '">';
			echo '<span style="background:#14569B;color:#fff;border-radius:3px;padding:0 .3rem;font-size:.7rem;font-weight:600">' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</span> ';
			echo '<small>' . esc_html( $author ? $author->display_name : '' ) . ' &middot; ' . esc_html( get_the_time( get_option( 'date_format' ), $n ) ) . '</small>';
			echo '<div style="font-size:.85rem;margin-top:.2rem">' . wp_kses_post( wpautop( $n->post_content ) ) . '</div>';
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

	$notes = get_posts(
		array(
			'post_type'      => WPRP_CPT,
			'post_status'    => $statuses,
			'posts_per_page' => 500,
			'orderby'        => 'date',
			'order'          => 'DESC',
		)
	);
	$types = wprp_note_types();

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
		$url = esc_url( add_query_arg( array( 'page' => 'wp-red-pen', 'status' => $key ), admin_url( 'admin.php' ) ) );
		echo ( $i++ ? ' | ' : '' ) . '<li><a href="' . $url . '"' . ( $filter === $key ? ' class="current"' : '' ) . '>' . esc_html( $label ) . '</a></li>';
	}
	echo '</ul><div style="clear:both"></div>';

	if ( ! $notes ) {
		echo '<p>' . esc_html__( 'No notes here.', 'wp-red-pen' ) . '</p></div>';
		return;
	}

	echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
	echo '<th>' . esc_html__( 'Type', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'Note', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'On page', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'By', 'wp-red-pen' ) . '</th><th>' . esc_html__( 'When', 'wp-red-pen' ) . '</th><th></th></tr></thead><tbody>';

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

		echo '<tr' . ( $resolved ? ' style="opacity:.55"' : '' ) . '>';
		echo '<td><span style="background:#14569B;color:#fff;border-radius:3px;padding:.05rem .35rem;font-size:.72rem;font-weight:600">' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</span></td>';
		echo '<td>' . wp_kses_post( wpautop( $n->post_content ) ) . '</td>';
		echo '<td>' . ( $target ? '<a href="' . esc_url( get_edit_post_link( $target ) ) . '">' . esc_html( get_the_title( $target ) ) . '</a> <a href="' . esc_url( get_permalink( $target ) ) . '" title="' . esc_attr__( 'View', 'wp-red-pen' ) . '">&#8599;</a>' : '&mdash;' ) . '</td>';
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

// ---------------------------------------------------------------------------
// Admin: load dashicons on our screens (for the menu icon + meta box chrome)
// ---------------------------------------------------------------------------
add_action(
	'admin_enqueue_scripts',
	function () {
		wp_enqueue_style( 'dashicons' );
	}
);
