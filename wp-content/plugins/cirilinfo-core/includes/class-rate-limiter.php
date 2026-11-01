<?php
/**
 * Transient based rate limiter for sensitive public operations.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Rate_Limiter {

	public static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0.0.0.0';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '0.0.0.0';
	}

	private static function key( string $action, string $subject ): string {
		return 'ci_rl_' . md5( $action . '|' . $subject );
	}

	/** Returns true when the caller is still within the limit (and counts the hit). */
	public static function hit( string $action, int $max, int $window, string $subject = '' ): bool {
		$subject = '' === $subject ? self::ip() : $subject;
		$key     = self::key( $action, $subject );
		$count   = (int) get_transient( $key );
		if ( $count >= $max ) {
			return false;
		}
		set_transient( $key, $count + 1, $window );
		return true;
	}

	public static function remaining( string $action, int $max, string $subject = '' ): int {
		$subject = '' === $subject ? self::ip() : $subject;
		return max( 0, $max - (int) get_transient( self::key( $action, $subject ) ) );
	}
}
