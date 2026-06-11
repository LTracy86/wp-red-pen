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
 *
 * Does NOT touch any post, page, or option the plugin did not create.
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
