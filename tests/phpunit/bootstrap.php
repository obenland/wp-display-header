<?php
/**
 * PHPUnit bootstrap for the WordPress test suite.
 *
 * Expects the WordPress PHPUnit library to be available. Under wp-env the
 * tests containers expose it at /wordpress-phpunit and set WP_TESTS_DIR
 * accordingly; the wp-phpunit Composer package is used as a fallback.
 *
 * @package wp-display-header
 */

$wpdh_root = dirname( __DIR__, 2 );

/*
 * Loading Composer's autoloader up front registers the PHPUnit polyfills the
 * WordPress test suite expects to find, so no environment variable has to be
 * set for it.
 */
require_once $wpdh_root . '/vendor/autoload.php';

$wpdh_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $wpdh_tests_dir ) {
	$wpdh_tests_dir = $wpdh_root . '/vendor/wp-phpunit/wp-phpunit';
}

if ( ! file_exists( "{$wpdh_tests_dir}/includes/functions.php" ) ) {
	echo 'Could not find the WordPress test suite. Run the tests through wp-env: npm run test:php' . PHP_EOL;
	exit( 1 );
}

require_once "{$wpdh_tests_dir}/includes/functions.php";

/**
 * Declares Custom Header support and loads the plugin's classes.
 *
 * The plugin only instantiates itself when the active theme supports custom
 * headers, so the support is declared before that check runs. The tests build
 * their own instance rather than relying on the `init` hook, so only the class
 * files are required here.
 *
 * @since 8.1
 */
function wpdh_manually_load_plugin() {
	add_theme_support( 'custom-header' );

	require_once dirname( __DIR__, 2 ) . '/class-obenland-wp-plugins-v5.php';
	require_once dirname( __DIR__, 2 ) . '/class-obenland-wp-display-header.php';
	require_once dirname( __DIR__, 2 ) . '/wp-display-header.php';
}
tests_add_filter( 'muplugins_loaded', 'wpdh_manually_load_plugin' );

require "{$wpdh_tests_dir}/includes/bootstrap.php";

require_once __DIR__ . '/class-wpdh-test-case.php';
