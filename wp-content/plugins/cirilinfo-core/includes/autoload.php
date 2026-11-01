<?php
/**
 * Minimal autoloader: CirilInfo\Foo_Bar => includes/class-foo-bar.php
 *
 * @package CirilInfo
 */

defined( 'ABSPATH' ) || exit;

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'CirilInfo\\';
		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}
		$name = strtolower( str_replace( '_', '-', substr( $class, strlen( $prefix ) ) ) );
		$file = CIRILINFO_DIR . 'includes/class-' . $name . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);
