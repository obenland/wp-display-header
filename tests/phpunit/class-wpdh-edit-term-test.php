<?php
/**
 * Coverage for Obenland_Wp_Display_Header::edit_term().
 *
 * @package wp-display-header
 */

/**
 * Tests the taxonomy term save handler.
 */
class Wpdh_Edit_Term_Test extends Wpdh_Test_Case {

	/**
	 * Term being edited.
	 *
	 * @var int
	 */
	protected $term_id;

	/**
	 * Term taxonomy ID of the term being edited.
	 *
	 * @var int
	 */
	protected $tt_id;

	/**
	 * An administrator, who may edit terms.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * A subscriber, who may not.
	 *
	 * @var int
	 */
	protected $subscriber_id;

	/**
	 * Creates the users and the term under test.
	 */
	public function set_up() {
		parent::set_up();

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );

		$term          = self::factory()->term->create_and_get( array( 'taxonomy' => 'category' ) );
		$this->term_id = $term->term_id;
		$this->tt_id   = $term->term_taxonomy_id;

		delete_option( 'wpdh_tax_meta' );
	}

	/**
	 * Reads the stored header for the term under test.
	 *
	 * @return string
	 */
	protected function stored_header() {
		$meta = get_option( 'wpdh_tax_meta', array() );

		return isset( $meta[ $this->tt_id ] ) ? $meta[ $this->tt_id ] : '';
	}

	/**
	 * A capable user's URL is stored against the term taxonomy ID.
	 */
	public function test_stores_url_for_capable_user() {
		$this->post_as( $this->admin_id, 'https://example.com/term.jpg' );
		$this->plugin->edit_term( $this->term_id, $this->tt_id );

		$this->assertSame( 'https://example.com/term.jpg', $this->stored_header() );
	}

	/**
	 * Percent-encoded octets survive the round trip.
	 */
	public function test_preserves_percent_encoded_url() {
		$this->post_as( $this->admin_id, self::ENCODED_URL );
		$this->plugin->edit_term( $this->term_id, $this->tt_id );

		$this->assertSame( self::ENCODED_URL, $this->stored_header() );
	}

	/**
	 * The reset button removes only this term's entry.
	 */
	public function test_reset_removes_only_this_terms_entry() {
		update_option(
			'wpdh_tax_meta',
			array(
				$this->tt_id => 'https://example.com/old.jpg',
				999999       => 'https://example.com/other.jpg',
			)
		);

		$this->post_as( $this->admin_id, 'https://example.com/new.jpg', array( 'wpdh-reset-header' => 'Restore' ) );
		$this->plugin->edit_term( $this->term_id, $this->tt_id );

		$meta = get_option( 'wpdh_tax_meta' );

		$this->assertArrayNotHasKey( $this->tt_id, $meta );
		$this->assertSame( 'https://example.com/other.jpg', $meta[999999] );
	}

	/**
	 * A valid nonce alone is not enough without the capability.
	 */
	public function test_ignores_user_without_capability() {
		$this->post_as( $this->subscriber_id, 'https://example.com/injected.jpg' );
		$this->plugin->edit_term( $this->term_id, $this->tt_id );

		$this->assertSame( '', $this->stored_header() );
	}

	/**
	 * A request carrying a bad nonce is ignored.
	 */
	public function test_ignores_invalid_nonce() {
		wp_set_current_user( $this->admin_id );
		$_POST = array(
			'wp-display-header'       => 'https://example.com/term.jpg',
			'wp-display-header-nonce' => 'not-a-real-nonce',
		);

		$this->plugin->edit_term( $this->term_id, $this->tt_id );

		$this->assertSame( '', $this->stored_header() );
	}

	/**
	 * A corrupted option is treated as empty rather than fataling.
	 */
	public function test_recovers_from_non_array_option() {
		update_option( 'wpdh_tax_meta', 'not-an-array' );

		$this->post_as( $this->admin_id, 'https://example.com/term.jpg' );
		$this->plugin->edit_term( $this->term_id, $this->tt_id );

		$this->assertSame( 'https://example.com/term.jpg', $this->stored_header() );
	}

	/**
	 * The handler returns its argument so it stays usable as a filter.
	 */
	public function test_returns_term_id() {
		$this->post_as( $this->admin_id, 'https://example.com/term.jpg' );

		$this->assertSame( $this->term_id, $this->plugin->edit_term( $this->term_id, $this->tt_id ) );
	}
}
