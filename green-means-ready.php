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
		add_submenu_page( 'tools.php', __( 'Server Checks', 'green-means-ready' ), __( 'Server Checks', 'green-means-ready' ), 'manage_options', 'gmr-checks', array( $this, 'callback_server_checks' ) );
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

	<?php 
		$this->check_http(); 
		$this->check_cron_http(); 
	?>
	
</div>
<?php
	}

	// CHECKS
	// =========

	/**
	 * Check we can use the WP HTTP API
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function check_http() {
		$url = "http://google.com/";
		add_action( 'http_api_debug', array( $this, 'action_http_api_debug' ), 10, 5 );
		$response = wp_remote_get( $url );
		remove_action( 'http_api_debug', array( $this, 'action_http_api_debug' ) );

		$this->diagnose_http_response( $response, $url, __( 'HTTP' ) );
	}

	/**
	 * Check we can ping the Cron.
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function check_cron_http() {
		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );
		/**
		 * Filter the cron request arguments.
		 *
		 * @since 3.5.0
		 *
		 * @param array $cron_request_array {
		 *     An array of cron request URL arguments.
		 *
		 *     @type string $url  The cron request URL.
		 *     @type int    $key  The 22 digit GMT microtime.
		 *     @type array  $args {
		 *         An array of cron request arguments.
		 *
		 *         @type int  $timeout   The request timeout in seconds. Default .01 seconds.
		 *         @type bool $blocking  Whether to set blocking for the request. Default false.
		 *         @type bool $sslverify Whether to sslverify. Default true.
		 *     }
		 * }
		 */
		$cron_request = apply_filters( 'cron_request', array(
			'url'  => add_query_arg( 'doing_wp_cron', $doing_wp_cron, site_url( 'wp-cron.php' ) ),
			'key'  => $doing_wp_cron,
			'args' => array(
				'timeout'   => 0.01,
				'blocking'  => true, // Different from an actual Cron request, because we want the response
				/** This filter is documented in wp-includes/class-http.php */
				'sslverify' => apply_filters( 'https_local_ssl_verify', true )
			)
		) );

		add_action( 'http_api_debug', array( $this, 'action_http_api_debug' ), 10, 5 );
		$response = wp_remote_post( $cron_request['url'], $cron_request['args'] );
		remove_action( 'http_api_debug', array( $this, 'action_http_api_debug' ) );
		$this->diagnose_http_response( $response, $cron_request['url'], __( 'Cron', 'green-means-ready' ) );
	}


	// UTILITIES
	// =========

	/**
	 * 
	 *
	 * @param $response
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function diagnose_http_response( $response, $url, $label ) {

		// Perhaps something went wrong with the HTTP request
		if ( is_wp_error( $response ) ) {
			?>
			<p class="error"><?php printf( __( '<strong>ERROR<span> –</span></strong> %1$s: <kbd>%2$s</kbd>', 'green-means-ready' ), esc_html( $label ), esc_html( $response->get_error_message() ) ); ?></p>
			<?php
			$this->http_info();
			return;
		}

		$debug = $this->http_debug[ count( $this->http_debug ) -1 ];
		$status_code = wp_remote_retrieve_response_code( $response );

		// If all is well, waste no time in telling the user
		if ( 200 == $status_code ) {
			?>
			<p class="error"><?php printf( __( '<STRONG>SUCCESS<span> –</span></STRONG> %1$s: The server was able to %2$s <kbd>%3$s</kbd>, which returned an HTTP <kbd>200</kbd> Status code', 'green-means-ready' ), esc_html( $label ), esc_html( $debug['args']['method'] ), esc_html( $url ) ); ?></p>
			<?php
			return;
		}

		// Some redirection?
		if ( in_array( $status_code, array( 301, 302 ) ) ) {
			$redirect_url = wp_remote_retrieve_header( $response, 'Location' );
			?>
			<p class="unknown"><?php printf( __( '<STRONG>UNKNOWN<span> –</span></STRONG> %1$s: The server attempted to %2$s <kbd>%3$s</kbd>, and was returned a <kbd>%3$s</kbd> status code and a redirect to <kbd>%4$s</kbd>', 'green-means-ready' ), esc_html( $label ), esc_html( $debug['args']['method'] ), esc_html( $url ), esc_html( $status_code ), esc_html( $redirect_url ) ); ?></p>
			<?php
			$this->http_info();
			return;
		}
		echo "Erk";
	}

	/**
	 * 
	 *
	 *
	 * @return void
	 * @author Simon Wheatley
	 **/
	protected function http_info() {
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







