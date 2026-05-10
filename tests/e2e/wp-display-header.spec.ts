import { test, expect } from '@playwright/test';
import { loginAsAdmin, wp } from './utils';

/**
 * End-to-end coverage for WP Display Header.
 *
 * The plugin only instantiates when the active theme supports
 * `custom-header`. wp-env is configured to install + activate
 * Twenty Seventeen, which does support it.
 *
 * Three things to pin:
 *
 * 1. The Header meta box is added to the post edit screen — the
 *    plugin's main user-facing control. If the meta box never renders,
 *    no per-post header can ever be set.
 *
 * 2. Saving a post header URL via the meta box (simulated through the
 *    underlying post-meta storage `_wpdh_display_header`, which is
 *    what the meta box submit handler writes) actually overrides
 *    `theme_mod_header_image` for that post — i.e. the plugin's
 *    `theme_mod_header_image` filter callback queries the post meta
 *    and returns the override URL on a singular view.
 *
 * 3. With no override saved, the same filter chain returns the input
 *    unchanged. The plugin must not regress the unmodified path.
 */

const OVERRIDE_URL =
	'https://example.com/wp-content/uploads/wpdh-test-header.jpg';

/**
 * Asks wp-cli to apply `theme_mod_header_image` in a singular-post
 * context for the given post ID. The plugin's filter callback only
 * queries the post meta when `is_singular()` is true, so the wp eval
 * sets up a `WP_Query` for the specific post and assigns it to both
 * `$wp_query` and `$wp_the_query` (some WP code paths read one or the
 * other). Returning the input default unchanged is the "no override"
 * case.
 */
function applyHeaderFilterForPost(
	postId: string,
	defaultHeader: string
): string {
	return wp( [
		'eval',
		`global $wp_query, $wp_the_query;
$q = new WP_Query( array( 'p' => ${ postId }, 'post_type' => 'post' ) );
$wp_query = $q;
$wp_the_query = $q;
echo apply_filters( 'theme_mod_header_image', '${ defaultHeader }' );`,
	] );
}

test.describe( 'WP Display Header', () => {
	test( 'renders the Header meta box on the post edit screen', async ( {
		page,
	} ) => {
		await loginAsAdmin( page );

		// Create a post via wp-cli so we have a stable ID to edit.
		const postId = wp( [
			'post',
			'create',
			'--post_type=post',
			'--post_status=publish',
			'--post_title=Header meta box test',
			'--porcelain',
		] );
		expect( postId ).toMatch( /^\d+$/ );

		// Open the legacy classic editor where this plugin's add_meta_boxes
		// hook fires; the block editor doesn't render PHP-side meta boxes
		// the same way, but the underlying hook is still wired in.
		await page.goto(
			`/wp-admin/post.php?post=${ postId }&action=edit&classic-editor`
		);

		// The meta box's wrapper id is the same as the meta box id
		// registered by add_meta_box('wp-display-header', ...).
		const metaBox = page.locator( '#wp-display-header' );
		await expect( metaBox ).toBeAttached( { timeout: 10000 } );
	} );

	test( 'theme_mod_header_image filter returns the override when post meta is set', () => {
		// Create a post and set the per-post header URL via wp-cli — the
		// same `_wpdh_display_header` post meta the meta box submit
		// handler writes (see class-obenland-wp-display-header.php's
		// `save_post` callback: `update_post_meta( $post_ID,
		// '_wpdh_display_header', $value );`).
		const postId = wp( [
			'post',
			'create',
			'--post_type=post',
			'--post_status=publish',
			'--post_title=Override Header',
			'--porcelain',
		] );

		wp( [
			'post',
			'meta',
			'update',
			postId,
			'_wpdh_display_header',
			OVERRIDE_URL,
		] );

		expect( applyHeaderFilterForPost( postId, 'default-header.jpg' ) ).toBe(
			OVERRIDE_URL
		);
	} );

	test( 'theme_mod_header_image filter passes through when no post meta is set', () => {
		const postId = wp( [
			'post',
			'create',
			'--post_type=post',
			'--post_status=publish',
			'--post_title=No Header Override',
			'--porcelain',
		] );

		expect( applyHeaderFilterForPost( postId, 'default-header.jpg' ) ).toBe(
			'default-header.jpg'
		);
	} );
} );
