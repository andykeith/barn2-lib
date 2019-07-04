<?php
// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

spl_autoload_register( function( $class ) {

	$namespace	 = 'Barn2\\Lib\\';
	$lib_path	 = __DIR__;

	// Bail if the class is not in our namespace.
	if ( 0 !== strpos( $class, $namespace ) ) {
		return;
	}

	// Remove the namespace.
	$class = str_replace( $namespace, '', $class );

	// Build the filename.
	$file = realpath( $lib_path ) . DIRECTORY_SEPARATOR . str_replace( '\\', DIRECTORY_SEPARATOR, $class ) . '.php';

	// If the file exists for the class name, load it.
	if ( file_exists( $file ) ) {
		include_once( $file );
	}
} );
