<?php
/**
 * Uninstall handler for WP Red Pen.
 *
 * Runs when the user DELETES the plugin from the WordPress admin (not on a mere
 * deactivation). Removes every piece of state the plugin wrote so the site is
 * left exactly as it was before WP Red Pen was installed.
 *
 * Removes:
 *   - every wprp_note post (the notes themselves) and their postmeta
 *   - the wprp_devmode per-user preference on every user
 *   - every wprp_* option (including the Hub connect token and the reviewer-link
 *     token hashes - secrets must not survive a delete)
 *   - the plugin's transients
 *   - the uploads/wp-red-pen screenshots folder and its files
 *
 * Does NOT touch any post, page, or option the plugin did not create.
 *
 * NOTE: keep the option list below in sync with the WPRP_*_OPT define() block in
 * wp-red-pen.php. uninstall.php runs standalone (the main plugin is not loaded),
 * so the option names are spelled out as literals here on purpose.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/** Delete all Red Pen data on the current site. */
function wprp_uninstall_site() {
	global $wpdb;

	// Delete every note (wp_delete_post cascades its postmeta).
	$note_ids = $wpdb->get_col(
		$wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s", 'wprp_note' )
	);
	foreach ( $note_ids as $id ) {
		wp_delete_post( (int) $id, true );
	}

	// Drop the per-user Dev Mode preference.
	$wpdb->delete( $wpdb->usermeta, array( 'meta_key' => 'wprp_devmode' ) );

	// Drop every option the plugin wrote. Includes secrets (Hub token, reviewer-link
	// token hashes) - these must not be left behind after a delete.
	$options = array(
		'wprp_db_version',    // schema version
		'wprp_show_on',       // where the widget appears
		'wprp_pin_color',     // custom pin colour
		'wprp_dark',          // dark mode
		'wprp_custom_types',  // user-defined note types
		'wprp_hub_url',       // Red Pen Hub URL
		'wprp_hub_token',     // Red Pen Hub connect token (secret)
		'wprp_agents',        // enabled agent platforms
		'wprp_agent_custom',  // custom agent label
		'wprp_review_tokens', // client-reviewer link token hashes (secret)
		'wprp_shot_guarded',  // one-time screenshot-folder guard flag
		'wprp_brief_secret',  // random suffix for agent-brief filenames
		'wprp_brand',         // white-label report branding (title/logo/accent/credit)
		'wprp_pro_key',       // legacy pre-open-source license key (may exist on old installs)
	);
	foreach ( $options as $opt ) {
		delete_option( $opt );
	}

	// Drop the plugin's transients (the dynamic rate-limit / new-link ones expire on their own).
	delete_transient( 'wprp_assignable_users' );
	delete_transient( 'wprp_open_count' );

	// Remove the screenshots folder (uploads/wp-red-pen) and its files.
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'wp-red-pen';
	if ( is_dir( $dir ) ) {
		$files = glob( $dir . '/*' );
		if ( is_array( $files ) ) {
			foreach ( $files as $f ) {
				if ( is_file( $f ) ) {
					wp_delete_file( $f );
				}
			}
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.dir_system_operations_rmdir -- cleanup of our own empty dir
		@rmdir( $dir );
	}
}

if ( is_multisite() ) {
	$sites = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $sites as $site_id ) {
		switch_to_blog( $site_id );
		wprp_uninstall_site();
		restore_current_blog();
	}
} else {
	wprp_uninstall_site();
}
