<?php
/**
 * Activation / deactivation.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Activator {

	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( CIRILINFO_FILE ) );
			wp_die( esc_html__( 'CirilInfo Core requires PHP 8.1 or newer.', 'cirilinfo' ) );
		}
		Transactions::create_table();
		Marketing::create_table();
		Roles::install();
		Uploads::ensure_dir();
		add_option( Settings::OPTION, Settings::defaults(), '', false );
		update_option( 'cirilinfo_db_version', CIRILINFO_VERSION );
		update_option( 'cirilinfo_flush_rewrite', 1 );
		if ( ! wp_next_scheduled( 'ci_campaign_tick' ) ) {
			wp_schedule_event( time() + 300, 'ci_five_minutes', 'ci_campaign_tick' );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'ci_campaign_tick' );
		flush_rewrite_rules( false );
	}
}
