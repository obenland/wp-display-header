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
 *    what the meta box submit handler writes) actually surfaces the
 *    overridden header URL in the rendered singular view — i.e. the
 *    plugin's `theme_mod_header_image` filter is wired up end-to-end
 *    and Twenty Seventeen consumes it.
 *
 * 3. With no override saved, the rendered post does NOT contain a URL
 *    we'd only have written by setting the override meta. Regression
 *    check that the plugin doesn't accidentally inject a header on
 *    posts that haven't opted in.
 */

const OVERRIDE_URL = 'https://example.com/wp-content/uploads/wpdh-test-header.jpg';

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

	test( 'override URL appears in the rendered post when post meta is set', async ( {
		page,
	} ) => {
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

		// Visit the post and check that Twenty Seventeen renders our
		// override URL. Twenty Seventeen surfaces the custom header
		// image as a `background-image` on the `.custom-header-media`
		// element and as `<img>` markup elsewhere — in both cases the
		// URL appears verbatim in the rendered HTML, so a `toContain`
		// on the page content is the cheapest robust assertion against
		// theme markup churn.
		const permalink = wp( [ 'post', 'url', postId ] );
		await page.goto( permalink );

		const html = await page.content();
		expect( html ).toContain( OVERRIDE_URL );
	} );

	test( 'override URL is absent when no post meta is set', async ( {
		page,
	} ) => {
		const postId = wp( [
			'post',
			'create',
			'--post_type=post',
			'--post_status=publish',
			'--post_title=No Header Override',
			'--porcelain',
		] );

		const permalink = wp( [ 'post', 'url', postId ] );
		await page.goto( permalink );

		// The plugin must not inject the override URL on a post that
		// hasn't opted in via post meta.
		const html = await page.content();
		expect( html ).not.toContain( OVERRIDE_URL );
	} );
} );
