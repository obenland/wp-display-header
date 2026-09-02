import { test, expect } from '@playwright/test';
import { loginAsAdmin, wp } from './utils';

/**
 * End-to-end coverage for WP Display Header.
 *
 * The plugin only instantiates when the active theme supports
 * `custom-header`. wp-env is configured to install + activate
 * Twenty Seventeen, which does support it.
 *
 * Four things to pin:
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
 *
 * 4. Taxonomy archives render without PHP warnings whether or not the
 *    `wpdh_tax_meta` option holds a usable value, and honour a header
 *    saved for the term. See the taxonomy describe block below for why
 *    the warning, rather than the returned header, is what's asserted.
 */

const OVERRIDE_URL =
	'https://example.com/wp-content/uploads/wpdh-test-header.jpg';

/** Disambiguates categories created within the same millisecond. */
let uniqueSuffix = 0;

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
// the_post() advances the loop pointer and assigns the global \$post,
// which the plugin's get_active_post_header() consults via get_post().
// Without it the method reads get_post()->ID on a null and bails to
// the input default.
$q->the_post();
echo apply_filters( 'theme_mod_header_image', '${ defaultHeader }' );`,
	] );
}

/**
 * Applies `theme_mod_header_image` in a category archive context. The
 * plugin's callback only consults `wpdh_tax_meta` when `is_category()`
 * is true, and reads the term from `get_queried_object()`, so the wp
 * eval builds a category `WP_Query` and assigns it to both `$wp_query`
 * and `$wp_the_query`. No `the_post()` here — unlike the post path,
 * `get_active_tax_header()` never touches the loop.
 */
function applyHeaderFilterForCategory(
	termId: string,
	defaultHeader: string
): string {
	return wp( [
		'eval',
		`global $wp_query, $wp_the_query;
$q = new WP_Query( array( 'cat' => ${ termId } ) );
$wp_query = $q;
$wp_the_query = $q;
echo apply_filters( 'theme_mod_header_image', '${ defaultHeader }' );`,
	] );
}

/**
 * Creates a category with one published post in it, so the archive is a
 * real query with results rather than a 404.
 *
 * The name is suffixed to keep it unique: `wp term create` fails on a
 * duplicate name, and wp-env keeps its database between runs.
 *
 * @param name Category name.
 * @return The term ID and its term taxonomy ID — `wpdh_tax_meta` is
 *         keyed by the latter, and the two are not interchangeable.
 */
function createCategoryWithPost( name: string ): {
	termId: string;
	ttId: string;
} {
	const uniqueName = `${ name } ${ Date.now() }-${ uniqueSuffix++ }`;
	const termId = wp( [
		'term',
		'create',
		'category',
		uniqueName,
		'--porcelain',
	] );
	const ttId = wp( [
		'term',
		'get',
		'category',
		termId,
		'--field=term_taxonomy_id',
	] );

	wp( [
		'post',
		'create',
		'--post_type=post',
		'--post_status=publish',
		`--post_title=${ uniqueName } post`,
		`--post_category=${ termId }`,
		'--porcelain',
	] );

	return { termId, ttId };
}

/**
 * Empties debug.log so a following page load can be asserted on in
 * isolation.
 */
function truncateDebugLog(): void {
	wp( [ 'eval', "file_put_contents( WP_CONTENT_DIR . '/debug.log', '' );" ] );
}

/**
 * Returns the contents of debug.log, or an empty string if WordPress
 * has not had cause to create it.
 */
function readDebugLog(): string {
	return wp( [
		'eval',
		`$log = WP_CONTENT_DIR . '/debug.log';
echo file_exists( $log ) ? file_get_contents( $log ) : '';`,
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

	/**
	 * Taxonomy archives — see https://github.com/obenland/wp-display-header/issues/79.
	 *
	 * `get_active_tax_header()` used to assign `$tt_id` only inside an
	 * `if ( $active )` branch while reading it unconditionally in the
	 * `apply_filters()` call below it, so an empty `wpdh_tax_meta` made
	 * every taxonomy archive emit `Undefined variable $tt_id`.
	 *
	 * The returned header is *not* what pins that bug: with no option
	 * set, the buggy code passed `false` to `get_active_header()` and
	 * the caller's truthiness check left the header untouched — exactly
	 * what the fixed code does with `''`. The warning was the only
	 * observable difference, so that is what the first test asserts on.
	 * It reads debug.log rather than the rendered page: `display_errors`
	 * is off in the wp-env PHP image, so the warning never reaches the
	 * response body, but .wp-env.json sets `WP_DEBUG_LOG`.
	 */
	test.describe( 'taxonomy archives', () => {
		test( 'render without PHP warnings when wpdh_tax_meta is unset', async ( {
			page,
		} ) => {
			const { termId } = createCategoryWithPost( 'Unset Tax Meta' );

			wp( [ 'eval', "delete_option( 'wpdh_tax_meta' );" ] );
			truncateDebugLog();

			const response = await page.goto( `/?cat=${ termId }` );
			expect( response?.status() ).toBe( 200 );

			expect( readDebugLog() ).not.toContain( 'Undefined variable' );
		} );

		test( 'theme_mod_header_image filter returns the override saved for the term', () => {
			const { termId, ttId } =
				createCategoryWithPost( 'Override Tax Meta' );

			// The same option shape the edit_term handler writes:
			// `$term_meta[ $tt_id ] = <url>; update_option( 'wpdh_tax_meta', $term_meta );`
			wp( [
				'eval',
				`update_option( 'wpdh_tax_meta', array( ${ ttId } => '${ OVERRIDE_URL }' ) );`,
			] );

			expect(
				applyHeaderFilterForCategory( termId, 'default-header.jpg' )
			).toBe( OVERRIDE_URL );
		} );

		test( 'theme_mod_header_image filter passes through when wpdh_tax_meta is not an array', () => {
			const { termId } = createCategoryWithPost( 'Scalar Tax Meta' );

			/*
			 * The plugin only ever writes an array, but an older version or a
			 * manual update could leave a scalar behind. Indexing a string by
			 * the term taxonomy ID yields a single character, which would then
			 * be served as the header URL.
			 */
			wp( [
				'eval',
				"update_option( 'wpdh_tax_meta', str_repeat( 'x', 500 ) );",
			] );

			expect(
				applyHeaderFilterForCategory( termId, 'default-header.jpg' )
			).toBe( 'default-header.jpg' );
		} );
	} );
} );
