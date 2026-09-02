<?php
/**
 * Coverage for Obenland_Wp_Display_Header::update_user().
 *
 * @package wp-display-header
 */

/**
 * Tests the user profile save handler.
 */
class Wpdh_Update_User_Test extends Wpdh_Test_Case {

	/**
	 * An administrator, who may edit any user.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * A subscriber, who may edit only themselves.
	 *
	 * @var int
	 */
	protected $subscriber_id;

	/**
	 * A second subscriber, used as the target of a cross-user write.
	 *
	 * @var int
	 */
	protected $other_user_id;

	/**
	 * Creates the users under test.
	 */
	public function set_up() {
		parent::set_up();

		$this->admin_id      = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->other_user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	/**
	 * Reads the stored header for a user.
	 *
	 * @param int $user_id User to read.
	 * @return string
	 */
	protected function stored_header( $user_id ) {
		return get_user_meta( $user_id, 'wp-display-header', true );
	}

	/**
	 * A user editing their own profile stores their selection.
	 */
	public function test_stores_url_on_own_profile() {
		$this->post_as( $this->subscriber_id, 'https://example.com/author.jpg' );
		$this->plugin->update_user( $this->subscriber_id );

		$this->assertSame( 'https://example.com/author.jpg', $this->stored_header( $this->subscriber_id ) );
	}

	/**
	 * Percent-encoded octets survive the round trip.
	 */
	public function test_preserves_percent_encoded_url() {
		$this->post_as( $this->subscriber_id, self::ENCODED_URL );
		$this->plugin->update_user( $this->subscriber_id );

		$this->assertSame( self::ENCODED_URL, $this->stored_header( $this->subscriber_id ) );
	}

	/**
	 * An administrator may write to somebody else's profile.
	 */
	public function test_administrator_may_edit_another_user() {
		$this->post_as( $this->admin_id, 'https://example.com/admin-set.jpg' );
		$this->plugin->update_user( $this->other_user_id );

		$this->assertSame( 'https://example.com/admin-set.jpg', $this->stored_header( $this->other_user_id ) );
	}

	/**
	 * A subscriber may not write to somebody else's profile.
	 */
	public function test_ignores_user_without_capability() {
		$this->post_as( $this->subscriber_id, 'https://example.com/injected.jpg' );
		$this->plugin->update_user( $this->other_user_id );

		$this->assertSame( '', $this->stored_header( $this->other_user_id ) );
	}

	/**
	 * The reset button drops the stored header.
	 */
	public function test_reset_deletes_meta() {
		update_user_meta( $this->subscriber_id, 'wp-display-header', 'https://example.com/old.jpg' );

		$this->post_as( $this->subscriber_id, 'https://example.com/new.jpg', array( 'wpdh-reset-header' => 'Restore' ) );
		$this->plugin->update_user( $this->subscriber_id );

		$this->assertSame( '', $this->stored_header( $this->subscriber_id ) );
	}

	/**
	 * Reset works when no radio is selected.
	 */
	public function test_reset_without_selection_deletes_meta() {
		update_user_meta( $this->subscriber_id, 'wp-display-header', 'https://example.com/old.jpg' );

		$this->post_as( $this->subscriber_id, null, array( 'wpdh-reset-header' => 'Restore' ) );
		$this->plugin->update_user( $this->subscriber_id );

		$this->assertSame( '', $this->stored_header( $this->subscriber_id ) );
	}

	/**
	 * A request carrying a bad nonce is ignored.
	 */
	public function test_ignores_invalid_nonce() {
		wp_set_current_user( $this->subscriber_id );
		$_POST = array(
			'wp-display-header'       => 'https://example.com/author.jpg',
			'wp-display-header-nonce' => 'not-a-real-nonce',
		);

		$this->plugin->update_user( $this->subscriber_id );

		$this->assertSame( '', $this->stored_header( $this->subscriber_id ) );
	}

	/**
	 * The handler returns its argument so it stays usable as a filter.
	 */
	public function test_returns_user_id() {
		$this->post_as( $this->subscriber_id, 'https://example.com/author.jpg' );

		$this->assertSame( $this->subscriber_id, $this->plugin->update_user( $this->subscriber_id ) );
	}
}
