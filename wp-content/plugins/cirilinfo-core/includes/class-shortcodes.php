<?php
/**
 * Shortcodes + front-end assets.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Shortcodes {

	public static function init(): void {
		$map = array(
			'ci_services'         => 'services',
			'ci_packages'         => 'packages',
			'ci_pricing'          => 'pricing',
			'ci_pricing_highlights' => 'highlights',
			'ci_faq'              => 'faq',
			'ci_testimonials'     => 'testimonials',
			'ci_portfolio'        => 'portfolio',
			'ci_clients'          => 'clients',
			'ci_team'             => 'team',
			'ci_enquiry_form'     => 'enquiry',
			'ci_newsletter'       => 'newsletter',
			'ci_feedback_form'    => 'feedback',
			'ci_contact_info'     => 'contact_info',
			'ci_map'              => 'map',
			'ci_social'           => 'social',
			'ci_whatsapp'         => 'whatsapp',
			'ci_breadcrumbs'      => 'breadcrumbs',
		);
		foreach ( $map as $tag => $method ) {
			add_shortcode( $tag, array( __CLASS__, $method ) );
		}
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'floating_whatsapp' ) );
	}

	public static function assets(): void {
		wp_enqueue_style( 'cirilinfo-front', CIRILINFO_URL . 'assets/css/front.css', array(), CIRILINFO_VERSION );
		wp_enqueue_script( 'cirilinfo-front', CIRILINFO_URL . 'assets/js/front.js', array(), CIRILINFO_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
		wp_localize_script( 'cirilinfo-front', 'CirilInfo', array(
			'ajax'    => admin_url( 'admin-ajax.php' ),
			'consent' => (bool) Settings::get( 'consent_required', 1 ) && ( Settings::get( 'ga4_id' ) || Settings::get( 'pixel_id' ) ),
			'i18n'    => array(
				'estimate' => __( 'Estimated total (excl. GST):', 'cirilinfo' ),
				'sending'  => __( 'Sending…', 'cirilinfo' ),
				'error'    => __( 'Something went wrong. Please try again.', 'cirilinfo' ),
				'accept'   => __( 'Accept', 'cirilinfo' ),
				'decline'  => __( 'Decline', 'cirilinfo' ),
				'cookie'   => __( 'We use cookies for analytics and marketing to improve your experience.', 'cirilinfo' ),
			),
		) );
	}

	public static function services( $atts ): string {
		return Render::services_grid( shortcode_atts( array( 'limit' => -1, 'columns' => 3 ), (array) $atts ) );
	}

	public static function packages( $atts ): string {
		$a  = shortcode_atts( array( 'service' => 0, 'compare' => 0, 'mode' => 'full' ), (array) $atts );
		$id = (int) $a['service'] ?: ( is_singular( 'ci_service' ) ? get_queried_object_id() : 0 );
		if ( ! $id ) {
			return '';
		}
		return Render::packages( $id, 'compact' === $a['mode'] ? 'compact' : 'full' ) . ( $a['compare'] ? '<details class="ci-compare-box"><summary>' . esc_html__( 'Compare all features', 'cirilinfo' ) . '</summary>' . Render::compare_table( $id ) . '</details>' : '' );
	}

	public static function pricing(): string {
		return Render::pricing();
	}

	/** Home page: compact cards for one service (default: Website Development). */
	public static function highlights( $atts ): string {
		$a = shortcode_atts( array( 'slug' => 'website-development' ), (array) $atts );
		$s = get_posts( array( 'post_type' => 'ci_service', 'name' => sanitize_title( $a['slug'] ), 'numberposts' => 1 ) );
		if ( ! $s ) {
			$all = Render::services_query( 1 );
			$s   = $all;
		}
		return $s ? Render::packages( $s[0]->ID, 'compact' ) : '';
	}

	public static function faq( $atts ): string {
		return Render::faq( shortcode_atts( array( 'limit' => -1, 'search' => 1 ), (array) $atts ) );
	}

	public static function testimonials( $atts ): string {
		return Render::testimonials( shortcode_atts( array( 'limit' => 6 ), (array) $atts ) );
	}

	public static function portfolio( $atts ): string {
		return Render::portfolio( shortcode_atts( array( 'limit' => 12, 'filter' => 1 ), (array) $atts ) );
	}

	public static function clients(): string {
		return Render::clients();
	}

	public static function team(): string {
		return Render::team();
	}

	public static function enquiry( $atts ): string {
		$a       = shortcode_atts( array( 'type' => 'contact', 'service' => 0 ), (array) $atts );
		$service = (int) $a['service'] ?: absint( $_GET['service'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $service && is_singular( 'ci_service' ) ) {
			$service = get_queried_object_id();
		}
		$package = absint( $_GET['package'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification
		$type    = 'quote' === $a['type'] || $package ? 'quote' : 'contact';
		return '<div class="ci-form-wrap" id="enquiry"><span id="ci-notice"></span>' . Forms::render_enquiry( $type, array( 'service' => $service, 'package' => $package ) ) . '</div>';
	}

	public static function newsletter(): string {
		return '<div class="ci-newsletter">' . Forms::render_newsletter() . '</div>';
	}

	public static function feedback(): string {
		return '<div class="ci-form-wrap" id="feedback"><span id="ci-notice"></span>' . Forms::render_feedback() . '</div>';
	}

	public static function contact_info(): string {
		return Render::contact_info();
	}

	public static function map(): string {
		return Render::map();
	}

	public static function social(): string {
		return Render::social_links();
	}

	public static function whatsapp( $atts ): string {
		$a   = shortcode_atts( array( 'text' => 'Chat on WhatsApp', 'message' => 'Hi CirilInfo, I would like to know more about your services.' ), (array) $atts );
		$url = Render::whatsapp_url( $a['message'] );
		return $url ? '<a class="ci-btn ci-btn--whatsapp" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . Icons::svg( 'whatsapp', 20 ) . ' ' . esc_html( $a['text'] ) . '</a>' : '';
	}

	public static function breadcrumbs(): string {
		return Seo::breadcrumb_html();
	}

	/** Floating WhatsApp button on every public page. */
	public static function floating_whatsapp(): void {
		$url = Render::whatsapp_url( 'Hi CirilInfo, I would like to know more about your services.' );
		if ( $url && ! is_admin() ) {
			echo '<a class="ci-wa-float" href="' . esc_url( $url ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr__( 'Chat with us on WhatsApp', 'cirilinfo' ) . '">' . Icons::svg( 'whatsapp', 28 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	}
}
