<?php
/**
 * HTML renderers shared by shortcodes and the theme.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Render {

	/* --------------------------------------------------------- Contact */

	public static function whatsapp_url( string $text = '' ): string {
		$num = preg_replace( '/\D/', '', (string) Settings::get( 'whatsapp', '' ) );
		if ( ! $num ) {
			return '';
		}
		return 'https://wa.me/' . $num . ( $text ? '?text=' . rawurlencode( $text ) : '' );
	}

	public static function tel_url(): string {
		$p = preg_replace( '/[^0-9+]/', '', (string) Settings::get( 'phone', '' ) );
		return $p ? 'tel:' . $p : '';
	}

	public static function social_links( string $class = 'ci-social' ): string {
		$out = '';
		foreach ( array( 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X', 'youtube' => 'YouTube' ) as $k => $label ) {
			$url = Settings::get( 'social_' . $k );
			if ( $url ) {
				$out .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $label ) . '">' . Icons::svg( $k, 20 ) . '</a>';
			}
		}
		return $out ? '<div class="' . esc_attr( $class ) . '">' . $out . '</div>' : '';
	}

	public static function contact_info(): string {
		$rows = array();
		if ( Settings::get( 'phone' ) ) {
			$rows[] = array( 'phone', __( 'Call us', 'cirilinfo' ), '<a href="' . esc_url( self::tel_url() ) . '">' . esc_html( Settings::get( 'phone' ) ) . '</a>' );
		}
		$email = Settings::business_email();
		if ( $email ) {
			$rows[] = array( 'mail', __( 'Email us', 'cirilinfo' ), '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' );
		}
		if ( self::whatsapp_url() ) {
			$rows[] = array( 'whatsapp', 'WhatsApp', '<a href="' . esc_url( self::whatsapp_url( 'Hi CirilInfo, I would like to know more about your services.' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Chat on WhatsApp', 'cirilinfo' ) . '</a>' );
		}
		if ( Settings::get( 'address' ) ) {
			$rows[] = array( 'pin', __( 'Visit us', 'cirilinfo' ), nl2br( esc_html( Settings::get( 'address' ) ) ) );
		}
		if ( ! $rows ) {
			return '<p class="ci-muted">' . esc_html__( 'Add your contact details under CirilInfo → Settings → Company.', 'cirilinfo' ) . '</p>';
		}
		$out = '<ul class="ci-contact-list">';
		foreach ( $rows as $r ) {
			$out .= '<li><span class="ci-contact-list__icon">' . Icons::svg( $r[0], 22 ) . '</span><div><strong>' . esc_html( $r[1] ) . '</strong><br>' . $r[2] . '</div></li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return $out . '</ul>';
	}

	public static function map(): string {
		$src = Settings::get( 'map_embed' );
		if ( ! $src || 0 !== strpos( $src, 'https://www.google.com/maps' ) ) {
			return '';
		}
		return '<div class="ci-map"><iframe src="' . esc_url( $src ) . '" title="' . esc_attr__( 'Our location', 'cirilinfo' ) . '" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
	}

	/* --------------------------------------------------------- Services */

	public static function services_query( int $limit = -1 ): array {
		return get_posts( array( 'post_type' => 'ci_service', 'post_status' => 'publish', 'numberposts' => $limit, 'orderby' => array( 'menu_order' => 'ASC', 'title' => 'ASC' ) ) );
	}

	public static function from_price( int $service_id ): string {
		$min = null;
		$bill = 'one_time';
		foreach ( Packages::for_service( $service_id ) as $p ) {
			if ( $p['purchasable'] && ( null === $min || $p['unit'] < $min ) ) {
				$min  = $p['unit'];
				$bill = $p['billing'];
			}
		}
		return null === $min ? '' : sprintf( /* translators: %s: price */ __( 'From %s', 'cirilinfo' ), Pricing::money( $min ) . Pricing::suffix( $bill ) );
	}

	public static function service_card( \WP_Post $s ): string {
		$icon = (string) get_post_meta( $s->ID, '_ci_icon', true ) ?: 'web';
		$tag  = (string) get_post_meta( $s->ID, '_ci_tagline', true );
		$from = self::from_price( $s->ID );
		return sprintf(
			'<a class="ci-service-card reveal" href="%1$s"><span class="ci-service-card__icon">%2$s</span><h3>%3$s</h3><p>%4$s</p><span class="ci-service-card__foot"><em>%5$s</em><span class="ci-service-card__go">%6$s</span></span></a>',
			esc_url( get_permalink( $s ) ),
			Icons::svg( $icon, 28 ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( get_the_title( $s ) ),
			esc_html( $tag ?: wp_trim_words( $s->post_excerpt ?: wp_strip_all_tags( $s->post_content ), 18 ) ),
			esc_html( $from ),
			Icons::svg( 'arrow', 18 ) // phpcs:ignore WordPress.Security.EscapeOutput
		);
	}

	public static function services_grid( array $a = array() ): string {
		$a        = wp_parse_args( $a, array( 'limit' => -1, 'columns' => 3 ) );
		$services = self::services_query( (int) $a['limit'] );
		if ( ! $services ) {
			return '<p class="ci-muted">' . esc_html__( 'Services will appear here once added.', 'cirilinfo' ) . '</p>';
		}
		$out = '<div class="ci-grid ci-grid--' . (int) $a['columns'] . '">';
		foreach ( $services as $s ) {
			$out .= self::service_card( $s );
		}
		return $out . '</div>';
	}

	/* ---------------------------------------------------------- Packages */

	public static function enquire_url( int $service_id, int $package_id = 0 ): string {
		$c    = get_page_by_path( 'contact' );
		$base = $c ? get_permalink( $c ) : home_url( '/contact/' );
		return add_query_arg( array_filter( array( 'service' => $service_id, 'package' => $package_id ) ), $base ) . '#enquiry';
	}

	/**
	 * One pricing card.
	 *
	 * @param string $mode 'full' (term + add-ons + live total) or 'compact' (direct add to cart).
	 */
	public static function package_card( array $p, string $mode = 'full' ): string {
		$tier_label = Post_Types::tiers()[ $p['tier'] ] ?? ucfirst( $p['tier'] );
		$wc         = Plugin::wc_active();
		$cls        = 'ci-plan' . ( $p['featured'] ? ' ci-plan--featured' : '' );
		$out        = '<article class="' . esc_attr( $cls ) . ' reveal" data-ci-plan data-unit="' . esc_attr( (string) $p['unit'] ) . '">';
		if ( $p['featured'] ) {
			$out .= '<span class="ci-plan__badge">' . esc_html__( 'Most popular', 'cirilinfo' ) . '</span>';
		}
		$out .= '<h3 class="ci-plan__tier">' . esc_html( $tier_label ) . '</h3>';
		if ( $p['description'] ) {
			$out .= '<p class="ci-plan__desc">' . esc_html( wp_strip_all_tags( $p['description'] ) ) . '</p>';
		}
		if ( 'custom' === $p['billing'] ) {
			$out .= '<div class="ci-plan__price"><strong class="ci-plan__custom">' . esc_html__( 'Custom quote', 'cirilinfo' ) . '</strong></div>';
		} else {
			$out .= '<div class="ci-plan__price">';
			if ( $p['has_sale'] ) {
				$out .= '<del>' . esc_html( Pricing::money( $p['price'] ) ) . '</del> ';
			}
			$out .= '<strong>' . esc_html( Pricing::money( $p['unit'] ) ) . '</strong>' . ( $p['note'] ? '<span class="ci-plan__plus">' . esc_html( $p['note'] ) . '</span>' : '' ) . '<span class="ci-plan__per">' . esc_html( Pricing::suffix( $p['billing'] ) ) . '</span></div>';
			$out .= '<p class="ci-plan__gst">' . esc_html( Settings::get( 'gst_enabled', 1 ) ? __( '+ GST as applicable', 'cirilinfo' ) : __( 'Prices exclude taxes', 'cirilinfo' ) ) . '</p>';
		}
		if ( $p['features'] ) {
			$out .= '<ul class="ci-plan__features">';
			foreach ( $p['features'] as $f ) {
				$out .= '<li>' . Icons::svg( 'check', 18 ) . '<span>' . esc_html( $f ) . '</span></li>';
			}
			$out .= '</ul>';
		}
		$meta = array_filter( array( __( 'Delivery', 'cirilinfo' ) => $p['delivery'], __( 'Revisions', 'cirilinfo' ) => $p['revisions'], __( 'Support', 'cirilinfo' ) => $p['support'] ) );
		if ( $meta ) {
			$out .= '<dl class="ci-plan__meta">';
			foreach ( $meta as $k => $v ) {
				$out .= '<div><dt>' . esc_html( $k ) . '</dt><dd>' . esc_html( $v ) . '</dd></div>';
			}
			$out .= '</dl>';
		}

		$out .= '<div class="ci-plan__actions">';
		if ( $wc && $p['purchasable'] && $p['product_id'] ) {
			$out .= '<form class="ci-plan__form" method="post" action="' . esc_url( wc_get_cart_url() ) . '" data-ci-cart>';
			$out .= '<input type="hidden" name="add-to-cart" value="' . (int) $p['product_id'] . '">';
			$out .= '<input type="hidden" name="_ci_cart_nonce" value="' . esc_attr( wp_create_nonce( 'ci_add_to_cart' ) ) . '">';
			if ( 'full' === $mode ) {
				$terms = Pricing::terms( $p['billing'] );
				if ( count( $terms ) > 1 ) {
					$unit  = 'monthly' === $p['billing'] ? __( 'month(s)', 'cirilinfo' ) : __( 'year(s)', 'cirilinfo' );
					$out  .= '<label class="ci-plan__term">' . esc_html__( 'Duration', 'cirilinfo' ) . ' <select name="ci_term" data-ci-term>';
					foreach ( $terms as $t ) {
						$out .= '<option value="' . (int) $t . '">' . (int) $t . ' ' . esc_html( $unit ) . '</option>';
					}
					$out .= '</select></label>';
				} else {
					$out .= '<input type="hidden" name="ci_term" value="1">';
				}
				$addons = Packages::addons( $p['service_id'] );
				if ( $addons ) {
					$out .= '<fieldset class="ci-plan__addons"><legend>' . esc_html__( 'Optional add-ons', 'cirilinfo' ) . '</legend>';
					foreach ( $addons as $i => $a ) {
						$out .= '<label><input type="checkbox" name="ci_addons[]" value="' . (int) $i . '" data-price="' . esc_attr( (string) (float) $a['price'] ) . '" data-recurring="' . ( ! empty( $a['recurring'] ) ? '1' : '0' ) . '"> <span>' . esc_html( $a['label'] ) . '</span> <em>+' . esc_html( Pricing::money( (float) $a['price'] ) ) . ( ! empty( $a['recurring'] ) ? esc_html( Pricing::suffix( $p['billing'] ) ) : '' ) . '</em></label>';
					}
					$out .= '</fieldset>';
				}
				$out .= '<p class="ci-plan__total" data-ci-total aria-live="polite"></p>';
			} else {
				$out .= '<input type="hidden" name="ci_term" value="1">';
			}
			$out .= '<button type="submit" class="ci-btn ci-btn--primary ci-btn--block">' . Icons::svg( 'cart', 18 ) . ' ' . esc_html__( 'Buy now', 'cirilinfo' ) . '</button></form>';
		}
		$out .= '<a class="ci-btn ci-btn--ghost ci-btn--block" href="' . esc_url( self::enquire_url( $p['service_id'], $p['id'] ) ) . '">' . esc_html( 'custom' === $p['billing'] || ! $p['purchasable'] ? __( 'Request a quote', 'cirilinfo' ) : __( 'Enquire', 'cirilinfo' ) ) . '</a>';
		if ( 'compact' === $mode && $p['service_id'] ) {
			$out .= '<a class="ci-link ci-plan__more" href="' . esc_url( get_permalink( $p['service_id'] ) ) . '">' . esc_html__( 'Details & add-ons', 'cirilinfo' ) . ' →</a>';
		}
		return $out . '</div></article>';
	}

	public static function packages( int $service_id, string $mode = 'full' ): string {
		$pkgs = Packages::for_service( $service_id );
		if ( ! $pkgs ) {
			return '<p class="ci-muted">' . esc_html__( 'Packages for this service are coming soon.', 'cirilinfo' ) . ' <a href="' . esc_url( self::enquire_url( $service_id ) ) . '">' . esc_html__( 'Request a custom quote', 'cirilinfo' ) . '</a></p>';
		}
		$out = '<div class="ci-plans">';
		foreach ( $pkgs as $p ) {
			$out .= self::package_card( $p, $mode );
		}
		return $out . '</div>';
	}

	public static function compare_table( int $service_id ): string {
		$pkgs = Packages::for_service( $service_id );
		if ( count( $pkgs ) < 2 ) {
			return '';
		}
		$features = array();
		foreach ( $pkgs as $p ) {
			foreach ( $p['features'] as $f ) {
				$features[ $f ] = true;
			}
		}
		$out  = '<div class="ci-table-wrap"><table class="ci-compare"><thead><tr><th><span class="screen-reader-text">' . esc_html__( 'Feature', 'cirilinfo' ) . '</span></th>';
		foreach ( $pkgs as $p ) {
			$out .= '<th' . ( $p['featured'] ? ' class="is-featured"' : '' ) . '>' . esc_html( Post_Types::tiers()[ $p['tier'] ] ?? $p['tier'] ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';
		$out .= '<tr class="ci-compare__price"><th scope="row">' . esc_html__( 'Price (excl. GST)', 'cirilinfo' ) . '</th>';
		foreach ( $pkgs as $p ) {
			$out .= '<td>' . esc_html( 'custom' === $p['billing'] ? __( 'Custom quote', 'cirilinfo' ) : Pricing::money( $p['unit'] ) . $p['note'] . Pricing::suffix( $p['billing'] ) ) . '</td>';
		}
		$out .= '</tr>';
		foreach ( array( 'delivery' => __( 'Delivery timeline', 'cirilinfo' ), 'revisions' => __( 'Revisions', 'cirilinfo' ), 'support' => __( 'Support', 'cirilinfo' ) ) as $k => $label ) {
			$out .= '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			foreach ( $pkgs as $p ) {
				$out .= '<td>' . esc_html( $p[ $k ] ?: '—' ) . '</td>';
			}
			$out .= '</tr>';
		}
		foreach ( array_keys( $features ) as $f ) {
			$out .= '<tr><th scope="row">' . esc_html( $f ) . '</th>';
			foreach ( $pkgs as $p ) {
				$has  = in_array( $f, $p['features'], true );
				$out .= '<td class="' . ( $has ? 'is-yes' : 'is-no' ) . '">' . ( $has ? Icons::svg( 'check', 18 ) . '<span class="screen-reader-text">' . esc_html__( 'Included', 'cirilinfo' ) . '</span>' : '<span aria-hidden="true">—</span><span class="screen-reader-text">' . esc_html__( 'Not included', 'cirilinfo' ) . '</span>' ) . '</td>';
			}
			$out .= '</tr>';
		}
		return $out . '</tbody></table></div>';
	}

	/** Pricing page: service selector tabs + cards + comparison. */
	public static function pricing(): string {
		$services = array_filter( self::services_query(), static fn( $s ) => (bool) Packages::for_service( $s->ID ) );
		if ( ! $services ) {
			return '<p class="ci-muted">' . esc_html__( 'Pricing will appear here once packages are published.', 'cirilinfo' ) . '</p>';
		}
		$out = '<div class="ci-tabs" data-ci-tabs><div class="ci-tabs__nav" role="tablist" aria-label="' . esc_attr__( 'Choose a service', 'cirilinfo' ) . '">';
		$i   = 0;
		foreach ( $services as $s ) {
			$out .= '<button type="button" role="tab" id="tab-' . (int) $s->ID . '" aria-controls="panel-' . (int) $s->ID . '" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '" class="' . ( 0 === $i ? 'is-active' : '' ) . '">' . esc_html( get_the_title( $s ) ) . '</button>';
			++$i;
		}
		$out .= '</div>';
		$i    = 0;
		foreach ( $services as $s ) {
			$out .= '<div class="ci-tabs__panel" role="tabpanel" id="panel-' . (int) $s->ID . '" aria-labelledby="tab-' . (int) $s->ID . '"' . ( 0 === $i ? '' : ' hidden' ) . '>';
			$out .= self::packages( $s->ID, 'compact' );
			$out .= '<details class="ci-compare-box"><summary>' . esc_html__( 'Compare all features', 'cirilinfo' ) . '</summary>' . self::compare_table( $s->ID ) . '</details>';
			$out .= '<p class="ci-center"><a class="ci-link" href="' . esc_url( get_permalink( $s ) ) . '">' . sprintf( /* translators: %s: service */ esc_html__( 'Learn more about %s', 'cirilinfo' ), esc_html( get_the_title( $s ) ) ) . ' →</a></p></div>';
			++$i;
		}
		return $out . '</div>';
	}

	/* ------------------------------------------------------------ Content */

	public static function faq( array $a = array() ): string {
		$a    = wp_parse_args( $a, array( 'limit' => -1, 'search' => 1 ) );
		$faqs = get_posts( array( 'post_type' => 'ci_faq', 'post_status' => 'publish', 'numberposts' => (int) $a['limit'], 'orderby' => array( 'menu_order' => 'ASC', 'date' => 'ASC' ) ) );
		if ( ! $faqs ) {
			return '';
		}
		$out = '<div class="ci-faq" data-ci-faq>';
		if ( $a['search'] ) {
			$out .= '<div class="ci-faq__search">' . Icons::svg( 'seo', 20 ) . '<input type="search" placeholder="' . esc_attr__( 'Search questions…', 'cirilinfo' ) . '" aria-label="' . esc_attr__( 'Search FAQs', 'cirilinfo' ) . '" data-ci-faq-search></div>';
		}
		$out .= '<div class="ci-faq__list">';
		foreach ( $faqs as $f ) {
			$out .= '<details class="ci-faq__item"><summary>' . esc_html( get_the_title( $f ) ) . '</summary><div class="ci-faq__answer">' . wp_kses_post( wpautop( $f->post_content ) ) . '</div></details>';
		}
		$out .= '</div><p class="ci-faq__empty" hidden>' . esc_html__( 'No matching questions. Try different keywords or contact us.', 'cirilinfo' ) . '</p></div>';
		return $out;
	}

	public static function stars( int $n ): string {
		$n = max( 0, min( 5, $n ) );
		return '<span class="ci-stars" role="img" aria-label="' . esc_attr( sprintf( /* translators: %d: rating */ __( 'Rated %d out of 5', 'cirilinfo' ), $n ) ) . '">' . str_repeat( '★', $n ) . str_repeat( '☆', 5 - $n ) . '</span>';
	}

	public static function testimonials( array $a = array() ): string {
		$a     = wp_parse_args( $a, array( 'limit' => 6 ) );
		$items = get_posts( array( 'post_type' => 'ci_testimonial', 'post_status' => 'publish', 'numberposts' => (int) $a['limit'] ) );
		if ( ! $items ) {
			return '';
		}
		$out = '<div class="ci-grid ci-grid--3">';
		foreach ( $items as $t ) {
			$role = trim( implode( ', ', array_filter( array( get_post_meta( $t->ID, '_ci_role', true ), get_post_meta( $t->ID, '_ci_company', true ) ) ) ) );
			$out .= '<figure class="ci-testimonial reveal">' . self::stars( (int) get_post_meta( $t->ID, '_ci_rating', true ) ?: 5 ) . '<blockquote>' . wp_kses_post( wpautop( $t->post_content ) ) . '</blockquote><figcaption>' . ( has_post_thumbnail( $t ) ? get_the_post_thumbnail( $t, 'thumbnail', array( 'loading' => 'lazy', 'alt' => '' ) ) : '<span class="ci-avatar" aria-hidden="true">' . esc_html( mb_substr( $t->post_title, 0, 1 ) ) . '</span>' ) . '<div><strong>' . esc_html( $t->post_title ) . '</strong><span>' . esc_html( $role ) . '</span></div></figcaption></figure>';
		}
		return $out . '</div>';
	}

	public static function clients(): string {
		$items = get_posts( array( 'post_type' => 'ci_client', 'post_status' => 'publish', 'numberposts' => 24 ) );
		if ( ! $items ) {
			return '';
		}
		$out = '<ul class="ci-clients">';
		foreach ( $items as $c ) {
			$img  = has_post_thumbnail( $c ) ? get_the_post_thumbnail( $c, 'medium', array( 'loading' => 'lazy', 'alt' => get_the_title( $c ) ) ) : '<span>' . esc_html( get_the_title( $c ) ) . '</span>';
			$out .= '<li>' . $img . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return $out . '</ul>';
	}

	public static function team(): string {
		$items = get_posts( array( 'post_type' => 'ci_team', 'post_status' => 'publish', 'numberposts' => 12, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
		if ( ! $items ) {
			return '';
		}
		$out = '<div class="ci-grid ci-grid--4">';
		foreach ( $items as $m ) {
			$out .= '<div class="ci-member reveal">' . ( has_post_thumbnail( $m ) ? get_the_post_thumbnail( $m, 'medium', array( 'loading' => 'lazy' ) ) : '<span class="ci-avatar ci-avatar--lg" aria-hidden="true">' . esc_html( mb_substr( $m->post_title, 0, 1 ) ) . '</span>' ) . '<h3>' . esc_html( get_the_title( $m ) ) . '</h3><p>' . esc_html( (string) get_post_meta( $m->ID, '_ci_role', true ) ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return $out . '</div>';
	}

	public static function portfolio( array $a = array() ): string {
		$a     = wp_parse_args( $a, array( 'limit' => 12, 'filter' => 1 ) );
		$items = get_posts( array( 'post_type' => 'ci_portfolio', 'post_status' => 'publish', 'numberposts' => (int) $a['limit'] ) );
		if ( ! $items ) {
			return '<p class="ci-muted">' . esc_html__( 'Our project showcase is being updated. Check back soon.', 'cirilinfo' ) . '</p>';
		}
		$out = '';
		if ( $a['filter'] ) {
			$terms = get_terms( array( 'taxonomy' => 'ci_portfolio_cat', 'hide_empty' => true ) );
			if ( ! is_wp_error( $terms ) && $terms ) {
				$out .= '<div class="ci-filter" role="group" aria-label="' . esc_attr__( 'Filter projects', 'cirilinfo' ) . '"><button type="button" class="is-active" data-filter="*">' . esc_html__( 'All', 'cirilinfo' ) . '</button>';
				foreach ( $terms as $t ) {
					$out .= '<button type="button" data-filter="' . esc_attr( $t->slug ) . '">' . esc_html( $t->name ) . '</button>';
				}
				$out .= '</div>';
			}
		}
		$out .= '<div class="ci-grid ci-grid--3" data-ci-portfolio>';
		foreach ( $items as $p ) {
			$cats = wp_get_post_terms( $p->ID, 'ci_portfolio_cat', array( 'fields' => 'slugs' ) );
			$tech = wp_get_post_terms( $p->ID, 'ci_tech', array( 'fields' => 'names' ) );
			$out .= '<article class="ci-work reveal" data-cats="' . esc_attr( implode( ' ', (array) $cats ) ) . '"><a class="ci-work__img" href="' . esc_url( get_permalink( $p ) ) . '">' . ( has_post_thumbnail( $p ) ? get_the_post_thumbnail( $p, 'large', array( 'loading' => 'lazy' ) ) : '<span class="ci-work__ph">' . Icons::svg( 'web', 40 ) . '</span>' ) . '</a><div class="ci-work__body"><h3><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3><p>' . esc_html( wp_trim_words( $p->post_excerpt ?: wp_strip_all_tags( $p->post_content ), 18 ) ) . '</p>';
			if ( $tech && ! is_wp_error( $tech ) ) {
				$out .= '<ul class="ci-tags">';
				foreach ( array_slice( $tech, 0, 5 ) as $t ) {
					$out .= '<li>' . esc_html( $t ) . '</li>';
				}
				$out .= '</ul>';
			}
			$url = get_post_meta( $p->ID, '_ci_url', true );
			if ( $url ) {
				$out .= '<a class="ci-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'View live project', 'cirilinfo' ) . ' ' . Icons::svg( 'external', 14 ) . '</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			$out .= '</div></article>';
		}
		return $out . '</div>';
	}
}
