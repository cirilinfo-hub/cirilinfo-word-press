<?php
/**
 * Packages: data access + synchronisation with hidden WooCommerce products.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Packages {

	/** @var bool */
	private static $syncing = false;

	public static function init(): void {
		add_action( 'cirilinfo_package_saved', array( __CLASS__, 'sync_product' ) );
		add_action( 'wp_trash_post', array( __CLASS__, 'on_trash' ) );
		add_action( 'untrashed_post', array( __CLASS__, 'on_untrash' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'on_delete' ) );
		add_action( 'template_redirect', array( __CLASS__, 'block_product_pages' ) );
		add_filter( 'woocommerce_product_is_visible', array( __CLASS__, 'hide_in_catalog' ), 10, 2 );
	}

	/** Normalised package record. */
	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || 'ci_package' !== $post->post_type ) {
			return null;
		}
		$m       = static fn( string $k ) => get_post_meta( $id, $k, true );
		$billing = (string) ( $m( '_ci_billing' ) ?: 'one_time' );
		$price   = (float) $m( '_ci_price' );
		$sale    = (float) $m( '_ci_sale_price' );
		$unit    = Pricing::unit( $price, $sale );
		$active  = '' === $m( '_ci_active' ) ? 1 : (int) $m( '_ci_active' );
		$lines   = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $m( '_ci_features' ) ) ) ) );
		return array(
			'id'          => $id,
			'title'       => $post->post_title,
			'description' => $post->post_content,
			'status'      => $post->post_status,
			'service_id'  => (int) $m( '_ci_service_id' ),
			'tier'        => (string) ( $m( '_ci_tier' ) ?: 'basic' ),
			'billing'     => isset( Pricing::BILLING[ $billing ] ) ? $billing : 'one_time',
			'price'       => $price,
			'sale'        => $sale,
			'unit'        => $unit,
			'has_sale'    => $unit < $price,
			'note'        => (string) $m( '_ci_price_note' ),
			'revisions'   => (string) $m( '_ci_revisions' ),
			'delivery'    => (string) $m( '_ci_delivery' ),
			'support'     => (string) $m( '_ci_support' ),
			'features'    => $lines,
			'featured'    => (bool) $m( '_ci_featured' ),
			'active'      => (bool) $active,
			'product_id'  => (int) $m( '_ci_product_id' ),
			'purchasable' => 'publish' === $post->post_status && $active && 'custom' !== $billing && $unit > 0,
		);
	}

	/** @return array[] */
	public static function for_service( int $service_id, bool $only_active = true ): array {
		$ids = get_posts( array(
			'post_type'   => 'ci_package',
			'post_status' => 'publish',
			'numberposts' => 30,
			'fields'      => 'ids',
			'orderby'     => 'menu_order',
			'order'       => 'ASC',
			'meta_query'  => array( array( 'key' => '_ci_service_id', 'value' => $service_id, 'type' => 'NUMERIC' ) ), // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		$out   = array();
		$order = array_flip( array_keys( Post_Types::tiers() ) );
		foreach ( $ids as $id ) {
			$p = self::get( (int) $id );
			if ( $p && ( ! $only_active || $p['active'] ) ) {
				$out[] = $p;
			}
		}
		usort( $out, static fn( $a, $b ) => ( $order[ $a['tier'] ] ?? 9 ) <=> ( $order[ $b['tier'] ] ?? 9 ) );
		return $out;
	}

	public static function addons( int $service_id ): array {
		$a = get_post_meta( $service_id, '_ci_addons', true );
		return is_array( $a ) ? array_values( $a ) : array();
	}

	/** Display price string like "₹9,999" or "₹4,999/month". */
	public static function price_label( array $p ): string {
		if ( 'custom' === $p['billing'] ) {
			return __( 'Custom quote', 'cirilinfo' );
		}
		return Pricing::money( $p['unit'] ) . esc_html( $p['note'] );
	}

	/* ---------------------------------------------- Product synchronisation */

	public static function sync_product( int $package_id ): void {
		if ( self::$syncing || ! class_exists( 'WC_Product_Simple' ) ) {
			return;
		}
		$pkg = self::get( $package_id );
		if ( ! $pkg ) {
			return;
		}
		self::$syncing = true;
		try {
			$product = $pkg['product_id'] ? wc_get_product( $pkg['product_id'] ) : null;
			if ( ! $product instanceof \WC_Product_Simple ) {
				$product = new \WC_Product_Simple();
			}
			$service = $pkg['service_id'] ? get_the_title( $pkg['service_id'] ) : '';
			$product->set_name( $service ? sprintf( '%s – %s', $service, Post_Types::tiers()[ $pkg['tier'] ] ?? $pkg['tier'] ) : $pkg['title'] );
			$product->set_status( $pkg['purchasable'] ? 'publish' : 'private' );
			$product->set_catalog_visibility( 'hidden' );
			$product->set_virtual( true );
			$product->set_sold_individually( true );
			$product->set_manage_stock( false );
			$product->set_tax_status( 'taxable' );
			$product->set_tax_class( '' );
			$product->set_regular_price( (string) $pkg['unit'] );
			$product->set_price( (string) $pkg['unit'] );
			$product->set_downloadable( false );
			$product->update_meta_data( '_ci_package_id', $package_id );
			$product_id = $product->save();
			update_post_meta( $package_id, '_ci_product_id', $product_id );
		} finally {
			self::$syncing = false;
		}
	}

	public static function on_trash( $post_id ): void {
		if ( 'ci_package' !== get_post_type( $post_id ) ) {
			return;
		}
		$pid = (int) get_post_meta( $post_id, '_ci_product_id', true );
		if ( $pid && get_post( $pid ) ) {
			wp_update_post( array( 'ID' => $pid, 'post_status' => 'private' ) );
		}
	}

	public static function on_untrash( $post_id ): void {
		if ( 'ci_package' === get_post_type( $post_id ) ) {
			self::sync_product( (int) $post_id );
		}
	}

	public static function on_delete( $post_id ): void {
		if ( 'ci_package' !== get_post_type( $post_id ) ) {
			return;
		}
		$pid = (int) get_post_meta( $post_id, '_ci_product_id', true );
		if ( $pid && get_post( $pid ) ) {
			wp_delete_post( $pid, true );
		}
	}

	/** Package products must only be bought through the service page flow. */
	public static function block_product_pages(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$pkg_id = (int) get_post_meta( get_queried_object_id(), '_ci_package_id', true );
		if ( ! $pkg_id ) {
			return;
		}
		$pkg = self::get( $pkg_id );
		wp_safe_redirect( $pkg && $pkg['service_id'] ? get_permalink( $pkg['service_id'] ) : home_url( '/services/' ), 301 );
		exit;
	}

	public static function hide_in_catalog( $visible, $product_id ) {
		return get_post_meta( $product_id, '_ci_package_id', true ) ? false : $visible;
	}
}
