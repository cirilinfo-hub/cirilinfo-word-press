<?php
/**
 * Gateway-agnostic payment ledger driven by WooCommerce payment events.
 *
 * The official gateway plugins (Razorpay, Cashfree, PayU...) verify signatures,
 * receive webhooks and call $order->payment_complete(). This ledger records the
 * outcome, de-duplicates it, flags double payments and powers the reports.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Transactions {

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'ci_transactions';
	}

	public static function create_table(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = self::table();
		dbDelta( "CREATE TABLE {$table} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  order_id bigint(20) unsigned NOT NULL,
  type varchar(12) NOT NULL DEFAULT 'payment',
  status varchar(16) NOT NULL DEFAULT 'pending',
  gateway varchar(64) NOT NULL DEFAULT '',
  method_title varchar(128) NOT NULL DEFAULT '',
  txn_ref varchar(191) NOT NULL,
  amount decimal(12,2) NOT NULL DEFAULT 0.00,
  currency varchar(8) NOT NULL DEFAULT 'INR',
  note text NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uniq_txn (order_id,type,txn_ref),
  KEY created_at (created_at),
  KEY status (status),
  KEY order_id (order_id)
) {$charset};" );
	}

	public static function init(): void {
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'on_payment_complete' ), 10, 2 );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'on_paid_status' ), 10, 1 );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'on_paid_status' ), 10, 1 );
		add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'on_failed' ), 10, 1 );
		add_action( 'woocommerce_order_status_on-hold', array( __CLASS__, 'on_pending' ), 10, 1 );
		add_action( 'woocommerce_order_status_pending', array( __CLASS__, 'on_pending' ), 10, 1 );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'on_cancelled' ), 10, 1 );
		add_action( 'woocommerce_order_refunded', array( __CLASS__, 'on_refund' ), 10, 2 );
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_ci_payments_export', array( __CLASS__, 'export' ) );
	}

	/* --------------------------------------------------------- Recording */

	/** @return bool True when a new row was inserted. */
	public static function record( \WC_Order $order, string $type, string $status, string $ref, float $amount, string $note = '' ): bool {
		global $wpdb;
		$table = self::table();
		$res   = $wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO {$table} (order_id,type,status,gateway,method_title,txn_ref,amount,currency,note,created_at) VALUES (%d,%s,%s,%s,%s,%s,%f,%s,%s,%s)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$order->get_id(),
			$type,
			$status,
			substr( $order->get_payment_method(), 0, 64 ),
			substr( wp_strip_all_tags( $order->get_payment_method_title() ), 0, 128 ),
			substr( $ref, 0, 191 ),
			$amount,
			$order->get_currency(),
			$note,
			gmdate( 'Y-m-d H:i:s' )
		) );
		return (bool) $res;
	}

	private static function success_ref( \WC_Order $order, string $txn = '' ): string {
		return $order->get_transaction_id() ?: ( $txn ?: 'order-' . $order->get_id() );
	}

	public static function on_payment_complete( $order_id, $txn = '' ): void {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			self::record_success( $order, self::success_ref( $order, (string) $txn ) );
		}
	}

	public static function on_paid_status( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order && $order->get_total() > 0 ) {
			self::record_success( $order, self::success_ref( $order ) );
		}
	}

	private static function record_success( \WC_Order $order, string $ref ): void {
		global $wpdb;
		$table = self::table();
		$other = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE order_id = %d AND type = 'payment' AND status = 'success' AND txn_ref <> %s", $order->get_id(), $ref ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		if ( $other > 0 ) {
			// A second, different payment reference for an already paid order.
			if ( self::record( $order, 'payment', 'duplicate', $ref, (float) $order->get_total(), 'Second payment reference for an already paid order – review and refund if needed.' ) ) {
				Mailer::notify_admin( sprintf( 'Possible duplicate payment on order #%s', $order->get_order_number() ), '<p>Reference <code>' . esc_html( $ref ) . '</code> was received for an order that is already paid. Please verify in the payment gateway dashboard and refund if required.</p>', $order->get_edit_order_url() );
			}
			return;
		}
		if ( self::record( $order, 'payment', 'success', $ref, (float) $order->get_total() ) ) {
			do_action( 'cirilinfo_payment_success', $order, $ref );
		}
	}

	public static function on_failed( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		if ( self::record( $order, 'payment', 'failed', 'failed-' . $order->get_id() . '-' . time(), (float) $order->get_total(), 'Payment failed or was declined.' ) ) {
			Mailer::notify_admin( sprintf( 'Payment failed for order #%s', $order->get_order_number() ), '<p>Customer: ' . esc_html( $order->get_billing_email() ) . '<br>Amount: ' . esc_html( wp_strip_all_tags( wc_price( $order->get_total() ) ) ) . '</p><p>The customer can retry payment from their dashboard.</p>', $order->get_edit_order_url() );
			$cid = (int) $order->get_customer_id();
			Notifications::add( $cid, sprintf( 'Payment failed for order #%s. You can retry from My Orders.', $order->get_order_number() ), wc_get_endpoint_url( 'orders', '', wc_get_page_permalink( 'myaccount' ) ) );
		}
	}

	public static function on_pending( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order && ! $order->is_paid() && $order->get_total() > 0 ) {
			self::record( $order, 'payment', 'pending', 'pending-' . $order->get_id(), (float) $order->get_total(), 'Awaiting payment confirmation.' );
		}
	}

	public static function on_cancelled( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( $order && ! $order->is_paid() ) {
			self::record( $order, 'payment', 'cancelled', 'cancelled-' . $order->get_id(), (float) $order->get_total(), 'Order cancelled before payment.' );
		}
	}

	public static function on_refund( $order_id, $refund_id ): void {
		$order  = wc_get_order( $order_id );
		$refund = wc_get_order( $refund_id );
		if ( $order && $refund ) {
			self::record( $order, 'refund', 'refunded', 'refund-' . $refund_id, (float) $refund->get_amount(), (string) $refund->get_reason() );
		}
	}

	/* ----------------------------------------------------------- Queries */

	public static function filters_from_request(): array {
		// phpcs:disable WordPress.Security.NonceVerification -- read-only admin filters.
		$date = static function ( string $key ): string {
			$v = isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : '';
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
		};
		return array(
			'status' => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'search' => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'from'   => $date( 'from' ),
			'to'     => $date( 'to' ),
		);
		// phpcs:enable
	}

	private static function where( array $f ): array {
		global $wpdb;
		$w = array( '1=1' );
		$a = array();
		if ( $f['status'] ) {
			$w[] = 'status = %s';
			$a[] = $f['status'];
		}
		if ( $f['search'] ) {
			$w[] = '(txn_ref LIKE %s OR order_id = %d OR gateway LIKE %s)';
			$a[] = '%' . $wpdb->esc_like( $f['search'] ) . '%';
			$a[] = absint( $f['search'] );
			$a[] = '%' . $wpdb->esc_like( $f['search'] ) . '%';
		}
		if ( $f['from'] ) {
			$w[] = 'created_at >= %s';
			$a[] = get_gmt_from_date( $f['from'] . ' 00:00:00' );
		}
		if ( $f['to'] ) {
			$w[] = 'created_at <= %s';
			$a[] = get_gmt_from_date( $f['to'] . ' 23:59:59' );
		}
		return array( implode( ' AND ', $w ), $a );
	}

	public static function query( array $f, int $limit = 25, int $offset = 0 ): array {
		global $wpdb;
		$table = self::table();
		list( $where, $args ) = self::where( $f );
		$args[] = $limit;
		$args[] = $offset;
		return (array) $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d", $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL,WordPress.DB.PreparedSQLPlaceholders
	}

	public static function count( array $f ): int {
		global $wpdb;
		$table = self::table();
		list( $where, $args ) = self::where( $f );
		$sql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
		return (int) $wpdb->get_var( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	public static function summary( array $f ): array {
		global $wpdb;
		$table = self::table();
		list( $where, $args ) = self::where( $f );
		$sql  = "SELECT status, type, COUNT(*) c, COALESCE(SUM(amount),0) s FROM {$table} WHERE {$where} GROUP BY status, type";
		$rows = $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out  = array( 'gross' => 0.0, 'refunds' => 0.0, 'failed' => 0, 'pending' => 0, 'duplicate' => 0, 'count' => 0 );
		foreach ( (array) $rows as $r ) {
			if ( 'payment' === $r->type && 'success' === $r->status ) {
				$out['gross'] += (float) $r->s;
				$out['count']  += (int) $r->c;
			} elseif ( 'refund' === $r->type ) {
				$out['refunds'] += (float) $r->s;
			} elseif ( isset( $out[ $r->status ] ) ) {
				$out[ $r->status ] += (int) $r->c;
			}
		}
		$out['net'] = $out['gross'] - $out['refunds'];
		return $out;
	}

	public static function by_gateway( array $f ): array {
		global $wpdb;
		$table = self::table();
		list( $where, $args ) = self::where( $f );
		$sql = "SELECT gateway, COUNT(*) c, COALESCE(SUM(amount),0) s FROM {$table} WHERE {$where} AND type = 'payment' AND status = 'success' GROUP BY gateway ORDER BY s DESC";
		return (array) $wpdb->get_results( $args ? $wpdb->prepare( $sql, $args ) : $sql ); // phpcs:ignore WordPress.DB.PreparedSQL
	}

	/** Net revenue per day (site timezone) for the last N days, oldest first. */
	public static function daily_totals( int $days = 30 ): array {
		global $wpdb;
		$table = self::table();
		$since = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT created_at, type, status, amount FROM {$table} WHERE created_at >= %s AND ((type='payment' AND status='success') OR type='refund')", $since ) ); // phpcs:ignore WordPress.DB.PreparedSQL
		$out   = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$out[ wp_date( 'Y-m-d', time() - $i * DAY_IN_SECONDS ) ] = 0.0;
		}
		foreach ( (array) $rows as $r ) {
			$day = get_date_from_gmt( $r->created_at, 'Y-m-d' );
			if ( isset( $out[ $day ] ) ) {
				$out[ $day ] += 'refund' === $r->type ? -(float) $r->amount : (float) $r->amount;
			}
		}
		return $out;
	}

	public static function recent( int $n = 8 ): array {
		return self::query( array( 'status' => '', 'search' => '', 'from' => '', 'to' => '' ), $n, 0 );
	}

	/* -------------------------------------------------------------- Admin */

	public static function menu(): void {
		add_submenu_page( 'cirilinfo', __( 'Payments', 'cirilinfo' ), __( 'Payments', 'cirilinfo' ), 'manage_cirilinfo', 'cirilinfo-payments', array( __CLASS__, 'render' ) );
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_cirilinfo' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ) );
		}
		$f     = self::filters_from_request();
		$paged = max( 1, absint( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		$per   = 25;
		$rows  = self::query( $f, $per, ( $paged - 1 ) * $per );
		$total = self::count( $f );
		$sum   = self::summary( $f );

		echo '<div class="wrap ci-admin"><h1>' . esc_html__( 'Payments & transactions', 'cirilinfo' ) . '</h1>';
		echo '<div class="ci-cards">';
		foreach ( array(
			array( __( 'Gross collected', 'cirilinfo' ), Pricing::money( $sum['gross'], true ) ),
			array( __( 'Refunded', 'cirilinfo' ), Pricing::money( $sum['refunds'], true ) ),
			array( __( 'Net revenue', 'cirilinfo' ), Pricing::money( $sum['net'], true ) ),
			array( __( 'Failed', 'cirilinfo' ), (string) $sum['failed'] ),
			array( __( 'Pending', 'cirilinfo' ), (string) $sum['pending'] ),
			array( __( 'Duplicates', 'cirilinfo' ), (string) $sum['duplicate'] ),
		) as $c ) {
			echo '<div class="ci-card"><span>' . esc_html( $c[0] ) . '</span><strong>' . esc_html( $c[1] ) . '</strong></div>';
		}
		echo '</div>';

		echo '<form method="get" class="ci-filters"><input type="hidden" name="page" value="cirilinfo-payments">';
		echo '<select name="status"><option value="">' . esc_html__( 'All statuses', 'cirilinfo' ) . '</option>';
		foreach ( array( 'success', 'failed', 'pending', 'cancelled', 'refunded', 'duplicate' ) as $s ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $s ), selected( $f['status'], $s, false ), esc_html( ucfirst( $s ) ) );
		}
		echo '</select> <input type="date" name="from" value="' . esc_attr( $f['from'] ) . '"> <input type="date" name="to" value="' . esc_attr( $f['to'] ) . '"> <input type="search" name="s" placeholder="' . esc_attr__( 'Order # / reference / gateway', 'cirilinfo' ) . '" value="' . esc_attr( $f['search'] ) . '"> <button class="button">' . esc_html__( 'Filter', 'cirilinfo' ) . '</button> ';
		$export = wp_nonce_url( add_query_arg( array_merge( array( 'action' => 'ci_payments_export' ), array_filter( $f ) ), admin_url( 'admin-post.php' ) ), 'ci_payments_export' );
		echo '<a class="button" href="' . esc_url( $export ) . '">' . esc_html__( 'Export CSV', 'cirilinfo' ) . '</a></form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Order', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Gateway', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Reference', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Type', 'cirilinfo' ) . '</th><th>' . esc_html__( 'Status', 'cirilinfo' ) . '</th><th style="text-align:right">' . esc_html__( 'Amount', 'cirilinfo' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$order = wc_get_order( (int) $r->order_id );
			$link  = $order ? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $order->get_order_number() ) . '</a>' : '#' . (int) $r->order_id;
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td><td><span class="ci-badge ci-badge--%s">%s</span></td><td style="text-align:right">%s%s</td></tr>',
				esc_html( get_date_from_gmt( $r->created_at, 'd M Y H:i' ) ),
				$link, // phpcs:ignore WordPress.Security.EscapeOutput
				esc_html( $r->method_title ?: $r->gateway ),
				esc_html( $r->txn_ref ),
				esc_html( $r->type ),
				esc_attr( $r->status ),
				esc_html( $r->status ),
				'refund' === $r->type ? '−' : '',
				esc_html( Pricing::money( (float) $r->amount, true ) )
			);
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="7">' . esc_html__( 'No transactions found.', 'cirilinfo' ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<div class="tablenav"><div class="tablenav-pages">' . wp_kses_post( (string) paginate_links( array( 'base' => add_query_arg( 'paged', '%#%' ), 'format' => '', 'current' => $paged, 'total' => max( 1, (int) ceil( $total / $per ) ) ) ) ) . '</div></div>';

		$gw = self::by_gateway( $f );
		if ( $gw ) {
			echo '<h2>' . esc_html__( 'Collections by gateway', 'cirilinfo' ) . '</h2><table class="widefat striped" style="max-width:520px"><tbody>';
			foreach ( $gw as $g ) {
				printf( '<tr><td>%s</td><td>%d</td><td style="text-align:right">%s</td></tr>', esc_html( $g->gateway ?: '—' ), (int) $g->c, esc_html( Pricing::money( (float) $g->s, true ) ) );
			}
			echo '</tbody></table>';
		}
		echo '<p class="description">' . esc_html__( 'Refunds are issued from the WooCommerce order screen (Refund button) and appear here automatically. Webhooks from your payment gateway update the order, which updates this ledger.', 'cirilinfo' ) . '</p></div>';
	}

	public static function export(): void {
		if ( ! current_user_can( 'manage_cirilinfo' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'cirilinfo' ), 403 );
		}
		check_admin_referer( 'ci_payments_export' );
		$f    = self::filters_from_request();
		$rows = self::query( $f, 5000, 0 );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=transactions-' . gmdate( 'Ymd' ) . '.csv' );
		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		fputcsv( $out, array( 'date_utc', 'order_id', 'gateway', 'reference', 'type', 'status', 'amount', 'currency', 'note' ) );
		foreach ( $rows as $r ) {
			fputcsv( $out, array_map( array( 'CirilInfo\\Marketing', 'csv_safe' ), array( $r->created_at, $r->order_id, $r->method_title ?: $r->gateway, $r->txn_ref, $r->type, $r->status, $r->amount, $r->currency, $r->note ) ) );
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}
}
