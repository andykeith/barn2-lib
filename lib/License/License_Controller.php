<?php

namespace Barn2\Lib\License;

if ( ! interface_exists( __NAMESPACE__ . '\License_Controller' ) ) {

	interface License_Controller extends License_Summary, License_Settings {

	}

}
