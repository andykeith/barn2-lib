<?php

namespace Barn2\Lib;

if ( ! \interface_exists( 'Barn2\Lib\Registerable' ) ) {

	/**
	 * An object that can be registered with WordPress via the Plugin API (i.e. add_action and add_filter callbacks).
	 *
	 * @version 1.0
	 */
	interface Registerable {

		public function register();
	}

}
