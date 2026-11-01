<?php
/**
 * Plugin bootstrap / module loader.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	/** @var bool */
	private static $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		load_plugin_textdomain( 'cirilinfo', false, dirname( plugin_basename( CIRILINFO_FILE ) ) . '/languages' );

		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ) );

		// Modules that do not need WooCommerce.
		Post_Types::init();
		Meta_Boxes::init();
		Uploads::init();
		Threads::init();
		Mailer::init();
		Forms::init();
		Seo::init();
		Shortcodes::init();
		Marketing::init();
		Roles::init();
		Notifications::init();
		Admin::init();
		Settings::init();
		Projects::init();
		Hardening::init();

		// Modules bound to WooCommerce.
		if ( self::wc_active() ) {
			Packages::init();
			Cart::init();
			Checkout::init();
			Account::init();
			Transactions::init();
			Invoices::init();
		} else {
			add_action( 'admin_notices', array( __CLASS__, 'wc_missing_notice' ) );
		}
	}

	/** Re-run schema creation when the plugin version changes. */
	public static function maybe_upgrade(): void {
		if ( get_option( 'cirilinfo_db_version' ) !== CIRILINFO_VERSION ) {
			Transactions::create_table();
			Marketing::create_table();
			Uploads::ensure_dir();
			update_option( 'cirilinfo_db_version', CIRILINFO_VERSION );
			update_option( 'cirilinfo_flush_rewrite', 1 );
		}
	}

	public static function wc_active(): bool {
		return class_exists( 'WooCommerce' );
	}

	public static function wc_missing_notice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p><strong>CirilInfo Core:</strong> ' .
			esc_html__( 'WooCommerce is not active. Services, enquiries and content work, but cart, checkout, payments, invoices and the customer dashboard require WooCommerce.', 'cirilinfo' ) .
			'</p></div>';
	}
}
