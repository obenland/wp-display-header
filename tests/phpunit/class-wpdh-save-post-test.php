<?php
/**
 * Coverage for Obenland_Wp_Display_Header::save_post().
 *
 * @package wp-display-header
 */

/**
 * Tests the post meta box save handler.
 */
class Wpdh_Save_Post_Test extends Wpdh_Test_Case {

	/**
	 * Post being edited.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * An editor, who may edit the post.
	 *
	 * @var int
	 */
	protected $editor_id;

	/**
	 * A subscriber, who may not.
	 *
	 * @var int
	 */
	protected $subscriber_id;

	/**
	 * Creates the users and the post under test.
	 */
	public function set_up() {
		parent::set_up();

		$this->editor_id     = self::factory()->user->create( array( 'role' => 'editor' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->post_id       = self::factory()->post->create( array( 'post_author' => $this->editor_id ) );
	}

	/**
	 * Reads the stored header for the post under test.
	 *
	 * @return string
	 */
	protected function stored_header() {
		return get_post_meta( $this->post_id, '_wpdh_display_header', true );
	}

	/**
	 * A capable user's URL is stored.
	 */
	public function test_stores_url_for_capable_user() {
		$this->post_as( $this->editor_id, 'https://example.com/header.jpg' );
		$this->plugin->save_post( $this->post_id );

		$this->assertSame( 'https://example.com/header.jpg', $this->stored_header() );
	}

	/**
	 * Percent-encoded octets survive the round trip.
	 *
	 * Regression: sanitize_text_field() strips them, which turned
	 * "gr%C3%B6%C3%9Fe.jpg" into "gre.jpg".
	 */
	public function test_preserves_percent_encoded_url() {
		$this->post_as( $this->editor_id, self::ENCODED_URL );
		$this->plugin->save_post( $this->post_id );

		$this->assertSame( self::ENCODED_URL, $this->stored_header() );
	}

	/**
	 * The "random" keyword is stored verbatim rather than treated as a URL.
	 */
	public function test_stores_random_keyword() {
		$this->post_as( $this->editor_id, 'random' );
		$this->plugin->save_post( $this->post_id );

		$this->assertSame( 'random', $this->stored_header() );
	}

	/**
	 * The "remove-header" keyword is stored verbatim.
	 */
	public function test_stores_remove_header_keyword() {
		$this->post_as( $this->editor_id, 'remove-header' );
		$this->plugin->save_post( $this->post_id );

		$this->assertSame( 'remove-header', $this->stored_header() );
	}

	/**
	 * The reset button drops the stored header.
	 */
	public function test_reset_deletes_meta() {
		update_post_meta( $this->post_id, '_wpdh_display_header', 'https://example.com/old.jpg' );

		$this->post_as( $this->editor_id, 'https://example.com/new.jpg', array( 'wpdh-reset-header' => 'Restore' ) );
		$this->plugin->save_post( $this->post_id );

		$this->assertSame( '', $this->stored_header() );
	}

	/**
	 * A request without a nonce field is ignored, and warns about nothing.
	 */
	public function test_ignores_request_without_nonce() {
		wp_set_current_user( $this->editor_id );
		$_POST = array( 'wp-display-header' => 'https://example.com/header.jpg' );

		$this->plugin->save_post( $this->post_id );

		$this->assertSame( '', $this->stored_header() );
	}

	/**
	 * A request carrying a bad nonce is ignored.
	 */
	public function test_ignores_invalid_nonce() {
		wp_set_current_user( $this->editor_id );
		$_POST = array(
			'wp-display-header'       => 'https://example.com/header.jpg',
			'wp-display-header-nonce' => 'not-a-real-nonce',
		);

		$this->plugin->save_post( $this->post_id );

		$this->assertSame( '', $this->stored_header() );
	}

	/**
	 * A valid nonce alone is not enough without the capability.
	 *
	 * Any logged-in user can mint a nonce for this action from their own
	 * profile screen, so the capability check is what actually gates the write.
	 */
	public function test_ignores_user_without_capability() {
		$nonce = $this->post_as( $this->subscriber_id, 'https://example.com/injected.jpg' );

		$this->assertTrue(
			(bool) wp_verify_nonce( $nonce, 'wp-display-header' ),
			'The subscriber should hold a structurally valid nonce.'
		);

		$this->plugin->save_post( $this->post_id );

		$this->assertSame( '', $this->stored_header() );
	}

	/**
	 * A request without the header field leaves an existing value alone.
	 */
	public function test_ignores_request_without_header_field() {
		update_post_meta( $this->post_id, '_wpdh_display_header', 'https://example.com/keep.jpg' );

		$this->post_as( $this->editor_id, null );
		$this->plugin->save_post( $this->post_id );

		$this->assertSame( 'https://example.com/keep.jpg', $this->stored_header() );
	}

	/**
	 * A non-scalar submission is discarded instead of reaching esc_url_raw().
	 */
	public function test_array_input_does_not_fatal() {
		$this->post_as( $this->editor_id, array( 'unexpected' ) );
		$this->plugin->save_post( $this->post_id );

		$this->assertSame( '', $this->stored_header() );
	}

	/**
	 * The handler returns its argument so it stays usable as a filter.
	 */
	public function test_returns_post_id() {
		$this->post_as( $this->editor_id, 'https://example.com/header.jpg' );

		$this->assertSame( $this->post_id, $this->plugin->save_post( $this->post_id ) );
	}
}
