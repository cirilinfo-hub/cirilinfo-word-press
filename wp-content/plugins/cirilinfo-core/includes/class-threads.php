<?php
/**
 * Conversation threads (project messages, ticket replies) stored as comments
 * of a private comment type, so they never appear in the public comment feeds.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Threads {

	const TYPE = 'ci_msg';

	public static function init(): void {
		// Keep thread messages out of public feeds and counts.
		add_filter( 'comments_clauses', array( __CLASS__, 'hide_from_default_queries' ), 10, 2 );
	}

	public static function hide_from_default_queries( array $clauses, \WP_Comment_Query $q ): array {
		global $wpdb;
		$type = $q->query_vars['type'] ?? '';
		$in   = $q->query_vars['type__in'] ?? array();
		if ( self::TYPE === $type || in_array( self::TYPE, (array) $in, true ) ) {
			return $clauses;
		}
		$clauses['where'] .= $wpdb->prepare( " AND {$wpdb->comments}.comment_type <> %s", self::TYPE );
		return $clauses;
	}

	/** @return int|false Comment ID. */
	public static function add( int $post_id, int $user_id, string $text, bool $is_staff ) {
		$text = trim( wp_kses( $text, array() ) );
		if ( '' === $text ) {
			return false;
		}
		$user = $user_id ? get_userdata( $user_id ) : null;
		$id   = wp_insert_comment( array(
			'comment_post_ID'      => $post_id,
			'comment_author'       => $user ? $user->display_name : 'CirilInfo',
			'comment_author_email' => $user ? $user->user_email : '',
			'comment_content'      => $text,
			'comment_type'         => self::TYPE,
			'comment_approved'     => 1,
			'user_id'              => $user_id,
			'comment_meta'         => array( 'ci_staff' => $is_staff ? 1 : 0 ),
		) );
		return $id ?: false;
	}

	public static function get( int $post_id ): array {
		return get_comments( array(
			'post_id' => $post_id,
			'type'    => self::TYPE,
			'status'  => 'approve',
			'order'   => 'ASC',
			'orderby' => 'comment_date_gmt',
		) );
	}

	public static function render( int $post_id ): string {
		$items = self::get( $post_id );
		if ( ! $items ) {
			return '<p class="ci-muted">' . esc_html__( 'No messages yet.', 'cirilinfo' ) . '</p>';
		}
		$out = '<ul class="ci-thread">';
		foreach ( $items as $c ) {
			$staff = (bool) get_comment_meta( $c->comment_ID, 'ci_staff', true );
			$out  .= sprintf(
				'<li class="%1$s"><div class="ci-thread__meta"><strong>%2$s</strong> <span>%3$s</span> <time>%4$s</time></div><div class="ci-thread__body">%5$s</div></li>',
				$staff ? 'is-staff' : 'is-customer',
				esc_html( $c->comment_author ),
				esc_html( $staff ? __( '(CirilInfo team)', 'cirilinfo' ) : '' ),
				esc_html( mysql2date( 'd M Y, H:i', $c->comment_date ) ),
				wpautop( esc_html( $c->comment_content ) )
			);
		}
		return $out . '</ul>';
	}
}
