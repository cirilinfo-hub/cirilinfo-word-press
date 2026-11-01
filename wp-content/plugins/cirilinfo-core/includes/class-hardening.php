<?php
/**
 * Low-risk security hardening that does not change core files.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Hardening {

	public static function init(): void {
		add_filter( 'xmlrpc_enabled', '__return_false' );
		add_filter( 'rest_endpoints', array( __CLASS__, 'hide_user_endpoints' ) );
		add_action( 'template_redirect', array( __CLASS__, 'block_author_enumeration' ), 1 );
		add_filter( 'login_errors', array( __CLASS__, 'generic_login_error' ) );
		add_filter( 'the_generator', '__return_empty_string' );
		add_filter( 'wp_headers', array( __CLASS__, 'headers' ) );
	}

	/** Anonymous visitors must not list usernames. */
	public static function hide_user_endpoints( array $endpoints ): array {
		if ( ! is_user_logged_in() ) {
			unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
		}
		return $endpoints;
	}

	public static function block_author_enumeration(): void {
		if ( ! is_user_logged_in() && isset( $_GET['author'] ) && ! is_admin() ) { // phpcs:ignore WordPress.Security.NonceVerification
			wp_safe_redirect( home_url( '/' ), 301 );
			exit;
		}
	}

	/** Do not reveal whether the username or the password was wrong (keeps our verification notice). */
	public static function generic_login_error( string $error ): string {
		if ( false !== strpos( $error, 'ci_resend_verification' ) ) {
			return $error;
		}
		global $errors;
		if ( is_wp_error( $errors ) && ( $errors->get_error_code() === 'invalid_username' || $errors->get_error_code() === 'incorrect_password' ) ) {
			return __( '<strong>Error:</strong> Invalid login details.', 'cirilinfo' );
		}
		return $error;
	}

	public static function headers( array $h ): array {
		$h['X-Content-Type-Options'] = 'nosniff';
		$h['X-Frame-Options']        = 'SAMEORIGIN';
		$h['Referrer-Policy']        = 'strict-origin-when-cross-origin';
		$h['Permissions-Policy']     = 'camera=(), microphone=(), geolocation=()';
		return $h;
	}
}
