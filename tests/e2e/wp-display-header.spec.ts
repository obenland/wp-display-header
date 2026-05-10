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
 *    `theme_mod_header_image` on a single post view. This is the
 *    plugin's `theme_mod_header_image` filter at work end-to-end.
 *
 * 3. With no override saved, the post falls back to the theme's default
 *    header. The plugin must not regress the unmodified path.
 */

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

	test( 'overrides theme_mod_header_image for a post when post meta is set', () => {
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

		const overrideUrl =
			'https://example.com/wp-content/uploads/test-header.jpg';
		wp( [
			'post',
			'meta',
			'update',
			postId,
			'_wpdh_display_header',
			overrideUrl,
		] );

		// Apply the filter directly via wp eval so we don't depend on
		// any theme-specific markup. The plugin's
		// `theme_mod_header_image` filter callback queries the current
		// post's meta on a singular view; `wp eval` inside a
		// `--url=<post_permalink>` context reproduces that environment.
		const permalink = wp( [ 'post', 'url', postId ] );
		const filtered = wp( [
			`--url=${ permalink }`,
			'eval',
			"echo apply_filters( 'theme_mod_header_image', 'default-header.jpg' );",
		] );

		expect( filtered ).toBe( overrideUrl );
	} );

	test( 'leaves the default theme header in place when no override is set', () => {
		const postId = wp( [
			'post',
			'create',
			'--post_type=post',
			'--post_status=publish',
			'--post_title=No Header Override',
			'--porcelain',
		] );

		const permalink = wp( [ 'post', 'url', postId ] );
		const filtered = wp( [
			`--url=${ permalink }`,
			'eval',
			"echo apply_filters( 'theme_mod_header_image', 'default-header.jpg' );",
		] );

		expect( filtered ).toBe( 'default-header.jpg' );
	} );
} );
