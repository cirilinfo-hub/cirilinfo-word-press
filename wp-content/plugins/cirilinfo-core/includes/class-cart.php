<?php
/**
 * Cart: server-authoritative pricing for packages, add-ons and terms.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Cart {

	public static function init(): void {
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'recalculate' ), 20 );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'order_item_meta' ), 10, 4 );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect_to_cart' ) );
		add_filter( 'woocommerce_cart_item_permalink', array( __CLASS__, 'item_permalink' ), 10, 2 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'check_cart' ) );
	}

	private static function package_for_product( int $product_id ): int {
		return (int) get_post_meta( $product_id, '_ci_package_id', true );
	}

	/** Pull and sanitise the posted selection. */
	private static function posted_selection( int $service_id, string $billing ): array {
		$addons_def = Packages::addons( $service_id );
		$sel        = array();
		if ( isset( $_POST['ci_addons'] ) && is_array( $_POST['ci_addons'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			foreach ( wp_unslash( $_POST['ci_addons'] ) as $idx ) { // phpcs:ignore WordPress.Security
				$i = absint( $idx );
				if ( isset( $addons_def[ $i ] ) ) {
					$sel[ $i ] = $i;
				}
			}
		}
		ksort( $sel );
		$term = Pricing::clamp_term( $billing, absint( $_POST['ci_term'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		return array( 'addons' => array_values( $sel ), 'term' => $term );
	}

	public static function validate( $passed, $product_id, $qty ) {
		$pkg_id = self::package_for_product( (int) $product_id );
		if ( ! $pkg_id ) {
			return $passed;
		}
		$nonce = isset( $_POST['_ci_cart_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_ci_cart_nonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! wp_verify_nonce( $nonce, 'ci_add_to_cart' ) ) {
			wc_add_notice( __( 'Your session expired. Please reload the page and choose your package again.', 'cirilinfo' ), 'error' );
			return false;
		}
		$pkg = Packages::get( $pkg_id );
		if ( ! $pkg || ! $pkg['purchasable'] ) {
			wc_add_notice( __( 'This package is not available for online purchase. Please request a quote.', 'cirilinfo' ), 'error' );
			return false;
		}
		return $passed;
	}

	public static function cart_item_data( $data, $product_id ) {
		$pkg_id = self::package_for_product( (int) $product_id );
		if ( ! $pkg_id ) {
			return $data;
		}
		$pkg = Packages::get( $pkg_id );
		if ( ! $pkg ) {
			return $data;
		}
		$sel                = self::posted_selection( $pkg['service_id'], $pkg['billing'] );
		$data['ci_pkg']     = $pkg_id;
		$data['ci_addons']  = $sel['addons'];
		$data['ci_term']    = $sel['term'];
		$data['ci_unique']  = md5( $pkg_id . '|' . implode( ',', $sel['addons'] ) . '|' . $sel['term'] );
		return $data;
	}

	/** Compute authoritative line price from DB (never from the browser). */
	public static function price_for( array $cart_item ): ?float {
		$pkg = Packages::get( (int) ( $cart_item['ci_pkg'] ?? 0 ) );
		if ( ! $pkg || ! $pkg['purchasable'] ) {
			return null;
		}
		$defs  = Packages::addons( $pkg['service_id'] );
		$addon = array();
		foreach ( (array) ( $cart_item['ci_addons'] ?? array() ) as $i ) {
			if ( isset( $defs[ (int) $i ] ) ) {
				$addon[] = array( 'price' => (float) $defs[ (int) $i ]['price'], 'recurring' => ! empty( $defs[ (int) $i ]['recurring'] ) );
			}
		}
		return Pricing::line_total( $pkg['unit'], $addon, Pricing::clamp_term( $pkg['billing'], (int) ( $cart_item['ci_term'] ?? 1 ) ) );
	}

	public static function recalculate( $cart ): void {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			if ( empty( $item['ci_pkg'] ) ) {
				continue;
			}
			$price = self::price_for( $item );
			if ( null !== $price ) {
				$item['data']->set_price( $price );
			}
		}
	}

	public static function check_cart(): void {
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( empty( $item['ci_pkg'] ) ) {
				continue;
			}
			if ( null === self::price_for( $item ) ) {
				WC()->cart->remove_cart_item( $key );
				wc_add_notice( sprintf( /* translators: %s: product name */ __( '"%s" is no longer available and was removed from your cart.', 'cirilinfo' ), $item['data']->get_name() ), 'error' );
			}
		}
	}

	/** Human readable lines shared by cart, checkout and order. */
	public static function describe( array $item ): array {
		$pkg = Packages::get( (int) ( $item['ci_pkg'] ?? 0 ) );
		if ( ! $pkg ) {
			return array();
		}
		$lines = array();
		$lines[ __( 'Package', 'cirilinfo' ) ] = Post_Types::tiers()[ $pkg['tier'] ] ?? $pkg['tier'];
		$term = Pricing::clamp_term( $pkg['billing'], (int) ( $item['ci_term'] ?? 1 ) );
		if ( 'one_time' !== $pkg['billing'] ) {
			$unit = 'monthly' === $pkg['billing'] ? _n( 'month', 'months', $term, 'cirilinfo' ) : _n( 'year', 'years', $term, 'cirilinfo' );
			$lines[ __( 'Billing period', 'cirilinfo' ) ] = sprintf( '%d %s × %s', $term, $unit, Pricing::money( $pkg['unit'] ) );
		}
		$defs   = Packages::addons( $pkg['service_id'] );
		$labels = array();
		foreach ( (array) ( $item['ci_addons'] ?? array() ) as $i ) {
			if ( isset( $defs[ (int) $i ] ) ) {
				$labels[] = sprintf( '%s (%s)', $defs[ (int) $i ]['label'], Pricing::money( (float) $defs[ (int) $i ]['price'] ) );
			}
		}
		if ( $labels ) {
			$lines[ __( 'Add-ons', 'cirilinfo' ) ] = implode( ', ', $labels );
		}
		if ( $pkg['delivery'] ) {
			$lines[ __( 'Delivery', 'cirilinfo' ) ] = $pkg['delivery'];
		}
		if ( $pkg['revisions'] ) {
			$lines[ __( 'Revisions', 'cirilinfo' ) ] = $pkg['revisions'];
		}
		return $lines;
	}

	public static function display_data( $item_data, $cart_item ) {
		if ( empty( $cart_item['ci_pkg'] ) ) {
			return $item_data;
		}
		foreach ( self::describe( $cart_item ) as $k => $v ) {
			$item_data[] = array( 'key' => $k, 'value' => $v );
		}
		return $item_data;
	}

	public static function order_item_meta( $item, $cart_item_key, $values, $order ): void {
		if ( empty( $values['ci_pkg'] ) ) {
			return;
		}
		$pkg = Packages::get( (int) $values['ci_pkg'] );
		$item->add_meta_data( '_ci_package_id', (int) $values['ci_pkg'], true );
		$item->add_meta_data( '_ci_service_id', $pkg ? $pkg['service_id'] : 0, true );
		$item->add_meta_data( '_ci_term', (int) ( $values['ci_term'] ?? 1 ), true );
		$item->add_meta_data( '_ci_addons', array_map( 'intval', (array) ( $values['ci_addons'] ?? array() ) ), true );
		foreach ( self::describe( $values ) as $k => $v ) {
			$item->add_meta_data( $k, $v, true );
		}
	}

	public static function redirect_to_cart( $url ) {
		return isset( $_POST['ci_term'] ) || isset( $_POST['_ci_cart_nonce'] ) ? wc_get_cart_url() : $url; // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function item_permalink( $permalink, $item ) {
		if ( empty( $item['ci_pkg'] ) ) {
			return $permalink;
		}
		$pkg = Packages::get( (int) $item['ci_pkg'] );
		return $pkg && $pkg['service_id'] ? get_permalink( $pkg['service_id'] ) : '';
	}
}
