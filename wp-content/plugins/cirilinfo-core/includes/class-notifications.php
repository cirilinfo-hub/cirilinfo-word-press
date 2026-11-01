<?php
/**
 * In-account notification inbox (stored in user meta, newest first, capped).
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Notifications {

	const META = '_ci_notifications';
	const CAP  = 40;

	public static function init(): void {
		add_action( 'admin_post_ci_notifications_read', array( __CLASS__, 'mark_read' ) );
	}

	public static function add( int $user_id, string $message, string $url = '' ): void {
		if ( $user_id <= 0 ) {
			return;
		}
		$list = get_user_meta( $user_id, self::META, true );
		$list = is_array( $list ) ? $list : array();
		array_unshift( $list, array( 'm' => wp_strip_all_tags( $message ), 'u' => esc_url_raw( $url ), 't' => time(), 'r' => 0 ) );
		update_user_meta( $user_id, self::META, array_slice( $list, 0, self::CAP ) );
	}

	public static function get( int $user_id, int $limit = 10 ): array {
		$list = get_user_meta( $user_id, self::META, true );
		return is_array( $list ) ? array_slice( $list, 0, $limit ) : array();
	}

	public static function unread( int $user_id ): int {
		$n = 0;
		foreach ( self::get( $user_id, self::CAP ) as $item ) {
			if ( empty( $item['r'] ) ) {
				++$n;
			}
		}
		return $n;
	}

	public static function mark_read(): void {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'Please log in.', 'cirilinfo' ), 403 );
		}
		check_admin_referer( 'ci_notifications_read' );
		$uid  = get_current_user_id();
		$list = self::get( $uid, self::CAP );
		foreach ( $list as &$item ) {
			$item['r'] = 1;
		}
		unset( $item );
		update_user_meta( $uid, self::META, $list );
		wp_safe_redirect( wp_get_referer() ?: home_url( '/' ) );
		exit;
	}
}
