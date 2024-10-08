<?php
namespace DEL\OLD\Posts\Cls\Assets;

/**
 * Enqueue Assets
 */

class Enqueue_Assets {

	/**
	 * Constructor.
	 */
	public function __construct() {
		/**
		 * Actions.
		 */
		add_action( 'admin_enqueue_scripts', [ $this, 'deloldp_EnqueueAssets' ] );
		add_filter( 'style_loader_src', [ $this, 'deloldp_StyleandScriptSrcVerStrip' ], 10, 2 );
		add_filter( 'script_loader_src', [ $this, 'deloldp_StyleandScriptSrcVerStrip' ], 10, 2 );
	}

	/**
	 * Enqueue editor scripts & styles.
	 *
	 */
	public function deloldp_EnqueueAssets() {
		global $wp;

		/**
		 * check the page url
		 */
        $current_url = home_url($_SERVER['REQUEST_URI']);
		
		/**
		 * load assets only if is page of the plugin
		 */
		if( stristr( $current_url, 'page=delete-old-posts' ) !== false || stristr( $current_url, 'page=delete-old-posts-filters' ) !== false ) {
			$vers = $this->delop_get_plugin_version();
			wp_enqueue_style	( 'tailwind', plugin_dir_url( __FILE__ ) . '../assets/css/tailwind.css', false, $vers, 'all');
			wp_enqueue_script	( 'alpine', plugin_dir_url( __FILE__ ) . '../assets/js/alpine.min.js', [], $vers, true);
			wp_enqueue_script	( 'script', plugin_dir_url( __FILE__ ) . '../assets/js/deloldp_script.js', [], $vers, true);
		}
	}

	/**
	 * Strip WP Version in Stylesheets/Scripts
	*/
	function deloldp_StyleandScriptSrcVerStrip( $src, $handle ) {
		/**
		 * use it for external css or js
		 */
		if(stristr($src,'alpinejs')) $src = remove_query_arg( 'ver', $src );
		return $src;
	}

	function delop_get_plugin_version(){
		if ( is_admin() ) {
			if( ! function_exists('get_plugin_data') ){
				require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
			}
			$plugin_data = get_plugin_data( dirname(__DIR__, 1) . '/' . basename(dirname(__DIR__, 1)) . '.php' );
			
			if( isset($plugin_data['Version']) && $plugin_data['Version'] != '' ) return $plugin_data['Version'];
			else return date('d_m_Y_H');
		}
	}
}

// new Enqueue_Assets();
