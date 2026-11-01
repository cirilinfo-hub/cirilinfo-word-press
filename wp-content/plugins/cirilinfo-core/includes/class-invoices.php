<?php
/**
 * GST tax invoices: sequential numbering, printable/PDF-ready HTML.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Invoices {

	public static function init(): void {
		add_action( 'cirilinfo_payment_success', array( __CLASS__, 'on_payment' ), 10, 2 );
		add_action( 'admin_post_ci_invoice', array( __CLASS__, 'render' ) );
		add_action( 'admin_post_nopriv_ci_invoice', array( __CLASS__, 'render' ) );
	}

	public static function url( \WC_Order $order ): string {
		return add_query_arg( array( 'action' => 'ci_invoice', 'order' => $order->get_id(), 'key' => $order->get_order_key() ), admin_url( 'admin-post.php' ) );
	}

	/** Atomically allocate INV-2026-00001 style numbers. */
	public static function ensure_number( \WC_Order $order ): string {
		$existing = (string) $order->get_meta( '_ci_invoice_number' );
		if ( '' !== $existing ) {
			return $existing;
		}
		global $wpdb;
		$year = wp_date( 'Y' );
		$key  = 'cirilinfo_invoice_seq_' . $year;
		add_option( $key, 0, '', false );
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = LAST_INSERT_ID(option_value + 1) WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$seq = (int) $wpdb->get_var( 'SELECT LAST_INSERT_ID()' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( $seq <= 0 ) {
			$seq = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		$number = sprintf( '%s-%s-%05d', Settings::get( 'invoice_prefix', 'INV' ), $year, $seq );
		$order->update_meta_data( '_ci_invoice_number', $number );
		$order->update_meta_data( '_ci_invoice_date', time() );
		$order->save();
		return $number;
	}

	public static function on_payment( \WC_Order $order, string $ref ): void {
		$number = self::ensure_number( $order );
		$name   = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		Mailer::send( 'payment_received', $order->get_billing_email(), array(
			'customer_name'  => $name ?: __( 'there', 'cirilinfo' ),
			'order_number'   => $order->get_order_number(),
			'amount'         => self::fmt( $order, (float) $order->get_total() ),
			'invoice_number' => $number,
			'invoice_url'    => self::url( $order ),
		) );
		Mailer::notify_admin(
			sprintf( 'Payment received – order #%s', $order->get_order_number() ),
			'<p>' . esc_html( self::fmt( $order, (float) $order->get_total() ) ) . ' via ' . esc_html( wp_strip_all_tags( $order->get_payment_method_title() ) ) . '<br>Ref: <code>' . esc_html( $ref ) . '</code><br>Customer: ' . esc_html( $order->get_billing_email() ) . '</p>',
			$order->get_edit_order_url()
		);
		Notifications::add( (int) $order->get_customer_id(), sprintf( 'Payment received for order #%s. Invoice %s is ready.', $order->get_order_number(), $number ), self::url( $order ) );
	}

	public static function fmt( \WC_Order $order, float $amount ): string {
		return 'INR' === $order->get_currency() ? Pricing::money( $amount, true ) : wp_strip_all_tags( wc_price( $amount, array( 'currency' => $order->get_currency() ) ) );
	}

	public static function can_view( \WC_Order $order ): bool {
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( $key && hash_equals( $order->get_order_key(), $key ) ) {
			return true;
		}
		if ( ! is_user_logged_in() ) {
			return false;
		}
		return current_user_can( 'manage_cirilinfo' ) || current_user_can( 'manage_woocommerce' ) || (int) $order->get_customer_id() === get_current_user_id();
	}

	public static function render(): void {
		$order = wc_get_order( absint( $_GET['order'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $order instanceof \WC_Order || ! self::can_view( $order ) ) {
			wp_die( esc_html__( 'Invoice not found or access denied.', 'cirilinfo' ), 403 );
		}
		if ( ! $order->is_paid() && ! $order->has_status( array( 'on-hold', 'refunded' ) ) ) {
			wp_die( esc_html__( 'An invoice is available once the order is placed and payment is initiated.', 'cirilinfo' ), 404 );
		}
		$number = self::ensure_number( $order );
		$date   = (int) $order->get_meta( '_ci_invoice_date' ) ?: time();

		$biz_state = strtoupper( (string) Settings::get( 'state_code', '' ) );
		$intra     = $biz_state && 'IN' === $order->get_billing_country() && strtoupper( $order->get_billing_state() ) === $biz_state;
		$status    = $order->is_paid() ? __( 'PAID', 'cirilinfo' ) : ( $order->has_status( 'refunded' ) ? __( 'REFUNDED', 'cirilinfo' ) : __( 'PAYMENT PENDING', 'cirilinfo' ) );
		$bill_to   = trim( $order->get_formatted_billing_address( '' ) );

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?php echo esc_html( $number ); ?> – <?php echo esc_html( Settings::get( 'company_name' ) ); ?></title>
<style>
*{box-sizing:border-box}body{font:14px/1.5 Arial,Helvetica,sans-serif;color:#1c2540;background:#eef0f7;margin:0;padding:24px}
.inv{max-width:820px;margin:0 auto;background:#fff;padding:40px;border-radius:12px}
.top{display:flex;justify-content:space-between;gap:24px;border-bottom:3px solid #5b3df5;padding-bottom:20px}
h1{margin:0;font-size:26px;color:#5b3df5}.muted{color:#6b7390}.right{text-align:right}
.badge{display:inline-block;padding:4px 12px;border-radius:99px;font-weight:700;font-size:12px;background:#e6f7ee;color:#0a7a3d}.badge.pending{background:#fff4e0;color:#a35a00}
table{width:100%;border-collapse:collapse;margin-top:24px}th{background:#f3f5fb;text-align:left;padding:10px;font-size:12px;text-transform:uppercase;letter-spacing:.04em}
td{padding:10px;border-bottom:1px solid #e5e8f2;vertical-align:top}.num{text-align:right;white-space:nowrap}
.tot td{border:0;padding:5px 10px}.grand td{font-size:16px;font-weight:700;border-top:2px solid #1c2540}
.meta{display:flex;gap:40px;margin-top:24px;flex-wrap:wrap}.meta div{min-width:200px}small{color:#6b7390}
.actions{max-width:820px;margin:0 auto 16px;text-align:right}.actions button{background:#5b3df5;color:#fff;border:0;padding:10px 18px;border-radius:8px;cursor:pointer;font-size:14px}
@media print{body{background:#fff;padding:0}.inv{box-shadow:none;padding:0}.actions{display:none}}
</style></head><body>
<div class="actions"><button onclick="window.print()"><?php esc_html_e( 'Print / Save as PDF', 'cirilinfo' ); ?></button></div>
<div class="inv">
	<div class="top">
		<div>
			<h1><?php echo esc_html( Settings::get( 'legal_name', 'CirilInfo' ) ); ?></h1>
			<div class="muted"><?php echo nl2br( esc_html( (string) Settings::get( 'address', '' ) ) ); ?><br>
			<?php echo esc_html( Settings::business_email() ); ?> <?php echo esc_html( (string) Settings::get( 'phone', '' ) ); ?><br>
			<?php if ( Settings::get( 'gstin' ) ) : ?><strong>GSTIN: <?php echo esc_html( Settings::get( 'gstin' ) ); ?></strong><?php endif; ?></div>
		</div>
		<div class="right">
			<div style="font-size:20px;font-weight:700"><?php esc_html_e( 'TAX INVOICE', 'cirilinfo' ); ?></div>
			<div><?php echo esc_html( $number ); ?></div>
			<div class="muted"><?php echo esc_html( wp_date( 'd M Y', $date ) ); ?></div>
			<div style="margin-top:8px"><span class="badge <?php echo $order->is_paid() ? '' : 'pending'; ?>"><?php echo esc_html( $status ); ?></span></div>
		</div>
	</div>
	<div class="meta">
		<div><strong><?php esc_html_e( 'Billed to', 'cirilinfo' ); ?></strong><br><?php echo wp_kses_post( $bill_to ); ?><br><?php echo esc_html( $order->get_billing_email() ); ?><br><?php echo esc_html( $order->get_billing_phone() ); ?>
			<?php if ( $order->get_meta( '_ci_gstin' ) ) : ?><br><strong>GSTIN: <?php echo esc_html( $order->get_meta( '_ci_gstin' ) ); ?></strong><?php endif; ?></div>
		<div><strong><?php esc_html_e( 'Order', 'cirilinfo' ); ?></strong><br>#<?php echo esc_html( $order->get_order_number() ); ?><br><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'd M Y' ) ); ?><br>
			<?php esc_html_e( 'Payment', 'cirilinfo' ); ?>: <?php echo esc_html( wp_strip_all_tags( $order->get_payment_method_title() ) ); ?>
			<?php if ( $order->get_transaction_id() ) : ?><br><small><?php esc_html_e( 'Txn ref', 'cirilinfo' ); ?>: <?php echo esc_html( $order->get_transaction_id() ); ?></small><?php endif; ?></div>
	</div>
	<table>
		<thead><tr><th><?php esc_html_e( 'Description', 'cirilinfo' ); ?></th><th class="num"><?php esc_html_e( 'Amount (excl. GST)', 'cirilinfo' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $order->get_items() as $item ) : ?>
			<tr><td><strong><?php echo esc_html( $item->get_name() ); ?></strong>
				<?php
				foreach ( $item->get_formatted_meta_data() as $meta ) {
					echo '<br><small>' . esc_html( wp_strip_all_tags( $meta->display_key ) ) . ': ' . esc_html( wp_strip_all_tags( $meta->display_value ) ) . '</small>';
				}
				?>
				<br><small>SAC 998314 (IT design &amp; development) / 998361 (advertising &amp; marketing)</small></td>
				<td class="num"><?php echo esc_html( self::fmt( $order, (float) $order->get_line_subtotal( $item, false ) ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<table class="tot" style="margin-top:8px"><tbody>
		<tr><td class="right muted"><?php esc_html_e( 'Subtotal', 'cirilinfo' ); ?></td><td class="num" style="width:160px"><?php echo esc_html( self::fmt( $order, (float) $order->get_subtotal() ) ); ?></td></tr>
		<?php if ( (float) $order->get_total_discount() > 0 ) : ?>
		<tr><td class="right muted"><?php esc_html_e( 'Discount', 'cirilinfo' ); ?></td><td class="num">−<?php echo esc_html( self::fmt( $order, (float) $order->get_total_discount() ) ); ?></td></tr>
		<?php endif; ?>
		<?php
		$tax_total = (float) $order->get_total_tax();
		if ( $tax_total > 0 ) {
			$rate  = '';
			foreach ( $order->get_taxes() as $t ) {
				$rate = $t->get_rate_percent() ? ' @ ' . rtrim( rtrim( number_format( (float) $t->get_rate_percent(), 2 ), '0' ), '.' ) . '%' : '';
			}
			foreach ( Pricing::gst_split( $tax_total, $intra ) as $label => $amt ) {
				printf( '<tr><td class="right muted">%s%s</td><td class="num">%s</td></tr>', esc_html( $label ), esc_html( $rate ? ( $intra ? ' @ ' . ( (float) trim( $rate, ' @%' ) / 2 ) . '%' : $rate ) : '' ), esc_html( self::fmt( $order, (float) $amt ) ) );
			}
		}
		?>
		<tr class="grand"><td class="right"><?php esc_html_e( 'Total', 'cirilinfo' ); ?></td><td class="num"><?php echo esc_html( self::fmt( $order, (float) $order->get_total() ) ); ?></td></tr>
	</tbody></table>
	<?php if ( Settings::get( 'bank_details' ) && ! $order->is_paid() ) : ?>
		<p><strong><?php esc_html_e( 'Bank transfer details', 'cirilinfo' ); ?></strong><br><?php echo nl2br( esc_html( (string) Settings::get( 'bank_details' ) ) ); ?></p>
	<?php endif; ?>
	<p class="muted" style="margin-top:32px;font-size:12px"><?php echo esc_html( (string) Settings::get( 'invoice_terms', '' ) ); ?></p>
</div></body></html>
		<?php
		exit;
	}
}
