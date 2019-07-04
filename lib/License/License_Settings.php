<?php

namespace Barn2\Lib\License;

if ( ! interface_exists( 'Barn2\Lib\License\License_Settings' ) ) {

	interface License_Settings {

		public function get_license_setting_name();

		public function get_license_setting();

		public function get_license_override_setting();

		public function save_license_setting( $license_key );
	}

}
