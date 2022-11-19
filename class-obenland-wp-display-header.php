<?php
/**
 * Obenland_Wp_Display_Header file.
 *
 * @package wp-display-header
 */

/**
 * Class Obenland_Wp_Display_Header.
 */
class Obenland_Wp_Display_Header extends Obenland_Wp_Plugins_V5 {

	/**
	 * Constructor.
	 *
	 * @since 1.0 - 23.03.2011
	 */
	public function __construct() {
		parent::__construct(
			array(
				'textdomain'     => 'wp-display-header',
				'plugin_path'    => __DIR__ . '/wp-display-header.php',
				'donate_link_id' => 'MWUA92KA2TL6Q',
			)
		);

		load_plugin_textdomain( 'wp-display-header', false, 'wp-display-header/lang' );

		$this->hook( 'init' );
	}

	/**
	 * Hooks in all the hooks :)
	 *
	 * @since 1.5.3 - 24.02.2012
	 */
	public function init() {
		$this->hook( 'theme_mod_header_image' );
		$this->hook( 'theme_mod_header_image_data' );
		$this->hook( 'add_meta_boxes' );

		// Save info.
		$this->hook( 'save_post' );
		$this->hook( 'edit_term' );
		$this->hook( 'personal_options_update', 'update_user' );
		$this->hook( 'edit_user_profile_update', 'update_user' );

		// Styles.
		$this->hook( 'admin_init', 'register_scripts_styles', 9 ); // Set priority to 9, so they can easily be deregistered.
		$this->hook( 'admin_enqueue_scripts', 'admin_print_styles' );

		// Edit forms.
		foreach ( get_taxonomies( array( 'show_ui' => true ) ) as $_tax ) {
			$this->hook( "{$_tax}_edit_form", 'edit_form', 9 ); // Let's make us a bit more important than we are.
		}

		$this->hook( 'admin_init', 'add_settings_field' );
		$this->hook( 'show_user_profile', 'edit_form' );
		$this->hook( 'show_user_profile', 'edit_form' );
	}

	/**
	 * Returns the header url.
	 *
	 * Returns the default header when we are on the blog page, the header
	 * settings page or no specific header was defined for that post. Can be
	 * filtered!
	 *
	 * @since 1.0 - 23.03.2011
	 *
	 * @param string $header_url The header url as saved in the theme mods.
	 * @return string
	 */
	public function theme_mod_header_image( $header_url ) {
		if ( is_category() || is_tag() || is_tax() ) {
			$active_header = $this->get_active_tax_header();
		} elseif ( is_author() ) {
			$active_header = $this->get_active_author_header();
		} elseif ( is_singular() || ( is_home() && ! is_front_page() ) ) {
			$active_header = $this->get_active_post_header();
		}

		if ( isset( $active_header ) && $active_header ) {
			$header_url = $active_header;
		}

		return $header_url;
	}

	/**
	 * Returns the header image data.
	 *
	 * Returns the default header image data when we are on the blog page,
	 * the header settings page, or no specific header was defined for that post.
	 *
	 * @since 6 - 20.12.2017
	 *
	 * @param array $header_data The header image data.
	 * @return object Header image data.
	 */
	public function theme_mod_header_image_data( $header_data ) {
		$active_header = false;

		if ( is_category() || is_tag() || is_tax() ) {
			$active_header = $this->get_active_tax_header();
		} elseif ( is_author() ) {
			$active_header = $this->get_active_author_header();
		} elseif ( is_singular() || ( is_home() && ! is_front_page() ) ) {
			$active_header = $this->get_active_post_header();
		}

		if ( $active_header ) {
			$attachment_id = $this->attachment_id_from_url( $active_header );

			if ( 0 !== $attachment_id ) {
				$data        = wp_get_attachment_metadata( $attachment_id );
				$header_data = (object) array(
					'attachment_id' => $attachment_id,
					'url'           => $active_header,
					'thumbnail_url' => $active_header,
					'height'        => isset( $data['height'] ) ? $data['height'] : 0,
					'width'         => isset( $data['width'] ) ? $data['width'] : 0,
				);
			}
		}

		return $header_data;
	}

	/**
	 * Adds the header post meta box.
	 *
	 * @since 1.0 - 23.03.2011
	 *
	 * @param string $post_type Post type.
	 */
	public function add_meta_boxes( $post_type ) {
		add_meta_box(
			'wp-display-header',
			esc_html__( 'Header' ),
			array( $this, 'display_meta_box' ),
			$post_type,
			'normal',
			'high'
		);
	}

	/**
	 * Registers the stylesheet.
	 *
	 * The stylesheets can easily be deregistered be calling
	 * <code>wp_deregister_style( 'wp-display-header' );</code> on the
	 * admin_init hook.
	 *
	 * @since 1.5 - 22.01.2012
	 */
	public function register_scripts_styles() {
		$plugin_data = get_plugin_data( __FILE__, false, false );
		$suffix      = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_register_style(
			$this->textdomain,
			plugins_url( "/css/{$this->textdomain}{$suffix}.css", __FILE__ ),
			array(),
			$plugin_data['Version']
		);
	}

	/**
	 * Enqueues the CSS so the Header meta box looks nice :)
	 *
	 * @since 1.0 - 23.03.2011
	 *
	 * @param string $screen Name of the current admin screen.
	 */
	public function admin_print_styles( $screen ) {
		if ( in_array( $screen, array( 'post-new.php', 'post.php', 'term.php', 'edit-tags.php', 'profile.php', 'user-edit.php' ), true ) ) {
			wp_enqueue_style( $this->textdomain );
		}
	}

	/**
	 * Registers the setting and the settings field if it does not already
	 * exist.
	 *
	 * @since 1.0 - 22.01.2012
	 */
	public function add_settings_field() {
		add_settings_section(
			$this->textdomain,
			esc_html__( 'Header' ),
			array( $this, 'settings_section_callback' ),
			$this->textdomain
		);

		add_settings_field(
			$this->textdomain,
			esc_html__( 'Choose Header', 'wp-display-header' ),
			array( $this, 'header_selection_callback' ),
			$this->textdomain,
			$this->textdomain
		);
	}

	/**
	 * Adds a settings section to the category edit screen.
	 *
	 * @since 1.0 - 22.01.2012
	 */
	public function edit_form() {
		do_settings_sections( $this->textdomain );
	}

	/**
	 * Renders the content of the post meta box.
	 *
	 * @since 1.0 - 22.01.2012
	 *
	 * @param WP_Post $post Post object.
	 */
	public function display_meta_box( $post ) {
		$active = $this->get_active_post_header( $post->ID, true );
		$this->header_selection_form( $active );
	}

	/**
	 * Echos out a description at the top of the section (between heading and fields).
	 *
	 * @since 1.0 - 22.01.2012
	 */
	public function settings_section_callback() {
		if ( 'profile' === get_current_screen()->base ) {
			esc_html_e( 'Select a header image for the author page.', 'wp-display-header' );
		} elseif ( in_array( get_current_screen()->base, array( 'edit-tags', 'term' ), true ) ) {
			esc_html_e( 'Select a header image for the taxonomy archive page.', 'wp-display-header' );
		}
	}

	/**
	 * Displays the settings field HTML.
	 *
	 * @since 1.0 - 22.01.2012
	 *
	 * @global WP_Term $tag Tag term object.
	 */
	public function header_selection_callback() {
		$active = '';

		switch ( get_current_screen()->base ) {
			case 'profile':
				$active = get_user_meta( $GLOBALS['profileuser']->ID, $this->textdomain, true );
				break;

			case 'edit-tags':
			case 'term':
				global $tag;

				$active = get_option( 'wpdh_tax_meta', '' );
				if ( $active ) {
					$active = isset( $active[ $tag->term_taxonomy_id ] ) ? $active[ $tag->term_taxonomy_id ] : '';
				}
				break;
		}

		// If no header set yet, get default header.
		if ( ! $active ) {
			$active = get_theme_mod( 'header_image' );
		}

		$this->header_selection_form( $active );
	}

	/**
	 * Saves the selected header for this post.
	 *
	 * @since 1.0 - 23.03.2011
	 *
	 * @param int $post_ID Post ID.
	 * @return int Post ID
	 */
	public function save_post( $post_ID ) {
		if ( ( ! defined( 'DOING_AUTOSAVE' ) || ! DOING_AUTOSAVE ) &&
			isset( $_POST[ $this->textdomain ] ) &&
			wp_verify_nonce( $_POST[ "{$this->textdomain}-nonce" ], $this->textdomain ) //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		) {

			if ( isset( $_POST['wpdh-reset-header'] ) ) {
				delete_post_meta( $post_ID, '_wpdh_display_header' );

			} else {
				$non_url = in_array( $_POST[ $this->textdomain ], array( 'random', 'remove-header' ), true );
				$value   = $non_url ? $_POST[ $this->textdomain ] : esc_url_raw( wp_unslash( $_POST[ $this->textdomain ] ) ); //phpcs:ignore WordPress.Security.ValidatedSanitizedInput

				update_post_meta( $post_ID, '_wpdh_display_header', $value );
			}
		}

		return $post_ID;
	}

	/**
	 * Sanitizes the settings field input.
	 *
	 * @since 2.0.0 - 12.03.2012
	 *
	 * @param int    $term_id Term ID.
	 * @param string $tt_id   Taxonomy Term ID.
	 * @return int Term ID
	 */
	public function edit_term( $term_id, $tt_id ) {
		if ( ( ! defined( 'DOING_AUTOSAVE' ) || ! DOING_AUTOSAVE ) &&
			isset( $_POST[ $this->textdomain ] ) &&
			wp_verify_nonce( $_POST[ "{$this->textdomain}-nonce" ], $this->textdomain ) //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		) {
			$term_meta = get_option( 'wpdh_tax_meta', array() );

			if ( isset( $_POST['wpdh-reset-header'] ) ) {
				unset( $term_meta[ $tt_id ] );
			} else {
				$non_url             = in_array( $_POST[ $this->textdomain ], array( 'random', 'remove-header' ), true );
				$term_meta[ $tt_id ] = $non_url ? $_POST[ $this->textdomain ] : esc_url_raw( $_POST[ $this->textdomain ] );  //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			}

			update_option( 'wpdh_tax_meta', $term_meta );
		}

		return $term_id;
	}

	/**
	 * Sanitizes the settings field input.
	 *
	 * @since 2.0.0 - 12.03.2012
	 *
	 * @param int $user_id User ID.
	 * @return int User ID
	 */
	public function update_user( $user_id ) {
		if ( ( ! defined( 'DOING_AUTOSAVE' ) || ! DOING_AUTOSAVE ) &&
			isset( $_POST[ $this->textdomain ] ) &&
			wp_verify_nonce( $_POST[ "{$this->textdomain}-nonce" ], $this->textdomain ) //phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		) {

			if ( isset( $_POST['wpdh-reset-header'] ) ) {
				delete_user_meta( $user_id, $this->textdomain );
			} else {
				$non_url = in_array( $_POST[ $this->textdomain ], array( 'random', 'remove-header' ), true );
				$value   = $non_url ? $_POST[ $this->textdomain ] : esc_url_raw( $_POST[ $this->textdomain ] );  //phpcs:ignore WordPress.Security.ValidatedSanitizedInput

				update_user_meta( $user_id, $this->textdomain, $value );
			}
		}

		return $user_id;
	}

	/**
	 * Displays the settings field HTML.
	 *
	 * @since 1.0 - 22.01.2012
	 *
	 * @param string $active URL or slug of active header. Default: Empty string.
	 */
	protected function header_selection_form( $active = '' ) {
		$headers = $this->get_headers();

		if ( empty( $headers ) ) {
			printf(
			/* translators: Upload URL. */
				wp_kses_post( __( 'The are no headers available. Please <a href="%s">upload a header image</a>!', 'wp-display-header' ) ),
				esc_url( add_query_arg( array( 'page' => 'custom-header' ), admin_url( 'themes.php' ) ) )
			);

			return;
		}

		foreach ( array_keys( $headers ) as $header ) {
			foreach ( array( 'url', 'thumbnail_url' ) as $url ) {
				$headers[ $header ][ $url ] = sprintf(
					$headers[ $header ][ $url ],
					get_template_directory_uri(),
					get_stylesheet_directory_uri()
				);
			}
		}

		wp_nonce_field( 'wp-display-header', 'wp-display-header-nonce' );
		?>
		<div class="available-headers">
			<div class="random-header">
				<p>
					<label>
						<input name="wp-display-header" type="radio" value="random" <?php checked( 'random', $active ); ?> />
						<?php echo wp_kses_post( __( '<strong>Random:</strong> Show a different image on each page.', 'wp-display-header' ) ); ?>
					</label>
				</p>
				<p>
					<label>
						<input name="wp-display-header" type="radio" value="remove-header" <?php checked( 'remove-header', $active ); ?> />
						<?php echo wp_kses_post( __( '<strong>None:</strong> Show no header image.', 'wp-display-header' ) ); ?>
					</label>
				</p>
			</div>
			<?php
			foreach ( $headers as $header_key => $header ) :
				$header_url       = $header['url'];
				$header_thumbnail = $header['thumbnail_url'];
				$header_desc      = isset( $header['description'] ) ? $header['description'] : '';
				?>
				<div class="default-header">
					<label>
						<input name="wp-display-header" type="radio" value="<?php echo esc_attr( $header_url ); ?>" <?php checked( $header_url, $active ); ?> />
						<img width="230" src="<?php echo esc_url( $header_thumbnail ); ?>" alt="<?php echo esc_attr( $header_desc ); ?>" title="<?php echo esc_attr( $header_desc ); ?>"/>
					</label>
				</div>
			<?php endforeach; ?>
			<div class="clear"></div>

			<?php submit_button( esc_html__( 'Restore Original Header Image', 'wp-display-header' ), 'button', 'wpdh-reset-header', false ); ?>
			<span class="description"><?php esc_html_e( 'This will restore the original header image. You will not be able to restore any customizations.', 'wp-display-header' ); ?></span>
		</div>
		<?php
	}

	/**
	 * Returns all registered headers.
	 *
	 * If there are uploaded headers via the WP Save Custom Header Plugin, they
	 * will be loaded, too.
	 *
	 * @since  1.0 - 23.03.2011
	 *
	 * @global array $_wp_default_headers Default headers.
	 *
	 * @return array
	 */
	protected function get_headers() {
		global $_wp_default_headers;
		$headers = array_merge( (array) $_wp_default_headers, get_uploaded_header_images() );

		/**
		 * Filters the list of registered headers.
		 *
		 * @param array $headers List of registered headers.
		 */
		return (array) apply_filters( 'wpdh_get_headers', $headers );
	}

	/**
	 * Determines the active header for the post and returns the url.
	 *
	 * The $raw variable is necessary so that the 'random' option stays
	 * selected in post edit screens.
	 *
	 * @since 2.0.0 - 12.03.2012
	 *
	 * @param int     $post_id Post id. Default: Current post.
	 * @param boolean $raw     Whether to use WP's db value for a random header. Default: false.
	 * @return string
	 */
	protected function get_active_post_header( $post_id = 0, $raw = false ) {
		if ( ! $post_id && is_home() && ! is_front_page() ) {
			$post_id = (int) get_option( 'page_for_posts' );
		}

		if ( ! $post_id ) {
			$post_id = get_post()->ID;
		}

		$active = get_post_meta( $post_id, '_wpdh_display_header', true );

		/**
		 * Filters the active header for the current post.
		 *
		 * @param string $header  Active header.
		 * @param int    $post_ID Current post ID.
		 */
		return apply_filters( 'wpdh_get_active_post_header', $this->get_active_header( $active, $raw ), $post_id );
	}

	/**
	 * Determines the active header for the category and returns the url.
	 *
	 * The $raw variable is necessary so that the 'random' option stays
	 * selected in post edit screens.
	 *
	 * @since 2.0.0 - 12.03.2012
	 *
	 * @return string
	 */
	protected function get_active_tax_header() {
		$active = get_option( 'wpdh_tax_meta', false );

		if ( $active ) {
			$tt_id  = get_queried_object()->term_taxonomy_id;
			$active = isset( $active[ $tt_id ] ) ? $active[ $tt_id ] : '';
		}

		/**
		 * Filters the active header for the current taxonomy.
		 *
		 * @param string $header Active header.
		 * @param int    $tt_id  Term taxonomy ID.
		 */
		return apply_filters( 'wpdh_get_active_tax_header', $this->get_active_header( $active ), $tt_id );
	}

	/**
	 * Determines the active header for the author and returns the url.
	 *
	 * The $raw variable is necessary so that the 'random' option stays
	 * selected in post edit screens.
	 *
	 * @since 2.0.0 - 12.03.2012
	 *
	 * @return string
	 */
	protected function get_active_author_header() {
		$user_id = get_queried_object()->ID;
		$active  = get_user_meta( $user_id, $this->textdomain, true );

		/**
		 * Filters the active header for the current author.
		 *
		 * @param string $header  Active header.
		 * @param int    $user_id User ID of the current author.
		 */
		return apply_filters( 'wpdh_get_active_author_header', $this->get_active_header( $active ), $user_id );
	}

	/**
	 * Determines the active header for the post and returns the url.
	 *
	 * The $raw variable is necessary so that the 'random' option stays
	 * selected in post edit screens.
	 *
	 * @since 1.0 - 23.03.2011
	 *
	 * @param string  $header Header URL.
	 * @param boolean $raw    Whether to use WP's db value for a random header. Default: false.
	 * @return string
	 */
	protected function get_active_header( $header, $raw = false ) {
		if ( 'random' === $header && ! $raw ) {
			$headers = $this->get_headers();
			$header  = sprintf(
				$headers[ array_rand( $headers ) ]['url'],
				get_template_directory_uri(),
				get_stylesheet_directory_uri()
			);
		}

		/**
		 * Filters the active header URL.
		 *
		 * @param string $header Active header.
		 */
		return apply_filters( 'wpdh_get_active_header', $header );
	}

	/**
	 * Examines a URL and tries to determine the post ID it represents.
	 *
	 * @since 6 - 20.12.2017
	 *
	 * @global wpdb $wpdp WordPress Database object.
	 *
	 * @param string $image_url Image URL to check.
	 * @return int Post ID or 0 on failure.
	 */
	protected function attachment_id_from_url( $image_url ) {
		global $wpdb;

		$attachment_id = wp_cache_get( 'wpdh_attachment_id_' . $image_url, 'post' );

		if ( false === $attachment_id ) {
			//phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$attachment_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE guid=%s LIMIT 1;",
					$image_url
				)
			);

			wp_cache_set( 'wpdh_attachment_id_' . $image_url, $attachment_id, 'post' );
		}

		return absint( $attachment_id );
	}

	/**
	 * Checks if the current theme supports custom header functionality and bails
	 * if it does not. The plugin will stay deactivated.
	 *
	 * @since 1.0 - 23.03.2011
	 * @deprecated 7 - 12.11.2022
	 * @static
	 */
	public static function activation() {
		_deprecated_function( __FUNCTION__, '7', 'obenland_wp_display_header_activation' );

		obenland_wp_display_header_activation();
	}
}
