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
 * Deliberately only the class files. The main plugin file registers an `init`
 * callback that constructs a fully hooked instance, which would leave the save
 * handlers attached for the whole run: creating a post or term through the
 * factories would then reach them, and a test that had already populated $_POST
 * would see a write it never asked for. Each test constructs the instance it
 * needs and calls it directly instead.
 *
 * Custom Header support is declared because the plugin is only meaningful on a
 * theme that has it.
 *
 * @since 9 - 02.09.2026
 */
function wpdh_manually_load_plugin() {
	add_theme_support( 'custom-header' );

	require_once dirname( __DIR__, 2 ) . '/class-obenland-wp-plugins-v5.php';
	require_once dirname( __DIR__, 2 ) . '/class-obenland-wp-display-header.php';
}
tests_add_filter( 'muplugins_loaded', 'wpdh_manually_load_plugin' );

require "{$wpdh_tests_dir}/includes/bootstrap.php";

require_once __DIR__ . '/class-wpdh-test-case.php';
