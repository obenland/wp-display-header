<?php
/** wp-display-header.php
 * 
 * Plugin Name:	WP Display Header
 * Plugin URI:	http://www.obenlands.de/en/portfolio/wp-display-header/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-display-header
 * Description:	This plugin lets you specify a header image for each post individually from your default headers and custom headers.
 * Version:		1.2
 * Author:		Konstantin Obenland
 * Author URI:	http://www.obenlands.de/en/?utm_source=wordpress&utm_medium=plugin&utm_campaign=wp-display-header
 * Text Domain:	wp-display-header
 * Domain Path:	/lang
 * License:		GPLv2
 */


if ( ! class_exists('Obenland_Wp_Plugins') ) {
	require_once('obenland-wp-plugins.php');
}


register_activation_hook(__FILE__, array(
	'Obenland_Wp_Display_Header',
	'activation'
));


class Obenland_Wp_Display_Header extends Obenland_Wp_Plugins {

	/////////////////////////////////////////////////////////////////////////////
	// PROPERTIES, PROTECTED
	/////////////////////////////////////////////////////////////////////////////
	
	/**
	 * The plugins' text domain
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	protected
	 * @static
	 * 
	 * @var		string
	 */
	protected static $plugin_textdomain	=	'wp-display-header';
	
	
	/**
	 * The folder within the template directory where the headers sit
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.2 - 23.04.2011
	 * @access	protected
	 * 
	 * @var		string
	 */
	protected $image_folder;
  
	
	/////////////////////////////////////////////////////////////////////////////
	// METHODS, PUBLIC
	/////////////////////////////////////////////////////////////////////////////

	/**
	 * Constructor
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @return	Obenland_Wp_Display_Header
	 */
	public function __construct() {

		parent::__construct( array(
			'textdomain'		=>	self::$plugin_textdomain,
			'plugin_name'		=>	plugin_basename(__FILE__),
			'donate_link_id'	=>	'MWUA92KA2TL6Q'
		));
		
		
		$this->image_folder = get_option( 'wp-header-upload-folder', 'images/headers' );
		
		load_plugin_textdomain($this->textdomain , false, $this->textdomain . '/lang');
	
		
		add_action( 'theme_mod_header_image', array(
			&$this,
			'display_header'
		));
		
		add_action( 'add_meta_boxes', array(
			&$this,
			'add_meta_box'
		));
		
		add_action( 'save_post', array(
			&$this,
		 	'save_post'
		));
		
		add_action( 'admin_print_styles-post-new.php', array(
			&$this,
		 	'admin_print_styles'
		));		
		add_action( 'admin_print_styles-post.php', array(
			&$this,
		 	'admin_print_styles'
		));
		
		add_action( 'admin_init', array(
			&$this,
			'add_settings_field'
		));
	}

	
	/**
	 * Checks if the current theme supports custom header functionality and bails
	 * if it doesn't. The plugin will stay deactivated.
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * @static
	 *
	 * @return	void
	 */
	public static function activation() {
		load_plugin_textdomain(self::$plugin_textdomain , false, self::$plugin_textdomain . '/lang');
		
		if ( ! current_theme_supports('custom-header')  ) {
			_e( 'Your current theme does not support Custom Headers', self::$plugin_textdomain );
			exit;
		}
	}

	
	/**
	 * Returns the header url
	 * 
	 * Returns the default header when we are on the blog page, the header
	 * settings page or no specific header was defined for that post. Can be
	 * filtered!
	 *
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @param	string	$header_url	The header url as saved in the theme mods
	 * 
	 * @return	string	
	 */
	public function display_header( $header_url ) {
		global $post;
		
		// Filter the decision to display the default header 
		$show_default	=	apply_filters( 'wpdh_show_default_header', (	
			is_home() OR 
			! isset($post) OR 
			! get_post_meta($post->ID, '_wpdh_display_header', true)
		));
		
		if ( $show_default ){
			return $header_url;
		}
		return $this->get_active_post_header( $post->ID );
	}

  
	/**
	 * Adds the header post meta box
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @param	string	$post_type
	 * 
	 * @return	void
	 */
	public function add_meta_box( $post_type ) {

		add_meta_box(
			$this->textdomain,
			__('Header'),
			array(
				&$this,
				'display_meta_box'
			),
			$post_type,
			'normal',
			'high'
		);
	}
	
	
	/**
	 * Renders the CSS so the Header meta box looks nice :)
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @return	void
	 */
	public function admin_print_styles() {
?>
		<style type="text/css">
			#available-headers .default-header {
			    float: left;
			    margin: 0 20px 20px 0;
				}
			#side-sortables #available-headers .default-header {
				margin-right: 0;
				}
			#available-headers label {
			    cursor: pointer;
			    vertical-align: middle;
				}
				#available-headers label input {
				    margin-right: 10px;
					}
				#available-headers label img {
				    vertical-align: middle;
					}
			#available-headers h4 {
				margin: 1.5em 0;
				text-shadow: 0 1px 0 #FFFFFF;
				}
			#wpdh-random {
				background: url("<?php echo admin_url('images/ed-bg.gif'); ?>") repeat-x scroll left top #DFDFDF;
				border: 1px solid #DFDFDF;
				display: inline-block;
				height: 46px;
				width: 228px;
				text-align: center;
				vertical-align:middle;
				}
				#wpdh-random span {
					display: block;
					font-size: 1em;
					font-weight: bold;
					margin: 1.5em 0;
					text-shadow: 0 1px 0 #FFFFFF;
					}				
		</style>
<?php 
	}
	
	
	/**
	 * Renders the content of the post meta box
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @param	stdClass	$post
	 * 
	 * @return	void
	 */
	public function display_meta_box( $post ) {
		
		$headers	=	$this->get_headers();
	
		foreach ( array_keys($headers) as $header ) {
			foreach ( array('url', 'thumbnail_url') as $url ) {
				$headers[$header][$url] =  sprintf(
					$headers[$header][$url],
					get_template_directory_uri(),
					get_stylesheet_directory_uri()
				);
			}
		}
		
		$active		=	$this->get_active_post_header( $post->ID, true );
		
		wp_nonce_field( $this->textdomain, $this->textdomain . '-nonce' );
?>
		<div id="available-headers">
<?php
		foreach ( $headers as $header_key => $header ) {
			$header_url			=	$header['url'];
			$header_thumbnail	=	$header['thumbnail_url'];
			$header_desc		=	$header['description'];
?>
			<div class="default-header">
				<label>
					<input name="wp-display-header" type="radio" value="<?php echo esc_attr($header_url); ?>" <?php checked($header_url, $active); ?> />
					<img src="<?php echo esc_url($header_thumbnail); ?>" alt="<?php echo esc_attr($header_desc); ?>" title="<?php echo esc_attr($header_desc); ?>" />
				</label>
			</div>
<?php	} ?>
			<div class="default-header">
				<label>
					<input name="wp-display-header" type="radio" value="random" <?php checked( 'random', $active ); ?> />
					<span id="wpdh-random">
						<span><?php _e('Random'); ?></span>
					</span>
				</label>
			</div>
			<div class="clear"></div>
		</div>
<?php
	}
 
 
	/**
	 * Saves the selected header for this post
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @param	int		$post_ID
	 * 
	 * @return	int
	 */
	public function save_post( $post_ID ) {	
		
		if (
			( ! current_user_can('edit_post', $post_ID) ) OR
			( defined('DOING_AUTOSAVE') AND DOING_AUTOSAVE ) OR
			( ! isset($_POST[$this->textdomain]) ) OR
			( ! wp_verify_nonce($_POST[$this->textdomain. '-nonce'], $this->textdomain) )
		) {
			return $post_ID;
		}

		update_post_meta( $post_ID, '_wpdh_display_header', esc_attr($_POST[$this->textdomain]) );
		
		return $post_ID;
	}
	
	
	/**
	 * Registers the setting and the settings field if it does not already
	 * exist
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.2 - 23.04.2011
	 * @access	public
	 * @global	$wp_settings_fields
	 * 
	 * return	void
	 */
	public function add_settings_field() {
		global $wp_settings_fields;
		
		if ( ! isset($wp_settings_fields['media']['uploads']['wp-header-upload-folder']) ) {
			
			register_setting(
				'media',									// Option group
				'wp-header-upload-folder',					// Option name
				array(&$this, 'settings_field_validate')	// Sanitation callback
			);
			
			$title = __( 'Store header images in this template folder', $this->textdomain );
			
			add_settings_field(
				'wp-header-upload-folder',					// Id
				$title,										// Title
				array(&$this, 'settings_field_callback'),	// Callback
				'media',									// Page
				'uploads',									// Section
				array(										// Args
					'label_for'	=>	'wp-header-upload-folder'
				)
			);
		}
	}
	
	
	/**
	 * Displays the settings field HTML
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.2 - 23.04.2011
	 * @access	public
	 * 
	 * return	void
	 */
	public function settings_field_callback() {
	?>
		<input name="wp-header-upload-folder" type="text" id="wp-header-upload-folder" value="<?php echo esc_attr( $this->image_folder ); ?>" class="regular-text code" />
		<span class="description"><?php _e( 'Default is <code>images/headers</code>', $this->textdomain ) ; ?></span>
	<?php
	}
	
	
	/**
	 * Sanitizes the settings field input
	 * 
	 * To make the input usable across plugins the folder path will be saved
	 * without prepended or trailing slashes
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.2 - 23.04.2011
	 * @access	public
	 * 
	 * @param	string	$input
	 * 
	 * @return	string	The sanitized folder name
	 */
	public function settings_field_validate( $input ) {
		$input = trim( $input, '/' );
		
		if ( empty($input) ){
			add_settings_error(
				'wp-header-upload-folder',
				'empty-value',
				__('Header images should not be stored in the root directory of your theme. Please specify a folder and try again.', $this->textdomain)
			);
			return $this->image_folder;
		}
		
		if ( ! is_dir(trailingslashit(TEMPLATEPATH) . $input) ) {
			add_settings_error(
				'wp-header-upload-folder',
				'no-dir',
				__('The specified folder does not exist! Please create the folder, make it writable and try again.', $this->textdomain)
			);
			return $this->image_folder;
		}
		
		if ( ! is_writable(trailingslashit(TEMPLATEPATH) . $input) ) {
			add_settings_error(
				'wp-header-upload-folder',
				'not-writable',
				__('The specified folder is not writable! Please make it writable and try again.', $this->textdomain)
			);
			return $this->image_folder;
		}
		
		return $input;
	}
	
	
	/////////////////////////////////////////////////////////////////////////////
	// METHODS, PROTECTED
	/////////////////////////////////////////////////////////////////////////////
	
	/**
	 * Returns all registered headers
	 * 
	 * If there are uploaded headers via the WP Save Custom Header Plugin, they
	 * will be loaded, too.
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * @global	$wpdb
	 * @global	$_wp_default_headers
	 * 
	 * @return	array
	 */
	protected function get_headers() {
		// Get all uploaded header images
		$posts = get_posts(apply_filters( 'wpdh_get_header_posts', array(
			'numberposts'	=>	-1,
			'post_type'		=>	'attachment',
			'meta_key'		=>	'_header_image',
			'order'			=>	'ASC',
		)));
		$headers = array();

		foreach ( $posts as $post ) {
			$meta	=	get_post_meta( $post->ID, '_wp_attachment_metadata' );
			$meta	=	( 1 == count($meta) ) ? $meta[0] : $meta;
			
			if ( ! empty($meta) AND is_array($meta) ) {
				$meta['post_title']	=	$post->post_title;
				$headers[]			=	$meta;
			}
		}
		
		if ( ! empty($headers) ) {
			
			$bid = '';
			if ( is_multisite() ) {
				global $wpdb;
				$bid = trailingslashit( $wpdb->blogid );
			}
			
			$placeholder = '%s/' . trailingslashit( $this->image_folder ) . $bid;
	
			foreach ( $headers as $header ) {
		
				$pics[$header['file']] = array(
					// %s is a placeholder for the theme template directory URI
					'url'			=>	$placeholder . $header['file'],
					'thumbnail_url'	=>	$placeholder . $header['sizes']['header-thumbnail']['file'],
					'description'	=>	$header['post_title']
				);
			}
			register_default_headers( $pics );
		}
		
		global $_wp_default_headers;
		return apply_filters( 'wpdh_get_headers', $_wp_default_headers );
	}
	
	
	/**
	 * Determines the active headeer for the post and returns the url
	 * 
	 * The $raw variable is necessary so that the 'random' option stays
	 * selected in post edit screens 
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @param	int		$post_ID
	 * @param	boolean	$raw
	 * 
	 * @return	string
	 */
	protected function get_active_post_header( $post_ID, $raw = false ) {
		$active		=	get_post_meta( $post_ID, '_wpdh_display_header', true );

		if ( 'random' == $active AND ! $raw ){
			$headers	=	$this->get_headers();
			shuffle( $headers );
			$active	=	sprintf(
				$headers[0]['url'],
				get_template_directory_uri(),
				get_stylesheet_directory_uri()
			);
		}
		
		// If no header set yet, get default header
		if ( ! $active ) {
			$active	=	get_theme_mod( 'header_image' );
		}

		return apply_filters( 'wpdh_get_active_post_header', $active );
	}
	
} // End of class Obenland_Wp_Display_Header


/**
 * Instantiates the class if current theme supports Custom Headers
 * 
 * @author	Konstantin Obenland
 * @since	1.2 - 03.05.2011
 * 
 * @return	void
 */
function Obenland_wpdh_instantiate() {

	if ( current_theme_supports('custom-header') ){
		new Obenland_Wp_Display_Header;
	}
}
add_action( 'init', 'Obenland_wpdh_instantiate' );


/* End of file wp-display-header.php */
/* Location: ./wp-content/plugins/wp-display-header/wp-display-header.php */