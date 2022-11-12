<?php
/**
 * Uninstall.
 *
 * @package wp-display-header
 */

// Don't uninstall unless you absolutely want to!
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	wp_die( 'WP_UNINSTALL_PLUGIN undefined.' );
}

// Delete all meta data.
delete_option( 'wpdh_tax_meta' );
delete_metadata( 'post', 0, '_wpdh_display_header', '', true );
delete_metadata( 'user', 0, 'wp-display-header', '', true );


/* Goodbye! Thank you for having me! */
