<?php
/**
 * Roles, capabilities and access scoping for team members.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Roles {

	public static function content_caps(): array {
		$base = array( 'edit', 'edit_others', 'publish', 'read_private', 'delete', 'delete_private', 'delete_others', 'edit_private', 'edit_published', 'delete_published' );
		$out  = array();
		foreach ( $base as $b ) {
			$out[] = $b . '_ci_content_items';
		}
		return $out;
	}

	public static function ops_caps(): array {
		$base = array( 'edit', 'edit_others', 'publish', 'read_private', 'delete', 'delete_private', 'delete_others', 'edit_private', 'edit_published', 'delete_published' );
		$out  = array();
		foreach ( $base as $b ) {
			$out[] = $b . '_ci_ops';
		}
		return $out;
	}

	/** Install roles & grant caps (idempotent). */
	public static function install(): void {
		$all = array_merge( self::content_caps(), self::ops_caps(), array( 'manage_cirilinfo' ) );

		$admin = get_role( 'administrator' );
		if ( $admin ) {
			foreach ( $all as $cap ) {
				$admin->add_cap( $cap );
			}
		}

		remove_role( 'ci_manager' );
		add_role( 'ci_manager', 'CirilInfo Manager', array_merge(
			array(
				'read'                  => true,
				'upload_files'          => true,
				'edit_posts'            => true,
				'edit_published_posts'  => true,
				'edit_others_posts'     => true,
				'publish_posts'         => true,
				'read_private_posts'    => true,
				'moderate_comments'     => true,
				'list_users'            => true,
				'manage_categories'     => true,
				'edit_pages'            => true,
				'edit_published_pages'  => true,
				'edit_others_pages'     => true,
				'publish_pages'         => true,
				'manage_woocommerce'    => true,
				'view_woocommerce_reports' => true,
				'edit_shop_orders'      => true,
				'read_shop_order'       => true,
				'edit_others_shop_orders' => true,
				'publish_shop_orders'   => true,
				'read_private_shop_orders' => true,
				'edit_published_shop_orders' => true,
			),
			array_fill_keys( $all, true )
		) );

		remove_role( 'ci_team' );
		add_role( 'ci_team', 'CirilInfo Team Member', array(
			'read'                    => true,
			'upload_files'            => true,
			'edit_ci_ops'             => true,
			'edit_others_ci_ops'      => true,
			'edit_private_ci_ops'     => true,
			'edit_published_ci_ops'   => true,
			'read_private_ci_ops'     => true,
		) );
	}

	public static function uninstall_roles(): void {
		remove_role( 'ci_manager' );
		remove_role( 'ci_team' );
	}

	public static function init(): void {
		add_filter( 'map_meta_cap', array( __CLASS__, 'scope_team_caps' ), 10, 4 );
		add_action( 'pre_get_posts', array( __CLASS__, 'scope_team_queries' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade_roles' ) );
	}

	public static function maybe_upgrade_roles(): void {
		if ( get_option( 'cirilinfo_roles_version' ) !== CIRILINFO_VERSION ) {
			self::install();
			update_option( 'cirilinfo_roles_version', CIRILINFO_VERSION );
		}
	}

	public static function is_team_only( int $user_id = 0 ): bool {
		$user = $user_id ? get_userdata( $user_id ) : wp_get_current_user();
		return $user && in_array( 'ci_team', (array) $user->roles, true ) && ! user_can( $user, 'manage_cirilinfo' );
	}

	/** Team members may edit only projects assigned to them; never tickets/enquiries. */
	public static function scope_team_caps( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, array( 'edit_post', 'delete_post', 'read_post' ), true ) || empty( $args[0] ) ) {
			return $caps;
		}
		if ( ! self::is_team_only( $user_id ) ) {
			return $caps;
		}
		$post = get_post( (int) $args[0] );
		if ( ! $post ) {
			return $caps;
		}
		if ( 'delete_post' === $cap && in_array( $post->post_type, array( 'ci_project', 'ci_ticket', 'ci_enquiry' ), true ) ) {
			return array( 'do_not_allow' );
		}
		if ( 'ci_project' === $post->post_type ) {
			if ( (int) get_post_meta( $post->ID, '_ci_assigned_to', true ) !== $user_id ) {
				return array( 'do_not_allow' );
			}
		} elseif ( in_array( $post->post_type, array( 'ci_ticket', 'ci_enquiry' ), true ) ) {
			return array( 'do_not_allow' );
		}
		return $caps;
	}

	public static function scope_team_queries( \WP_Query $q ): void {
		if ( ! is_admin() || ! $q->is_main_query() || ! self::is_team_only() ) {
			return;
		}
		$type = $q->get( 'post_type' );
		if ( 'ci_project' === $type ) {
			$meta   = (array) $q->get( 'meta_query' );
			$meta[] = array( 'key' => '_ci_assigned_to', 'value' => get_current_user_id(), 'compare' => '=' );
			$q->set( 'meta_query', $meta );
		} elseif ( in_array( $type, array( 'ci_ticket', 'ci_enquiry' ), true ) ) {
			$q->set( 'post__in', array( 0 ) );
		}
	}
}
