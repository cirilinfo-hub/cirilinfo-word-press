<?php
/**
 * Dependency-free unit tests for CirilInfo\Pricing.  Run: php tests/pricing-test.php
 */
define( 'ABSPATH', __DIR__ . '/' );
require __DIR__ . '/../wp-content/plugins/cirilinfo-core/includes/class-pricing.php';

use CirilInfo\Pricing;

$fail = 0;
function check( string $name, $got, $want ): void {
	global $fail;
	if ( $got === $want ) {
		echo "ok   $name\n";
		return;
	}
	++$fail;
	echo "FAIL $name: got " . var_export( $got, true ) . ' want ' . var_export( $want, true ) . "\n";
}

check( 'unit uses sale when lower', Pricing::unit( 24999, 19999 ), 19999.0 );
check( 'unit ignores sale >= price', Pricing::unit( 24999, 30000 ), 24999.0 );
check( 'unit ignores zero sale', Pricing::unit( 9999, 0 ), 9999.0 );
check( 'one-time total with one-off add-on', Pricing::line_total( 9999, array( array( 'price' => 1500, 'recurring' => 0 ) ), 1 ), 11499.0 );
check( 'monthly 3x', Pricing::line_total( 4999, array(), 3 ), 14997.0 );
check( 'recurring add-on multiplies by term', Pricing::line_total( 4999, array( array( 'price' => 1500, 'recurring' => 1 ), array( 'price' => 2500, 'recurring' => 0 ) ), 3 ), 4999 * 3 + 1500 * 3 + 2500.0 );
check( 'negative add-on price ignored', Pricing::line_total( 1000, array( array( 'price' => -500, 'recurring' => 0 ) ), 1 ), 1000.0 );
check( 'term clamp invalid', Pricing::clamp_term( 'monthly', 5 ), 1 );
check( 'term clamp valid', Pricing::clamp_term( 'monthly', 12 ), 12 );
check( 'one_time only term 1', Pricing::clamp_term( 'one_time', 12 ), 1 );
check( 'yearly allows 3', Pricing::clamp_term( 'yearly', 3 ), 3 );
check( 'gst 18%', Pricing::gst( 24999, 18 ), array( 'net' => 24999.0, 'tax' => 4499.82, 'gross' => 29498.82 ) );
check( 'gst 0%', Pricing::gst( 1000, 0 )['tax'], 0.0 );
check( 'gst split intra', Pricing::gst_split( 4499.82, true ), array( 'CGST' => 2249.91, 'SGST' => 2249.91 ) );
check( 'gst split odd paisa', Pricing::gst_split( 0.01, true ), array( 'CGST' => 0.01, 'SGST' => 0.0 ) );
check( 'gst split inter', Pricing::gst_split( 4499.82, false ), array( 'IGST' => 4499.82 ) );
check( 'indian 1,234', Pricing::indian( 1234 ), '1,234' );
check( 'indian lakh', Pricing::indian( 123456 ), '1,23,456' );
check( 'indian crore', Pricing::indian( 12345678.5 ), '1,23,45,678.50' );
check( 'indian small', Pricing::indian( 999 ), '999' );
check( 'money symbol', Pricing::money( 49999 ), "\u{20B9}49,999" );
check( 'money forced decimals', Pricing::money( 100, true ), "\u{20B9}100.00" );
check( 'suffix monthly', Pricing::suffix( 'monthly' ), '/month' );
check( 'suffix one_time', Pricing::suffix( 'one_time' ), '' );

exit( $fail ? 1 : 0 );
