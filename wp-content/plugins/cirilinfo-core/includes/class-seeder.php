<?php
/**
 * Idempotent content & configuration seeding (pages, menus, services, packages, demo data).
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Seeder {

	/** Create a page if no page with the slug exists. Returns page ID. */
	private static function page( string $slug, string $title, string $content = '', int $parent = 0 ): int {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			return (int) $existing->ID;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_parent'  => $parent,
		), true );
		return is_wp_error( $id ) ? 0 : (int) $id;
	}

	public static function pages_and_menus(): int {
		$name = Settings::get( 'company_name', 'CirilInfo' );
		$ids  = array(
			'home'     => self::page( 'home', 'Home' ),
			'about'    => self::page( 'about', 'About Us' ),
			'services' => self::page( 'services', 'Services' ),
			'pricing'  => self::page( 'pricing', 'Pricing & Packages' ),
			'portfolio' => self::page( 'portfolio', 'Portfolio' ),
			'blog'     => self::page( 'blog', 'Blog' ),
			'contact'  => self::page( 'contact', 'Contact Us' ),
			'faq'      => self::page( 'faq', 'Frequently Asked Questions' ),
			'terms'    => self::page( 'terms-and-conditions', 'Terms & Conditions', self::legal( 'terms', $name ) ),
			'privacy'  => self::page( 'privacy-policy', 'Privacy Policy', self::legal( 'privacy', $name ) ),
			'refund'   => self::page( 'refund-policy', 'Refund & Cancellation Policy', self::legal( 'refund', $name ) ),
		);
		$desc = (string) get_option( 'blogdescription' );
		if ( '' === $desc || 'Just another WordPress site' === $desc ) {
			update_option( 'blogdescription', (string) Settings::get( 'tagline', 'Your Digital Growth Partner' ) );
		}
		update_option( 'show_on_front', 'page' );
		if ( $ids['home'] ) {
			update_option( 'page_on_front', $ids['home'] );
		}
		if ( $ids['blog'] ) {
			update_option( 'page_for_posts', $ids['blog'] );
		}
		if ( $ids['privacy'] ) {
			update_option( 'wp_page_for_privacy_policy', $ids['privacy'] );
		}
		if ( $ids['terms'] ) {
			update_option( 'woocommerce_terms_page_id', $ids['terms'] );
		}
		if ( ! get_option( 'permalink_structure' ) ) {
			update_option( 'permalink_structure', '/%postname%/' );
			update_option( 'cirilinfo_flush_rewrite', 1 );
		}
		self::menus( $ids );
		return count( array_filter( $ids ) );
	}

	private static function menus( array $ids ): void {
		$locations = get_theme_mod( 'nav_menu_locations', array() );
		$locations = is_array( $locations ) ? $locations : array();

		if ( ! wp_get_nav_menu_object( 'CirilInfo Primary' ) ) {
			$menu = wp_create_nav_menu( 'CirilInfo Primary' );
			if ( ! is_wp_error( $menu ) ) {
				$i = 1;
				foreach ( array( 'home' => 'Home', 'about' => 'About', 'services' => 'Services', 'pricing' => 'Pricing', 'portfolio' => 'Portfolio', 'blog' => 'Blog', 'contact' => 'Contact' ) as $k => $label ) {
					if ( empty( $ids[ $k ] ) ) {
						continue;
					}
					wp_update_nav_menu_item( $menu, 0, array( 'menu-item-title' => $label, 'menu-item-object' => 'page', 'menu-item-object-id' => $ids[ $k ], 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $i++ ) );
				}
				$locations['primary'] = $menu;
			}
		}
		if ( ! wp_get_nav_menu_object( 'CirilInfo Company' ) ) {
			$menu = wp_create_nav_menu( 'CirilInfo Company' );
			if ( ! is_wp_error( $menu ) ) {
				foreach ( array( 'about', 'portfolio', 'blog', 'faq', 'contact' ) as $i => $k ) {
					if ( ! empty( $ids[ $k ] ) ) {
						wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object' => 'page', 'menu-item-object-id' => $ids[ $k ], 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $i + 1 ) );
					}
				}
				$locations['footer_company'] = $menu;
			}
		}
		if ( ! wp_get_nav_menu_object( 'CirilInfo Legal' ) ) {
			$menu = wp_create_nav_menu( 'CirilInfo Legal' );
			if ( ! is_wp_error( $menu ) ) {
				foreach ( array( 'terms', 'privacy', 'refund' ) as $i => $k ) {
					if ( ! empty( $ids[ $k ] ) ) {
						wp_update_nav_menu_item( $menu, 0, array( 'menu-item-object' => 'page', 'menu-item-object-id' => $ids[ $k ], 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-position' => $i + 1 ) );
					}
				}
				$locations['footer_legal'] = $menu;
			}
		}
		set_theme_mod( 'nav_menu_locations', $locations );
		self::service_menu_items();
	}

	/** Add services as a dropdown beneath the "Services" item of the primary menu (once). */
	private static function service_menu_items(): void {
		$menu = wp_get_nav_menu_object( 'CirilInfo Primary' );
		$page = get_page_by_path( 'services' );
		if ( ! $menu || ! $page || get_option( 'cirilinfo_service_menu_done' ) ) {
			return;
		}
		$services = Render::services_query();
		if ( ! $services ) {
			return;
		}
		$parent = 0;
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $item ) {
			if ( (int) $item->object_id === (int) $page->ID ) {
				$parent = (int) $item->ID;
			}
		}
		if ( ! $parent ) {
			return;
		}
		foreach ( $services as $i => $s ) {
			wp_update_nav_menu_item( $menu->term_id, 0, array( 'menu-item-title' => get_the_title( $s ), 'menu-item-object' => 'ci_service', 'menu-item-object-id' => $s->ID, 'menu-item-type' => 'post_type', 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent, 'menu-item-position' => 100 + $i ) );
		}
		update_option( 'cirilinfo_service_menu_done', 1 );
	}

	public static function configure_woocommerce(): void {
		if ( ! Plugin::wc_active() ) {
			return;
		}
		Checkout::configure_woocommerce();
		$set = array( 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]', 'myaccount' => '[woocommerce_my_account]' );
		foreach ( $set as $key => $sc ) {
			$id = (int) wc_get_page_id( $key );
			if ( $id > 0 ) {
				wp_update_post( array( 'ID' => $id, 'post_content' => $sc ) );
			}
		}
		$terms = get_page_by_path( 'terms-and-conditions' );
		if ( $terms ) {
			update_option( 'woocommerce_terms_page_id', $terms->ID );
		}
		update_option( 'cirilinfo_flush_rewrite', 1 );
	}

	/* --------------------------------------------- Services & packages */

	public static function services_and_packages(): array {
		$data  = include CIRILINFO_DIR . 'includes/data/services.php';
		$count = array( 'services' => 0, 'packages' => 0, 'faqs' => 0 );

		foreach ( $data['services'] as $order => $s ) {
			$existing = get_posts( array( 'post_type' => 'ci_service', 'name' => $s['slug'], 'post_status' => 'any', 'numberposts' => 1 ) );
			if ( $existing ) {
				$sid = (int) $existing[0]->ID;
			} else {
				$body = '<p>' . esc_html( $s['body'] ) . '</p>';
				$sid  = wp_insert_post( array(
					'post_type' => 'ci_service', 'post_status' => 'publish', 'post_title' => $s['title'], 'post_name' => $s['slug'],
					'post_excerpt' => $s['tagline'], 'post_content' => $body, 'menu_order' => $order,
				), true );
				if ( is_wp_error( $sid ) ) {
					continue;
				}
				update_post_meta( $sid, '_ci_icon', $s['icon'] );
				update_post_meta( $sid, '_ci_tagline', $s['tagline'] );
				update_post_meta( $sid, '_ci_highlights', implode( "\n", $s['highlights'] ) );
				update_post_meta( $sid, '_ci_process', implode( "\n", $s['process'] ) );
				update_post_meta( $sid, '_ci_addons', $s['addons'] );
				update_post_meta( $sid, '_ci_seo_desc', $s['tagline'] );
				++$count['services'];
			}

			$prev = array();
			foreach ( array( 'basic', 'professional', 'enterprise' ) as $rank => $tier ) {
				$t = $s['tiers'][ $tier ];
				$have = get_posts( array( 'post_type' => 'ci_package', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_ci_service_id', 'value' => $sid ), array( 'key' => '_ci_tier', 'value' => $tier ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
				$features = 0 === $rank ? $t['f'] : array_merge( $prev, $t['f'] );
				$prev     = $features;
				if ( $have ) {
					continue;
				}
				$billing = $t['billing'] ?? $s['billing'];
				$pid     = wp_insert_post( array(
					'post_type' => 'ci_package', 'post_status' => 'publish', 'menu_order' => $rank,
					'post_title' => $s['title'] . ' – ' . ucfirst( $tier ),
				), true );
				if ( is_wp_error( $pid ) ) {
					continue;
				}
				update_post_meta( $pid, '_ci_service_id', $sid );
				update_post_meta( $pid, '_ci_tier', $tier );
				update_post_meta( $pid, '_ci_billing', $billing );
				update_post_meta( $pid, '_ci_price', (float) $t['price'] );
				update_post_meta( $pid, '_ci_sale_price', '' );
				update_post_meta( $pid, '_ci_price_note', $t['note'] );
				update_post_meta( $pid, '_ci_revisions', $t['rev'] );
				update_post_meta( $pid, '_ci_delivery', $t['del'] );
				update_post_meta( $pid, '_ci_support', $t['sup'] );
				update_post_meta( $pid, '_ci_features', implode( "\n", $features ) );
				update_post_meta( $pid, '_ci_featured', 1 === $rank ? 1 : 0 );
				update_post_meta( $pid, '_ci_active', 1 );
				do_action( 'cirilinfo_package_saved', $pid );
				++$count['packages'];
			}
		}

		if ( 0 === (int) wp_count_posts( 'ci_faq' )->publish ) {
			foreach ( $data['faqs'] as $i => $f ) {
				wp_insert_post( array( 'post_type' => 'ci_faq', 'post_status' => 'publish', 'post_title' => $f[0], 'post_content' => $f[1], 'menu_order' => $i ) );
				++$count['faqs'];
			}
		}
		self::service_menu_items();
		return $count;
	}

	/* -------------------------------------------------------- Demo data */

	public static function demo( bool $on ): int {
		$n = 0;
		if ( ! $on ) {
			$ids = get_posts( array( 'post_type' => array( 'ci_testimonial', 'ci_portfolio', 'ci_client', 'ci_team' ), 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_ci_demo', 'meta_value' => '1' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			foreach ( $ids as $id ) {
				wp_delete_post( (int) $id, true );
				++$n;
			}
			return $n;
		}
		$mk = static function ( string $type, string $title, string $content, array $meta ) use ( &$n ) {
			$id = wp_insert_post( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_content' => $content ) );
			if ( $id && ! is_wp_error( $id ) ) {
				update_post_meta( $id, '_ci_demo', 1 );
				foreach ( $meta as $k => $v ) {
					update_post_meta( $id, $k, $v );
				}
				++$n;
			}
			return $id;
		};
		$mk( 'ci_testimonial', '[SAMPLE] Customer Name', 'Sample testimonial text. Replace with a real customer review before launch.', array( '_ci_role' => 'Founder', '_ci_company' => 'Sample Company', '_ci_rating' => 5 ) );
		$mk( 'ci_testimonial', '[SAMPLE] Another Customer', 'Sample testimonial text. Replace with a real customer review before launch.', array( '_ci_role' => 'Marketing Head', '_ci_company' => 'Sample Retail', '_ci_rating' => 5 ) );
		$mk( 'ci_testimonial', '[SAMPLE] Third Customer', 'Sample testimonial text. Replace with a real customer review before launch.', array( '_ci_role' => 'Director', '_ci_company' => 'Sample Services', '_ci_rating' => 4 ) );
		foreach ( array( 'Corporate website', 'E-commerce store', 'Booking portal' ) as $i => $t ) {
			$id = $mk( 'ci_portfolio', '[SAMPLE] ' . $t, 'Sample project description. Replace with a real case study: the brief, what you built and the result achieved.', array( '_ci_client' => 'Sample Client', '_ci_url' => '', '_ci_result' => 'Replace with a real result' ) );
			if ( $id && ! is_wp_error( $id ) ) {
				wp_set_object_terms( $id, array( 'WordPress', 'WooCommerce', 'PHP' ), 'ci_tech' );
				wp_set_object_terms( $id, array( 0 === $i ? 'Business' : ( 1 === $i ? 'E-commerce' : 'Web Apps' ) ), 'ci_portfolio_cat' );
			}
		}
		foreach ( array( 'Sample Brand A', 'Sample Brand B', 'Sample Brand C', 'Sample Brand D', 'Sample Brand E' ) as $c ) {
			$mk( 'ci_client', '[SAMPLE] ' . $c, '', array() );
		}
		return $n;
	}

	/* ------------------------------------------------------ Legal text */

	private static function legal( string $kind, string $name ): string {
		$note = '<p><em>Template text – have this reviewed by a qualified professional and update the company details before going live.</em></p>';
		switch ( $kind ) {
			case 'terms':
				return $note . '<h2>1. Services</h2><p>' . esc_html( $name ) . ' provides website development and digital marketing services as described in the selected package. Scope, deliverables, timelines and revisions are those listed on the package at the time of purchase or in a written proposal.</p><h2>2. Orders &amp; payment</h2><p>Prices are in Indian Rupees and exclude GST unless stated. Work begins after payment is confirmed and project requirements are received. Payments are processed by third-party payment gateways; we do not store card or UPI credentials.</p><h2>3. Client responsibilities</h2><p>You agree to provide accurate content, assets and timely feedback. Delays in providing materials may extend delivery timelines.</p><h2>4. Revisions &amp; scope changes</h2><p>Each package includes the stated number of revision rounds. Work beyond the agreed scope is quoted separately.</p><h2>5. Intellectual property</h2><p>On full payment, ownership of the final deliverables transfers to you. Third-party software, fonts and stock assets remain under their own licences. We may showcase completed work in our portfolio unless you request otherwise in writing.</p><h2>6. Recurring services</h2><p>Monthly and yearly services are billed for the period purchased and must be renewed to continue. Ad spend for advertising services is paid directly to the advertising platform.</p><h2>7. Limitation of liability</h2><p>We do not guarantee specific search rankings, traffic or sales outcomes. Our liability is limited to the fees paid for the affected service.</p><h2>8. Governing law</h2><p>These terms are governed by the laws of India. Disputes are subject to the jurisdiction of the courts at our registered location.</p>';
			case 'privacy':
				return $note . '<h2>Information we collect</h2><p>Contact details you submit (name, email, phone), billing details for orders, project files you upload, and technical data such as IP address and cookies for security and analytics.</p><h2>How we use it</h2><p>To respond to enquiries, deliver services, process payments and invoices, send service updates, and (with your consent) marketing emails. You can unsubscribe at any time.</p><h2>Payments</h2><p>Payments are handled by our payment gateway partners. We never see or store your card number, CVV or UPI PIN.</p><h2>Sharing</h2><p>We share data only with service providers needed to run the business (payment gateway, email delivery, hosting, analytics) and where required by law.</p><h2>Cookies &amp; analytics</h2><p>We use cookies for essential site functions and, with your consent, Google Analytics and Meta Pixel to understand and improve our marketing.</p><h2>Your rights</h2><p>You may request access, correction or deletion of your personal data by contacting us using the details on the contact page.</p><h2>Security</h2><p>We use HTTPS, access controls and private file storage to protect your information.</p>';
			default:
				return $note . '<h2>Cancellations</h2><p>You may cancel an order before work begins for a full refund minus payment-gateway charges. Once work has started, refunds are calculated based on milestones already completed.</p><h2>Refund eligibility</h2><p>Digital deliverables already delivered and approved are non-refundable. If we are unable to deliver the agreed scope, you are entitled to a refund for the undelivered portion.</p><h2>Recurring services</h2><p>Monthly services can be cancelled before the next billing period; fees for the current period are non-refundable.</p><h2>How refunds are processed</h2><p>Approved refunds are issued to the original payment method within 5–7 working days of approval.</p><h2>How to request</h2><p>Raise a support ticket from your dashboard or contact us with your order number.</p>';
		}
	}
}
