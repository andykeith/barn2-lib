<?php

namespace Barn2\Lib;

if ( ! \class_exists( 'Barn2\Lib\Simple_Plugin' ) ) {

	/**
	 * Basic implementation of the Plugin interface which stores core data about a
	 * WordPress plugin (ID, version number, etc). Data is passed as an array on construction.
	 *
	 * @author    Barn2 Media <info@barn2.co.uk>
	 * @license   GPL-3.0
	 * @copyright Barn2 Media Ltd
	 */
	class Simple_Plugin implements Plugin {

		protected $data;
		private $basename = null;

		public function __construct( $data ) {
			$this->data = \array_merge( array(
				'name' => '',
				'version' => '',
				'file' => null,
				'is_woocommerce' => false,
				'documentation_path' => '',
				'settings_page_path' => ''
				), (array) $data );

			$this->data['documentation_path']	 = \ltrim( $this->data['documentation_path'], '/' );
			$this->data['settings_page_path']	 = \ltrim( $this->data['settings_page_path'], '/' );
		}

		/**
		 * Gets the name of this plugin.
		 *
		 * @return string The plugin name.
		 */
		public function get_name() {
			return $this->data['name'];
		}

		/**
		 * Gets the plugin version number (e.g. 1.3.2).
		 *
		 * @return string The version number.
		 */
		public function get_version() {
			return $this->data['version'];
		}

		/**
		 * Gets the path to the main plugin file.
		 *
		 * @return string The plugin file.
		 */
		public function get_plugin_file() {
			return $this->data['file'];
		}

		/**
		 * Gets the basename for this plugin used by WordPress (e.g. my-plugin/my-plugin.php).
		 *
		 * @return string The plugin basename.
		 */
		public function get_basename() {
			if ( null === $this->basename ) {
				$this->basename = ! empty( $this->data['file'] ) ? \plugin_basename( $this->data['file'] ) : '';
			}
			return $this->basename;
		}

		/**
		 * Gets the slug for this plugin (e.g. my-plugin).
		 *
		 * @return string The plugin slug.
		 */
		public function get_slug() {
			return ! empty( $this->data['file'] ) ? \basename( $this->data['file'], '.php' ) : '';
		}

		/**
		 * Is this plugin a WooCommerce addon?
		 *
		 * @return boolean true if WooCommerce.
		 */
		public function is_woocommerce() {
			return (bool) $this->data['is_woocommerce'];
		}

		/**
		 * Get the documentation URL for this plugin.
		 *
		 * @return string (URL)
		 */
		public function get_documentation_url() {
			return ! empty( $this->data['documentation_path'] ) ?
				Link_Util::format_store_url( $this->data['documentation_path'] ) :
				Link_Util::DOCUMENTATION_HOME_URL;
		}

		/**
		 * Get the settings page URL in the WordPress admin.
		 *
		 * @return string (URL)
		 */
		public function get_settings_page_url() {
			return ! empty( $this->data['settings_page_path'] ) ?
				\admin_url( $this->data['settings_page_path'] ) : '';
		}

	}

}