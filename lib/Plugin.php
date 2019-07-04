<?php

namespace Barn2\Lib;

if ( ! \interface_exists( 'Barn2\Lib\Plugin' ) ) {

	interface Plugin {

		public function get_name();

		public function get_version();

		public function get_plugin_file();

		public function get_basename();

		public function get_slug();

		public function is_woocommerce();

		public function get_settings_page_url();

		public function get_documentation_url();
	}

}
