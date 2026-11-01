<?php
/**
 * Pure pricing maths. No WordPress/WooCommerce dependencies so it is unit-testable.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Pricing {

	const BILLING = array(
		'one_time' => 'One-time',
		'monthly'  => 'Monthly',
		'yearly'   => 'Yearly',
		'custom'   => 'Custom quote',
	);

	/** Effective unit price: discounted price when valid, otherwise list price. */
	public static function unit( float $price, float $sale ): float {
		return ( $sale > 0 && $sale < $price ) ? round( $sale, 2 ) : round( max( 0, $price ), 2 );
	}

	/** Allowed purchase terms (number of billing periods) per billing model. */
	public static function terms( string $billing ): array {
		switch ( $billing ) {
			case 'monthly':
				return array( 1, 3, 6, 12 );
			case 'yearly':
				return array( 1, 2, 3 );
			default:
				return array( 1 );
		}
	}

	public static function clamp_term( string $billing, int $term ): int {
		$allowed = self::terms( $billing );
		return in_array( $term, $allowed, true ) ? $term : $allowed[0];
	}

	/**
	 * Line total.
	 *
	 * @param float $unit    Package unit price (per period).
	 * @param array $addons  Selected add-ons: [ ['price'=>float,'recurring'=>bool], ... ].
	 * @param int   $term    Number of billing periods.
	 */
	public static function line_total( float $unit, array $addons, int $term ): float {
		$term  = max( 1, $term );
		$total = $unit * $term;
		foreach ( $addons as $a ) {
			$p      = max( 0, (float) ( $a['price'] ?? 0 ) );
			$total += ! empty( $a['recurring'] ) ? $p * $term : $p;
		}
		return round( $total, 2 );
	}

	/** Split a net amount into GST and gross. */
	public static function gst( float $net, float $rate ): array {
		$tax = round( $net * max( 0, $rate ) / 100, 2 );
		return array(
			'net'   => round( $net, 2 ),
			'tax'   => $tax,
			'gross' => round( $net + $tax, 2 ),
		);
	}

	/** CGST/SGST (intra-state) or IGST (inter-state) breakup. */
	public static function gst_split( float $tax, bool $intra_state ): array {
		if ( $intra_state ) {
			$half = round( $tax / 2, 2 );
			return array( 'CGST' => $half, 'SGST' => round( $tax - $half, 2 ) );
		}
		return array( 'IGST' => round( $tax, 2 ) );
	}

	/** Indian digit grouping: 1234567.5 => 12,34,567.50 (decimals only when needed). */
	public static function indian( float $amount, bool $force_decimals = false ): string {
		$neg    = $amount < 0;
		$amount = abs( round( $amount, 2 ) );
		$int    = (string) (int) floor( $amount );
		$dec    = (int) round( ( $amount - floor( $amount ) ) * 100 );
		if ( strlen( $int ) > 3 ) {
			$last = substr( $int, -3 );
			$rest = substr( $int, 0, -3 );
			$rest = preg_replace( '/\B(?=(\d{2})+(?!\d))/', ',', $rest );
			$int  = $rest . ',' . $last;
		}
		$out = $int;
		if ( $dec > 0 || $force_decimals ) {
			$out .= '.' . str_pad( (string) $dec, 2, '0', STR_PAD_LEFT );
		}
		return ( $neg ? '-' : '' ) . $out;
	}

	public static function money( float $amount, bool $force_decimals = false ): string {
		return "\u{20B9}" . self::indian( $amount, $force_decimals );
	}

	public static function suffix( string $billing ): string {
		return array(
			'monthly' => '/month',
			'yearly'  => '/year',
		)[ $billing ] ?? '';
	}
}
