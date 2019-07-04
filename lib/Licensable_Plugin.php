<?php

namespace Barn2\Lib;

if ( ! \interface_exists( 'Barn2\Lib\Licensable_Plugin' ) ) {

	interface Licensable_Plugin extends Plugin {

		/**
		 * Get the item ID for this plugin.
		 *
		 * @var int
		 */
		public function get_item_id();

		/**
		 * Get the license settings page URL.
		 *
		 * @var string (URL)
		 */
		public function get_license_setting_url();
	}

}