<?php

namespace Barn2\Lib\License;

if ( ! \interface_exists( 'Barn2\Lib\License\License_Summary' ) ) {

	interface License_Summary {

		public function get_license_key();

		public function exists();

		public function is_active();

		public function is_valid();
	}

}