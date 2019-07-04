<?php

namespace Barn2\Lib\License;

if ( ! \interface_exists( 'Barn2\Lib\License\License' ) ) {

	interface License extends License_Summary {

		public function activate( $license_key );

		public function deactivate();

		public function refresh();

		public function override( $license_key, $status );

		public function is_expired();

		public function is_disabled();

		public function is_inactive();

		public function get_status();

		public function get_status_help_text();

		public function get_active_url();

		public function get_renewal_url( $apply_discount = true );

		public function get_setting_name();

		public function set_status( $status );

		public function set_license_key( $license_key );
	}

}
