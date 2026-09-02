<?php
/**
 * Coverage for header URL placeholder expansion.
 *
 * Registered header URLs may carry sprintf placeholders pointing at the
 * template or stylesheet directory. Everything else — percent-encoded
 * characters in particular — has to survive untouched.
 *
 * @package wp-display-header
 */

/**
 * Tests expand_header_url() through the public theme_mod_header_image filter.
 */
class Wpdh_Header_Url_Test extends Wpdh_Test_Case {

	/**
	 * Post used to reach a singular view.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * The registered callback, kept so it can be removed individually.
	 *
	 * @var callable|null
	 */
	protected $headers_callback = null;

	/**
	 * Creates the post under test.
	 */
	public function set_up() {
		parent::set_up();

		$this->post_id = self::factory()->post->create();
	}

	/**
	 * Removes only this test's callback.
	 */
	public function tear_down() {
		if ( null !== $this->headers_callback ) {
			remove_filter( 'wpdh_get_headers', $this->headers_callback );
			$this->headers_callback = null;
		}

		parent::tear_down();
	}

	/**
	 * Resolves a single registered header through the plugin's filter.
	 *
	 * The post is set to "random" so the one registered header is always the
	 * one picked, which routes the URL through expand_header_url().
	 *
	 * @param string $url URL to register as the only available header.
	 * @return string The URL as the plugin resolves it.
	 */
	protected function resolve( $url ) {
		$this->headers_callback = function () use ( $url ) {
			return array(
				'test' => array(
					'url'           => $url,
					'thumbnail_url' => $url,
					'description'   => 'Test header',
				),
			);
		};
		add_filter( 'wpdh_get_headers', $this->headers_callback );

		update_post_meta( $this->post_id, '_wpdh_display_header', 'random' );
		$this->go_to( get_permalink( $this->post_id ) );

		return $this->plugin->theme_mod_header_image( 'fallback.jpg' );
	}

	/**
	 * A bare %s placeholder resolves to the template directory.
	 */
	public function test_expands_template_directory_placeholder() {
		$this->assertSame(
			get_template_directory_uri() . '/headers/image.jpg',
			$this->resolve( '%s/headers/image.jpg' )
		);
	}

	/**
	 * A %2$s placeholder resolves to the stylesheet directory.
	 */
	public function test_expands_stylesheet_directory_placeholder() {
		$this->assertSame(
			get_stylesheet_directory_uri() . '/headers/image.jpg',
			$this->resolve( '%2$s/headers/image.jpg' )
		);
	}

	/**
	 * Percent-encoded octets are left alone.
	 */
	public function test_preserves_percent_encoded_url() {
		$this->assertSame( self::ENCODED_URL, $this->resolve( self::ENCODED_URL ) );
	}

	/**
	 * An encoded space followed by "s" is not a placeholder.
	 *
	 * Regression: "%20s" is a valid sprintf width plus specifier, so this URL
	 * used to come back with a padded directory URI spliced into it, without
	 * raising anything for the try/catch to see.
	 */
	public function test_preserves_encoded_space_before_s() {
		$this->assertSame( self::ENCODED_SPACE_URL, $this->resolve( self::ENCODED_SPACE_URL ) );
	}

	/**
	 * A URL asking for more arguments than exist is returned unchanged.
	 */
	public function test_returns_url_with_too_few_arguments_unchanged() {
		$this->assertSame( '%s%s%s.jpg', $this->resolve( '%s%s%s.jpg' ) );
	}

	/**
	 * A URL with no placeholder at all is returned unchanged.
	 */
	public function test_returns_plain_url_unchanged() {
		$this->assertSame(
			'https://example.com/plain.jpg',
			$this->resolve( 'https://example.com/plain.jpg' )
		);
	}
}
