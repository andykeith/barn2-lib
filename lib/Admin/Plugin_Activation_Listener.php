<?php

namespace Barn2\Lib\Admin;

if ( ! \interface_exists( 'Barn2\Lib\Admin\Plugin_Activation_Listener' ) ) {

	interface Plugin_Activation_Listener {

		public function register_activation_events();

		public function on_activate();

		public function on_deactivate();
	}

}
