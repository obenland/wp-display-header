<?php 
/** obenland-wp-plugins.php
 * 
 * @author	Konstantin Obenland
 * @since	1.0
 */


class Obenland_Wp_Plugins {
	
	
	/////////////////////////////////////////////////////////////////////////////
	// PROPERTIES, PROTECTED
	/////////////////////////////////////////////////////////////////////////////
	
	/**
	 * The name of the calling plugin
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	protected
	 * @static
	 * 
	 * @var		string
	 */
	protected $plugin_name;
	
	
	/**
	 * The donate link for the plugin
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	protected
	 * @static
	 * 
	 * @var		string
	 */
	protected $donate_link;
	
	
	///////////////////////////////////////////////////////////////////////////
	// METHODS, PUBLIC
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * Constructor
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @param	string	$plugin_name
	 * @param	string	$donate_link_id
	 */
	public function __construct( $plugin_name = '', $donate_link_id = '' ) {
		
		$this->plugin_name	=	$plugin_name;
		$this->donate_link	=	add_query_arg(array(
			'cmd'				=>	'_s-xclick',
			'hosted_button_id'	=>	$donate_link_id
		), 'https://www.paypal.com/cgi-bin/webscr');
		
		add_action('plugin_row_meta', array(
			&$this,
			'plugin_meta_donate'
		), 10, 2);
	}
	
	/**
	 * 
	 * @author	Konstantin Obenland
	 * @since	1.0 - 23.03.2011
	 * @access	public
	 * 
	 * @param	array	$plugin_meta
	 * @param	string	$plugin_file
	 * 
	 * @return	string
	 */
	public function plugin_meta_donate( $plugin_meta, $plugin_file ) {
		
		if ( $this->plugin_name == $plugin_file ) {
			$plugin_meta[]	=	sprintf( '<a href="%1$s" target="_blank" title="%2$s">%2$s</a>', $this->donate_link, __('Donate') );
		}
		return $plugin_meta;
	}
} // End of class Obenland_Wp_Plugins


/* End of file obenland-wp-plugins.php */
/* Location: ./wp-content/plugins/wp-display-header/obenland-wp-plugins.php */