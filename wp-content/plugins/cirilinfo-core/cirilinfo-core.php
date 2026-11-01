<?php
/**
 * Plugin Name:       CirilInfo Core
 * Plugin URI:        https://cirilinfo.com
 * Description:       Business engine for the CirilInfo digital services marketplace: services, packages, WooCommerce checkout integration, customer dashboard, projects, tickets, enquiries, invoices, payment ledger, email, SEO and marketing tools.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            CirilInfo
 * License:           GPL-2.0-or-later
 * Text Domain:       cirilinfo
 * Domain Path:       /languages
 * WC requires at least: 8.0
 */

defined( 'ABSPATH' ) || exit;

define( 'CIRILINFO_VERSION', '1.0.0' );
define( 'CIRILINFO_FILE', __FILE__ );
define( 'CIRILINFO_DIR', plugin_dir_path( __FILE__ ) );
define( 'CIRILINFO_URL', plugin_dir_url( __FILE__ ) );

require_once CIRILINFO_DIR . 'includes/autoload.php';

register_activation_hook( __FILE__, array( 'CirilInfo\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CirilInfo\\Activator', 'deactivate' ) );

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', CIRILINFO_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', CIRILINFO_FILE, false );
		}
	}
);

add_action( 'plugins_loaded', array( 'CirilInfo\\Plugin', 'boot' ), 20 );
