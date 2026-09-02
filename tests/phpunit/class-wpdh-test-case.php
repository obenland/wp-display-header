<?php
/**
 * Shared fixture for the plugin's unit tests.
 *
 * @package wp-display-header
 */

/**
 * Base class handling instance construction and request setup.
 */
abstract class Wpdh_Test_Case extends WP_UnitTestCase {

	/**
	 * A URL whose non-ASCII characters are percent-encoded.
	 *
	 * Encoded octets are what sanitize_text_field() strips, so this is the
	 * shape that regressed.
	 *
	 * @var string
	 */
	const ENCODED_URL = 'https://example.com/wp-content/uploads/gr%C3%B6%C3%9Fe.jpg';

	/**
	 * A URL where an encoded space precedes an "s".
	 *
	 * "%20s" is a valid sprintf width plus specifier, so this is the shape
	 * that expand_header_url() used to mangle without raising anything.
	 *
	 * @var string
	 */
	const ENCODED_SPACE_URL = 'https://example.com/wp-content/uploads/header%20small.jpg';

	/**
	 * Plugin instance under test.
	 *
	 * @var Obenland_Wp_Display_Header
	 */
	protected $plugin;

	/**
	 * Builds a fresh instance and clears request state.
	 */
	public function set_up() {
		parent::set_up();

		$this->plugin = new Obenland_Wp_Display_Header();
		$_POST        = array();
	}

	/**
	 * Clears request state so it cannot leak between tests.
	 */
	public function tear_down() {
		$_POST = array();

		parent::tear_down();
	}

	/**
	 * Populates $_POST as the given user, with a nonce valid for them.
	 *
	 * Nonces are tied to the current user, so the user is switched before the
	 * nonce is minted.
	 *
	 * @param int   $user_id User making the request.
	 * @param mixed $value   Value for the header field. Pass null to omit it.
	 * @param array $extra   Additional fields to merge into the request.
	 * @return string The nonce placed in the request.
	 */
	protected function post_as( $user_id, $value, $extra = array() ) {
		wp_set_current_user( $user_id );

		$nonce = wp_create_nonce( 'wp-display-header' );
		$_POST = array_merge( array( 'wp-display-header-nonce' => $nonce ), $extra );

		if ( null !== $value ) {
			$_POST['wp-display-header'] = $value;
		}

		return $nonce;
	}
}
