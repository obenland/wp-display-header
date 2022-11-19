<?php
/**
 * Plugin Name: WP Display Header
 * Plugin URI:  http://en.wp.obenland.it/wp-display-header/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-display-header
 * Description: This plugin lets you specify a header image for each post and taxonomy/author archive page individually, from your default headers and custom headers.
 * Version:     7
 * Author:      Konstantin Obenland
 * Author URI:  http://en.wp.obenland.it/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-display-header
 * Text Domain: wp-display-header
 * Domain Path: /lang
 * License:     GPLv2
 *
 * @package wp-display-header
 */

/**
 * Instantiates the class if current theme supports Custom Headers.
 *
 * @since 1.2 - 03.05.2011
 */
function obenland_wpdh_instantiate() {
	if ( current_theme_supports( 'custom-header' ) ) {
		if ( ! class_exists( 'Obenland_Wp_Plugins_V5' ) ) {
			require_once 'class-obenland-wp-plugins-v5.php';
		}

		require_once 'class-obenland-wp-display-header.php';

		new Obenland_Wp_Display_Header();
	}
}
add_action( 'init', 'obenland_wpdh_instantiate', 1 );

/**
 * Plugin activation.
 */
function obenland_wp_display_header_activation() {
	load_plugin_textdomain( 'wp-display-header', false, 'wp-display-header/lang' );

	if ( ! current_theme_supports( 'custom-header' ) ) {
		wp_die( esc_html__( 'Your current theme does not support Custom Headers.', 'wp-display-header' ), '', array( 'back_link' => true ) );
	}

	if ( version_compare( get_bloginfo( 'version' ), '3.2', '<' ) ) {
		wp_die( esc_html__( 'WP Display Headers requires WordPress version 3.2 or later.', 'wp-display-header' ), '', array( 'back_link' => true ) );
	}
}
register_activation_hook( __FILE__, 'obenland_wp_display_header_activation' );
