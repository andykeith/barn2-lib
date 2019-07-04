<?php

namespace Barn2\Lib\License\EDD;

use Barn2\Lib\Registerable;
use Barn2\Lib\Licensable_Plugin;
use Barn2\Lib\Admin\Plugin_Admin;
use Barn2\Lib\License\License_Controller;

if ( ! \class_exists( __NAMESPACE__ . '\Plugin_License_Controller' ) ) {

	/**
	 *
	 * @author    Barn2 Media <info@barn2.co.uk>
	 * @license   GPL-3.0
	 * @copyright Barn2 Media Ltd
	 */
	class Plugin_License_Controller implements Registerable, License_Controller {

		private $plugin;
		private $license;
		private $updater;
		private $admin;
		private $license_admin;

		/**
		 * Creates a new Plugin_License_Controller.
		 *
		 * @param Licensable_Plugin $plugin The plugin to setup licensing and updates for.
		 * @param string $legacy_option_prefix The DB option prefix for plugins using the old license system. Ignore if this is a new plugin.
		 */
		public function __construct( Licensable_Plugin $plugin, $legacy_option_prefix = '' ) {
			$this->plugin = $plugin;

			$this->license		 = new Plugin_License( $this->plugin->get_item_id(), $legacy_option_prefix );
			$this->updater		 = new Plugin_Updater( $this->plugin, $this->license->get_license_key() );
			$this->admin		 = new Plugin_Admin( $this->plugin );
			$this->license_admin = new Plugin_License_Admin( $this->plugin, $this->license );
		}

		public function register() {
			$this->license->register();
			$this->updater->register();

			if ( \is_admin() || ( \defined( 'WP_CLI' ) && WP_CLI ) ) {
				$this->admin->register();
				$this->license_admin->register();
			}
		}

		public function setup() {
			$this->register();
		}

		public function get_license_key() {
			return $this->license->get_license_key();
		}

		public function exists() {
			return $this->license->exists();
		}

		public function is_active() {
			return $this->license->is_active();
		}

		public function is_valid() {
			return $this->license->is_valid();
		}

		public function get_license_setting_name() {
			return $this->license_admin->get_license_setting_name();
		}

		public function get_license_setting() {
			return $this->license_admin->get_license_setting();
		}

		public function get_license_override_setting() {
			return $this->license_admin->get_license_override_setting();
		}

		public function save_license_setting( $license_key ) {
			return $this->license_admin->save_license_setting( $license_key );
		}

	}

}