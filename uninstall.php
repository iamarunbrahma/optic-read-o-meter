<?php
/**
 * Uninstall handler for Optic Read-O-Meter.
 *
 * Removes the plugin's stored option and per-post meta. Runs only when WP
 * processes a real plugin uninstall, not on deactivate.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'optrom_settings' );

delete_post_meta_by_key( '_optrom_words' );
delete_post_meta_by_key( '_optrom_images' );
delete_post_meta_by_key( '_optrom_disabled' );
delete_post_meta_by_key( '_optrom_override_minutes' );
