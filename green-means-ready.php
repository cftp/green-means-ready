<?php 

/*
Plugin Name: Green Means Ready
Plugin URI: https://github.com/cftp/green-means-ready
Description: Description
Network: true
Version: 0.1
Author: Code for the People Ltd
Author URI: http://codeforthepeople.com/
*/
 
/*  Copyright 2014 Code for the People Ltd

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*/


/**
 * 
 * 
 * @package 
 **/
class GreenMeansReady {

	/**
	 * A version integer.
	 *
	 * @var int
	 **/
	var $version;

	/**
	 * An array of HTTP debug information
	 *
	 * @var array
	 **/
	var $http_debug;

	/**
	 * Singleton stuff.
	 * 
	 * @access @static
	 * 
	 * @return GreenMeansReady object
	 */
	static public function init() {
		static $instance = false;

		if ( ! $instance )
			$instance = new GreenMeansReady;

		return $instance;

	}

	/**
	 * Class constructor
	 *
	 * @return null
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'action_admin_menu' ) );

		$this->version = 1;
		$this->http_debug = array();
	}

	// HOOKS
	// =====

	/**
	 * Hooks the WP action admin_init
	 *
	 * @action admin_init
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function action_admin_menu() {
		add_submenu_page( 'tools.php', __( 'Server Checks', 'green-means-ready' ), __( 'Server Checks', 'green-means-ready' ), 'manage_options', 'jhabsfdsjahbvkasdjhbck', array( $this, 'callback_server_checks' ) );
	}

	/**
	 * Hooks the WP action http_api_debug
	 *
	 * @action http_api_debug
	 * @param response, 'response', $class, $args, $url
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function action_http_api_debug( $response, $type, $class, $args, $url ) {
		$this->http_debug[] = array(
			'response' => $response,
			'type'     => $type,
			'class'    => $class,
			'args'     => $args,
			'url'      => $url,
		);
	}

	// CALLBACKS
	// =========

	/**
	 * 
	 *
	 * @param 
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function callback_server_checks() {
?>
<div class="wrap" id="cron-gui">
	<div id="icon-tools" class="icon32"><br /></div>
	<h2><?php _e( 'Server Checks', 'green-means-ready' ); ?></h2>

	<?php $this->http_checks(); ?>
	
</div>
<?php
	}

	// UTILITIES
	// =========

	/**
	 * 
	 *
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function http_checks() {
		$url = "http://google.com/";
		add_action( 'http_api_debug', array( $this, 'action_http_api_debug' ), 10, 5 );
		$response = wp_remote_get( $url );
		remove_action( 'http_api_debug', array( $this, 'action_http_api_debug' ) );

		// Perhaps something went wrong with the HTTP request
		if ( is_wp_error( $response ) ) {
			?>
			<p class="error"><?php printf( __( '<strong>ERROR<span> –</span></strong> HTTP: %s', 'green-means-ready' ), esc_html( $response->get_error_message() ) ); ?></p>
			<?php
			$this->http_info();
			return;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// If all is well, waste no time in telling the user
		if ( 200 == $status_code ) {
			?>
			<p class="error"><?php printf( __( '<STRONG>SUCCESS<span> –</span></STRONG> HTTP: The server was able to reach <kbd>%s</kbd>, which returned an HTTP <kbd>200</kbd> Status code', 'green-means-ready' ), esc_html( $url ) ); ?></p>
			<?php
			return;
		}

		// Some redirection?
		if ( in_array( $status_code, array( 301, 302 ) ) ) {
			$redirect_url = wp_remote_retrieve_header( $response, $header );
			?>
			<p class="unknown"><?php printf( __( '<STRONG>UNKNOWN<span> –</span></STRONG> HTTP: The server attempted to reach <kbd>%1$s</kbd>, and was returned a <kbd>%2$s</kbd> status code and a redirect to <kbd>%3$s</kbd>', 'green-means-ready' ), esc_html( $url ), esc_html( $status_code ), esc_html( $redirect_url ) ); ?></p>
			<?php
			return;
		}
	}

	/**
	 * 
	 *
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	public function http_info() {
		// Check for a proxy
		$proxy = new WP_HTTP_Proxy();
		$debug = $this->http_debug[ count( $this->http_debug ) -1 ];
		?>
			<ul class="info">
		<?php
		if ( $proxy->is_enabled() ) {
			?>
				<li><?php printf( __( 'A Proxy is configured for <kbd>%1$s:%2$s</kbd>', 'green-means-ready' ), esc_html( $proxy->host() ), esc_html( $proxy->port() ) ); ?></li>
			<?php
		}
		?>
		<li><?php printf( __( 'The HTTP Request class was <kbd>%s</kbd>', 'green-means-ready' ), esc_html( $debug[ 'class' ] ) ); ?></li>
			</ul>
		<?php
	}

	/**
	 * Returns the URL for for a file/dir within this plugin.
	 *
	 * @param  string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string URL
	 * @author John Blackbourn
	 **/
	protected function plugin_url( $file = '' ) {
		return $this->plugin( 'url', $file );
	}

	/**
	 * Returns the filesystem path for a file/dir within this plugin.
	 *
	 * @param  string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string Filesystem path
	 * @author John Blackbourn
	 **/
	protected function plugin_path( $file = '' ) {
		return $this->plugin( 'path', $file );
	}

	/**
	 * Returns a version number for the given plugin file.
	 *
	 * @param  string The path within this plugin, e.g. '/js/clever-fx.js'
	 * @return string Version
	 * @author John Blackbourn
	 **/
	protected function plugin_ver( $file ) {
		return filemtime( $this->plugin_path( $file ) );
	}

	/**
	 * Returns the current plugin's basename, eg. 'my_plugin/my_plugin.php'.
	 *
	 * @return string Basename
	 * @author John Blackbourn
	 **/
	protected function plugin_base() {
		return $this->plugin( 'base' );
	}

	/**
	 * Populates and returns the current plugin info.
	 *
	 * @author John Blackbourn
	 **/
	protected function plugin( $item, $file = '' ) {
		if ( ! isset( $this->plugin ) ) {
			$this->plugin = array(
				'url'  => plugin_dir_url( $this->file ),
				'path' => plugin_dir_path( $this->file ),
				'base' => plugin_basename( $this->file )
			);
		}
		return $this->plugin[ $item ] . ltrim( $file, '/' );
	}
}


// Initiate the singleton
GreenMeansReady::init();







