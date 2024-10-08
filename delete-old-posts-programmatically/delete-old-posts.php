<?php

/**
 * Plugin Name: Delete old posts automatically
 * Description: Keep your website clean by automatically deleting old posts and redirecting the deleted posts to similar ones.
 * Author:      WPMagic
 * Author URI:  https://wpmagic.cloud
 * License:     GPL3
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 * Version:     3.9.0
 * Text Domain: delete-old-posts
 *
 * @package headless-cms
 */
use DEL\OLD\Posts\Cls;
use DEL\OLD\Posts\Cls\Assets;
// If this file is accessed directory, then abort.
if ( !defined( 'WPINC' ) ) {
    die;
}
if ( function_exists( 'dop_fs' ) ) {
    dop_fs()->set_basename( false, __FILE__ );
} else {
    // DO NOT REMOVE THIS IF, IT IS ESSENTIAL FOR THE `function_exists` CALL ABOVE TO PROPERLY WORK.
    // ... Freemius integration snippet ...
    /*******************
     * freemius
     * *************** */
    if ( !function_exists( 'dop_fs' ) ) {
        // Create a helper function for easy SDK access.
        function dop_fs() {
            global $dop_fs;
            if ( !isset( $dop_fs ) ) {
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/freemius/start.php';
                $dop_fs = fs_dynamic_init( array(
                    'id'             => '8165',
                    'slug'           => 'delete-old-posts',
                    'type'           => 'plugin',
                    'public_key'     => 'pk_344033c43756e8fcfcd1e6a2c6b17',
                    'is_premium'     => false,
                    'premium_suffix' => 'Professional',
                    'has_addons'     => false,
                    'has_paid_plans' => true,
                    'menu'           => array(
                        'slug'    => 'delete-old-posts',
                        'support' => false,
                    ),
                    'is_live'        => true,
                ) );
            }
            return $dop_fs;
        }

        // Init Freemius.
        dop_fs();
        // Signal that SDK was initiated.
        do_action( 'dop_fs_loaded' );
    }
    // ... Plugin's main file logic ...
    /********************
     * Main Plugin Code
     ********************/
    // register plugin classes
    require_once plugin_dir_path( __FILE__ ) . 'inc/class-enqueue-assets.php';
    require_once plugin_dir_path( __FILE__ ) . 'inc/class-delete-old-posts.php';
    require_once plugin_dir_path( __FILE__ ) . 'inc/class-delete-old-posts-redirects.php';
    require_once plugin_dir_path( __FILE__ ) . 'inc/class-delete-old-posts-filters.php';
    /**
     * Starts the plugin by initializing the class
     * Set the plugin in motion.
     */
    add_action( 'plugins_loaded', 'deloldp_class_load' );
    function deloldp_class_load() {
        new Assets\Enqueue_Assets();
        new Cls\Delete_Old_Posts();
        new Cls\Delete_Old_Posts_Filters();
        new Cls\Delete_Old_Posts_Redirects();
    }

    /**
     * add plugin translations
     */
    add_action( 'init', 'delop_load_textdomain' );
    function delop_load_textdomain() {
        load_plugin_textdomain( 'delete-old-posts', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
    }

}