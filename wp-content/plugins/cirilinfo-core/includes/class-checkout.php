<?php
/**
 * Checkout: GST configuration, project requirements, GSTIN, marketing opt-in.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Checkout {

	const GSTIN_REGEX = '/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/';

	public static function init(): void {
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'fields' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'save_order_meta' ), 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_order_panel' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou_panel' ), 5 );
		add_filter( 'woocommerce_order_button_text', static fn() => __( 'Pay securely', 'cirilinfo' ) );
	}

	public static function fields( array $f ): array {
		$f['order']['ci_requirements'] = array(
			'type'        => 'textarea',
			'label'       => __( 'Project requirements', 'cirilinfo' ),
			'placeholder' => __( 'Tell us about your goals, pages/features, brand, deadlines… You can add more details and upload files from your dashboard after payment.', 'cirilinfo' ),
			'required'    => false,
			'class'       => array( 'form-row-wide' ),
			'priority'    => 10,
		);
		$f['order']['ci_website'] = array(
			'type'     => 'text',
			'label'    => __( 'Existing website (if any)', 'cirilinfo' ),
			'required' => false,
			'class'    => array( 'form-row-wide' ),
			'priority' => 20,
		);
		$f['order']['ci_gstin'] = array(
			'type'              => 'text',
			'label'             => __( 'GSTIN (optional – for a GST invoice in your company name)', 'cirilinfo' ),
			'required'          => false,
			'class'             => array( 'form-row-wide' ),
			'priority'          => 30,
			'custom_attributes' => array( 'maxlength' => 15, 'autocapitalize' => 'characters' ),
		);
		$f['order']['ci_marketing_optin'] = array(
			'type'     => 'checkbox',
			'label'    => __( 'Send me occasional offers and digital marketing tips', 'cirilinfo' ),
			'required' => false,
			'class'    => array( 'form-row-wide' ),
			'priority' => 40,
		);
		// Services are virtual: no shipping or order notes clutter.
		unset( $f['shipping'] );
		return $f;
	}

	public static function validate( array $data, \WP_Error $errors ): void {
		$gstin = strtoupper( trim( (string) ( $data['ci_gstin'] ?? '' ) ) );
		if ( '' !== $gstin && ! preg_match( self::GSTIN_REGEX, $gstin ) ) {
			$errors->add( 'ci_gstin', __( '<strong>GSTIN</strong> is not valid. It must be 15 characters, e.g. 29ABCDE1234F1Z5.', 'cirilinfo' ) );
		}
	}

	public static function save_order_meta( \WC_Order $order, array $data ): void {
		$req = isset( $data['ci_requirements'] ) ? sanitize_textarea_field( (string) $data['ci_requirements'] ) : '';
		if ( '' !== $req ) {
			$order->update_meta_data( '_ci_requirements', $req );
		}
		$web = isset( $data['ci_website'] ) ? esc_url_raw( (string) $data['ci_website'] ) : '';
		if ( $web ) {
			$order->update_meta_data( '_ci_website', $web );
		}
		$gstin = strtoupper( trim( (string) ( $data['ci_gstin'] ?? '' ) ) );
		if ( $gstin && preg_match( self::GSTIN_REGEX, $gstin ) ) {
			$order->update_meta_data( '_ci_gstin', $gstin );
		}
		if ( ! empty( $data['ci_marketing_optin'] ) ) {
			$order->update_meta_data( '_ci_marketing_optin', 1 );
			Marketing::subscribe( $order->get_billing_email(), $order->get_billing_first_name(), 'checkout', true );
		}
	}

	public static function admin_order_panel( \WC_Order $order ): void {
		$req = (string) $order->get_meta( '_ci_requirements' );
		echo '<div style="clear:both;margin-top:12px"><h3>' . esc_html__( 'CirilInfo', 'cirilinfo' ) . '</h3>';
		if ( $order->get_meta( '_ci_gstin' ) ) {
			echo '<p><strong>GSTIN:</strong> ' . esc_html( $order->get_meta( '_ci_gstin' ) ) . '</p>';
		}
		if ( $order->get_meta( '_ci_website' ) ) {
			echo '<p><strong>' . esc_html__( 'Website', 'cirilinfo' ) . ':</strong> ' . esc_html( $order->get_meta( '_ci_website' ) ) . '</p>';
		}
		if ( $req ) {
			echo '<p><strong>' . esc_html__( 'Requirements', 'cirilinfo' ) . ':</strong><br>' . nl2br( esc_html( $req ) ) . '</p>';
		}
		if ( $order->get_meta( '_ci_invoice_number' ) ) {
			echo '<p><strong>' . esc_html__( 'Invoice', 'cirilinfo' ) . ':</strong> <a href="' . esc_url( Invoices::url( $order ) ) . '" target="_blank" rel="noopener">' . esc_html( $order->get_meta( '_ci_invoice_number' ) ) . '</a></p>';
		}
		echo '</div>';
	}

	public static function thankyou_panel( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$projects = get_posts( array( 'post_type' => 'ci_project', 'post_status' => 'publish', 'numberposts' => 10, 'meta_query' => array( array( 'key' => '_ci_order_id', 'value' => $order->get_id() ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		if ( ! $projects ) {
			return;
		}
		echo '<section class="ci-panel ci-panel--next"><h2>' . esc_html__( 'What happens next?', 'cirilinfo' ) . '</h2><ol class="ci-steps">';
		echo '<li>' . esc_html__( 'We confirm your payment and email your invoice.', 'cirilinfo' ) . '</li>';
		echo '<li>' . esc_html__( 'Share your detailed requirements and reference files.', 'cirilinfo' ) . '</li>';
		echo '<li>' . esc_html__( 'Our team is assigned and starts work. You get progress updates by email and in your dashboard.', 'cirilinfo' ) . '</li></ol>';
		if ( is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id() ) {
			foreach ( $projects as $p ) {
				echo '<p><a class="ci-btn ci-btn--primary" href="' . esc_url( Projects::project_url( $p->ID ) ) . '">' . esc_html__( 'Submit requirements & track project', 'cirilinfo' ) . '</a></p>';
				break;
			}
		} else {
			echo '<p>' . esc_html__( 'Log in or create an account with the same email address to submit requirements and track your project.', 'cirilinfo' ) . ' <a href="' . esc_url( wc_get_page_permalink( 'myaccount' ) ) . '">' . esc_html__( 'My account', 'cirilinfo' ) . '</a></p>';
		}
		echo '</section>';
	}

	/* ----------------------------------------------- WooCommerce setup */

	/** Apply the store-wide WooCommerce settings the marketplace flow relies on. */
	public static function configure_woocommerce(): void {
		$set = array(
			'woocommerce_currency'                     => 'INR',
			'woocommerce_currency_pos'                 => 'left',
			'woocommerce_price_thousand_sep'           => ',',
			'woocommerce_price_decimal_sep'            => '.',
			'woocommerce_price_num_decimals'           => '2',
			'woocommerce_enable_guest_checkout'        => 'yes',
			'woocommerce_enable_signup_and_login_from_checkout' => 'yes',
			'woocommerce_enable_checkout_login_reminder' => 'yes',
			'woocommerce_enable_myaccount_registration' => 'yes',
			'woocommerce_registration_generate_password' => 'no',
			'woocommerce_enable_coupons'               => 'yes',
			'woocommerce_calc_discounts_sequentially'  => 'no',
			'woocommerce_cart_redirect_after_add'      => 'no',
			'woocommerce_ship_to_countries'            => 'disabled',
			'woocommerce_prices_include_tax'           => 'no',
			'woocommerce_tax_based_on'                 => 'billing',
			'woocommerce_tax_display_shop'             => 'excl',
			'woocommerce_tax_display_cart'             => 'excl',
			'woocommerce_tax_total_display'            => 'itemized',
			'woocommerce_price_display_suffix'         => '',
		);
		foreach ( $set as $k => $v ) {
			update_option( $k, $v );
		}
		if ( ! get_option( 'woocommerce_default_country' ) || 'GB' === get_option( 'woocommerce_default_country' ) ) {
			update_option( 'woocommerce_default_country', 'IN:' . Settings::get( 'state_code', 'KL' ) );
		}
		self::sync_gst();
	}

	/** Mirror the configurable GST rate into the WooCommerce tax table. */
	public static function sync_gst(): void {
		if ( ! class_exists( 'WC_Tax' ) ) {
			return;
		}
		$rate_id = (int) get_option( 'cirilinfo_gst_rate_id', 0 );
		if ( $rate_id ) {
			\WC_Tax::_delete_tax_rate( $rate_id );
			delete_option( 'cirilinfo_gst_rate_id' );
		}
		$enabled = (bool) Settings::get( 'gst_enabled', 1 );
		$rate    = (float) Settings::get( 'gst_rate', '18' );
		update_option( 'woocommerce_calc_taxes', $enabled && $rate > 0 ? 'yes' : 'no' );
		if ( ! $enabled || $rate <= 0 ) {
			return;
		}
		$new_id = \WC_Tax::_insert_tax_rate( array(
			'tax_rate_country'  => 'india' === Settings::get( 'gst_scope', 'india' ) ? 'IN' : '',
			'tax_rate_state'    => '',
			'tax_rate'          => number_format( $rate, 4, '.', '' ),
			'tax_rate_name'     => 'GST',
			'tax_rate_priority' => 1,
			'tax_rate_compound' => 0,
			'tax_rate_shipping' => 0,
			'tax_rate_order'    => 1,
			'tax_rate_class'    => '',
		) );
		update_option( 'cirilinfo_gst_rate_id', (int) $new_id );
	}
}
