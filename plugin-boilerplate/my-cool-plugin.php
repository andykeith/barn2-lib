<?php
/**
 * The main plugin file for [name].
 *
 * This file is included during the WordPress bootstrap process if the plugin is active.
 *
 * @wordpress-plugin
 * Plugin Name:       [name]
 * Plugin URI:
 * Description:
 * Version:           0.1
 * Author:            Barn2 Media
 * Author URI:        https://barn2.co.uk
 * Text Domain:       [domain]
 * Domain Path:       /languages
 *
 * WC requires at least: ??
 * WC tested up to: ??
 *
 * Copyright:		  Barn2 Media Ltd
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 */
// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The main plugin class for [name].
 *
 * Implemented as a singleton.
 *
 * @package   [name]
 * @author    Barn2 Media <info@barn2.co.uk>
 * @license   GPL-3.0
 * @copyright Barn2 Media Ltd
 */
final class My_Cool_Plugin_Class {

	const NAME	 = '[name]';
	const VERSION	 = '0.1';
	const FILE	 = __FILE__;

	/**
	 * Our plugin license
	 */
	private $license;
	/**
	 * The singleton instance
	 */
	private static $_instance = null;

	public function __construct() {
		$this->define_constants();
		$this->includes();
		add_action( 'plugins_loaded', array( $this, 'init_hooks' ) );

		// Create plugin license & updater
		$this->license = new Barn2_Plugin_License( self::FILE, self::NAME, self::VERSION, 'wcol' );
	}

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	private function define_constants() {
		if ( ! defined( 'ABC_INCLUDES_DIR' ) ) {
			define( 'ABC_INCLUDES_DIR', plugin_dir_path( self::FILE ) . 'includes/' );
		}
		if ( ! defined( 'ABC_PLUGIN_BASENAME' ) ) {
			define( 'ABC_PLUGIN_BASENAME', plugin_basename( self::FILE ) );
		}
	}

	private function includes() {
		// License
		require_once ABC_INCLUDES_DIR . 'license/class-b2-plugin-license.php';

		// Core
		require_once ABC_INCLUDES_DIR . 'lib/class-html-data-table.php';
		require_once ABC_INCLUDES_DIR . 'class-wc-order-listing-shortcode.php';
	}

	public function init_hooks() {
		// Don't continue if WooCommerce isn't installed & active
		//if ( ! WCPT_Util::is_wc_active() ) {
		//	return;
		//}

		add_action( 'init', array( $this, 'init' ) );
	}

	public function init() {
		$this->load_textdomain();

		if ( is_admin() ) {

		}

		// Initialise plugin if valid
		if ( $this->license->is_valid() ) {

			if ( $this->is_front_end() ) {

			}
		}
	}

	private function is_front_end() {
		return ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	private function load_textdomain() {
		load_plugin_textdomain( '[domain]', false, dirname( ABC_PLUGIN_BASENAME ) . '/languages' );
	}

}
// end My_Cool_Plugin_Class
// Load the plugin
My_Cool_Plugin_Class::instance();
