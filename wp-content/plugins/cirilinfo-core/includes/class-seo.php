<?php
/**
 * Technical SEO, structured data, social tags, analytics & conversion tracking.
 *
 * @package CirilInfo
 */

namespace CirilInfo;

defined( 'ABSPATH' ) || exit;

final class Seo {

	public static function init(): void {
		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
		add_action( 'wp_head', array( __CLASS__, 'head_meta' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'json_ld' ), 5 );
		add_action( 'wp_head', array( __CLASS__, 'tracking' ), 20 );
		add_action( 'wp_footer', array( __CLASS__, 'chat' ), 50 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 10, 2 );
		add_filter( 'wp_sitemaps_post_types', array( __CLASS__, 'sitemap_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( __CLASS__, 'sitemap_taxes' ) );
		add_filter( 'wp_sitemaps_add_provider', array( __CLASS__, 'sitemap_providers' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_query' ), 10, 2 );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'purchase_event' ), 20 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'checkout_event' ) );
		add_filter( 'wp_get_attachment_image_attributes', array( __CLASS__, 'image_attrs' ), 10, 1 );
	}

	/* ------------------------------------------------------------ Titles */

	public static function title_parts( array $parts ): array {
		if ( is_singular() ) {
			$t = (string) get_post_meta( get_queried_object_id(), '_ci_seo_title', true );
			if ( '' !== $t ) {
				$parts['title'] = $t;
			}
		}
		return $parts;
	}

	public static function description(): string {
		$default = (string) Settings::get( 'seo_default_desc', get_bloginfo( 'description' ) );
		if ( is_singular() ) {
			$id   = get_queried_object_id();
			$desc = (string) get_post_meta( $id, '_ci_seo_desc', true );
			if ( '' === $desc ) {
				$post = get_post( $id );
				$desc = $post ? ( has_excerpt( $id ) ? $post->post_excerpt : wp_strip_all_tags( strip_shortcodes( $post->post_content ) ) ) : '';
			}
			$desc = trim( wp_trim_words( wp_strip_all_tags( $desc ), 28, '…' ) );
			return '' !== $desc ? $desc : $default;
		}
		if ( is_category() || is_tag() || is_tax() ) {
			$d = term_description();
			return $d ? wp_trim_words( wp_strip_all_tags( $d ), 28, '…' ) : $default;
		}
		return $default;
	}

	public static function canonical(): string {
		if ( is_singular() ) {
			return (string) wp_get_canonical_url();
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_home() ) {
			$pid = (int) get_option( 'page_for_posts' );
			$url = $pid ? get_permalink( $pid ) : home_url( '/' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$url = get_term_link( get_queried_object() );
			$url = is_wp_error( $url ) ? '' : $url;
		} else {
			return '';
		}
		$paged = (int) get_query_var( 'paged' );
		return $paged > 1 ? get_pagenum_link( $paged ) : (string) $url;
	}

	public static function image(): string {
		if ( is_singular() && has_post_thumbnail() ) {
			$src = wp_get_attachment_image_url( get_post_thumbnail_id(), 'large' );
			if ( $src ) {
				return $src;
			}
		}
		return (string) Settings::get( 'seo_og_image', '' );
	}

	public static function head_meta(): void {
		$desc  = self::description();
		$canon = self::canonical();
		$site  = (string) Settings::get( 'company_name', get_bloginfo( 'name' ) );
		$title = wp_get_document_title();
		$img   = self::image();
		$type  = is_singular( 'post' ) ? 'article' : 'website';

		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $canon && ! is_singular() ) {
			echo '<link rel="canonical" href="' . esc_url( $canon ) . '">' . "\n";
		}
		$verify = Settings::get( 'gsc_verification' );
		if ( $verify ) {
			echo '<meta name="google-site-verification" content="' . esc_attr( $verify ) . '">' . "\n";
		}
		echo '<meta property="og:locale" content="en_IN">' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '">' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( $site ) . '">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $canon ) {
			echo '<meta property="og:url" content="' . esc_url( $canon ) . '">' . "\n";
		}
		if ( $img ) {
			echo '<meta property="og:image" content="' . esc_url( $img ) . '">' . "\n";
		}
		echo '<meta name="twitter:card" content="' . esc_attr( $img ? 'summary_large_image' : 'summary' ) . '">' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '">' . "\n";
		if ( $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '">' . "\n";
		}
		if ( $img ) {
			echo '<meta name="twitter:image" content="' . esc_url( $img ) . '">' . "\n";
		}
		echo '<meta name="theme-color" content="#1b1464">' . "\n";
	}

	/* ------------------------------------------------------- Breadcrumbs */

	/** @return array<int,array{0:string,1:string}> label + url (url empty for the current page). */
	public static function breadcrumbs(): array {
		$crumbs = array( array( __( 'Home', 'cirilinfo' ), home_url( '/' ) ) );
		if ( is_front_page() ) {
			return array();
		}
		$page_link = static function ( string $slug ): ?array {
			$p = get_page_by_path( $slug );
			return $p ? array( get_the_title( $p ), get_permalink( $p ) ) : null;
		};
		if ( is_singular( 'ci_service' ) ) {
			$c = $page_link( 'services' );
			if ( $c ) {
				$crumbs[] = $c;
			}
			$crumbs[] = array( get_the_title(), '' );
		} elseif ( is_singular( 'ci_portfolio' ) ) {
			$c = $page_link( 'portfolio' );
			if ( $c ) {
				$crumbs[] = $c;
			}
			$crumbs[] = array( get_the_title(), '' );
		} elseif ( is_singular( 'post' ) ) {
			$pid = (int) get_option( 'page_for_posts' );
			if ( $pid ) {
				$crumbs[] = array( get_the_title( $pid ), get_permalink( $pid ) );
			}
			$cats = get_the_category();
			if ( $cats ) {
				$crumbs[] = array( $cats[0]->name, get_category_link( $cats[0] ) );
			}
			$crumbs[] = array( get_the_title(), '' );
		} elseif ( is_page() ) {
			$ancestors = array_reverse( get_post_ancestors( get_queried_object_id() ) );
			foreach ( $ancestors as $a ) {
				$crumbs[] = array( get_the_title( $a ), get_permalink( $a ) );
			}
			$crumbs[] = array( get_the_title(), '' );
		} elseif ( is_home() ) {
			$crumbs[] = array( __( 'Blog', 'cirilinfo' ), '' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$pid = (int) get_option( 'page_for_posts' );
			if ( $pid && ( is_category() || is_tag() ) ) {
				$crumbs[] = array( get_the_title( $pid ), get_permalink( $pid ) );
			}
			$crumbs[] = array( single_term_title( '', false ), '' );
		} elseif ( is_search() ) {
			$crumbs[] = array( sprintf( /* translators: %s: query */ __( 'Search: %s', 'cirilinfo' ), get_search_query() ), '' );
		} elseif ( is_404() ) {
			$crumbs[] = array( __( 'Page not found', 'cirilinfo' ), '' );
		} elseif ( is_archive() ) {
			$crumbs[] = array( wp_strip_all_tags( get_the_archive_title() ), '' );
		}
		return $crumbs;
	}

	public static function breadcrumb_html(): string {
		$items = self::breadcrumbs();
		if ( count( $items ) < 2 ) {
			return '';
		}
		$out = '<nav class="ci-breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'cirilinfo' ) . '"><ol>';
		foreach ( $items as $i => $c ) {
			$last = $i === count( $items ) - 1;
			$out .= '<li' . ( $last ? ' aria-current="page"' : '' ) . '>' . ( $c[1] && ! $last ? '<a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a>' : '<span>' . esc_html( $c[0] ) . '</span>' ) . '</li>';
		}
		return $out . '</ol></nav>';
	}

	/* ------------------------------------------------------------ JSON-LD */

	public static function json_ld(): void {
		$graph = array();
		$name  = (string) Settings::get( 'company_name', get_bloginfo( 'name' ) );
		$home  = home_url( '/' );
		$logo  = '';
		if ( has_custom_logo() ) {
			$logo = (string) wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'full' );
		}
		$same = array_values( array_filter( array_map( static fn( $k ) => Settings::get( 'social_' . $k ), array( 'facebook', 'instagram', 'linkedin', 'x', 'youtube' ) ) ) );

		$org = array(
			'@type' => 'ProfessionalService',
			'@id'   => $home . '#organization',
			'name'  => $name,
			'url'   => $home,
			'slogan' => (string) Settings::get( 'tagline', '' ),
			'description' => (string) Settings::get( 'seo_default_desc', '' ),
			'priceRange' => '₹₹',
			'areaServed' => 'IN',
		);
		if ( $logo ) {
			$org['logo']  = $logo;
			$org['image'] = $logo;
		}
		if ( $same ) {
			$org['sameAs'] = $same;
		}
		if ( Settings::get( 'phone' ) || Settings::get( 'email' ) ) {
			$org['contactPoint'] = array_filter( array(
				'@type'       => 'ContactPoint',
				'contactType' => 'customer support',
				'telephone'   => (string) Settings::get( 'phone', '' ),
				'email'       => (string) Settings::get( 'email', '' ),
				'availableLanguage' => array( 'English' ),
			) );
		}
		if ( Settings::get( 'address' ) ) {
			$org['address'] = array( '@type' => 'PostalAddress', 'streetAddress' => (string) Settings::get( 'address' ), 'addressLocality' => (string) Settings::get( 'city', '' ), 'addressCountry' => 'IN' );
		}
		$graph[] = $org;

		if ( is_front_page() ) {
			$graph[] = array(
				'@type' => 'WebSite', '@id' => $home . '#website', 'url' => $home, 'name' => $name,
				'publisher' => array( '@id' => $home . '#organization' ),
				'potentialAction' => array( '@type' => 'SearchAction', 'target' => $home . '?s={search_term_string}', 'query-input' => 'required name=search_term_string' ),
			);
		}

		if ( is_singular( 'ci_service' ) ) {
			$id   = get_queried_object_id();
			$pkgs = array_filter( Packages::for_service( $id ), static fn( $p ) => $p['purchasable'] );
			$svc  = array(
				'@type'       => 'Service',
				'name'        => get_the_title( $id ),
				'description' => self::description(),
				'url'         => get_permalink( $id ),
				'provider'    => array( '@id' => $home . '#organization' ),
				'areaServed'  => 'IN',
			);
			if ( $pkgs ) {
				$offers = array();
				foreach ( $pkgs as $p ) {
					$offers[] = array( '@type' => 'Offer', 'name' => $p['title'], 'price' => number_format( $p['unit'], 2, '.', '' ), 'priceCurrency' => 'INR', 'availability' => 'https://schema.org/InStock', 'url' => get_permalink( $id ) );
				}
				$svc['offers'] = $offers;
			}
			$graph[] = $svc;
		}

		if ( is_singular( 'post' ) ) {
			$id    = get_queried_object_id();
			$graph[] = array_filter( array(
				'@type'         => 'Article',
				'headline'      => get_the_title( $id ),
				'datePublished' => get_the_date( 'c', $id ),
				'dateModified'  => get_the_modified_date( 'c', $id ),
				'author'        => array( '@type' => 'Person', 'name' => get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $id ) ) ),
				'publisher'     => array( '@id' => $home . '#organization' ),
				'mainEntityOfPage' => get_permalink( $id ),
				'image'         => self::image(),
				'description'   => self::description(),
			) );
		}

		if ( is_front_page() || is_page( 'faq' ) || is_singular( 'ci_service' ) ) {
			$faqs = get_posts( array( 'post_type' => 'ci_faq', 'numberposts' => 20, 'orderby' => 'menu_order', 'order' => 'ASC' ) );
			if ( $faqs ) {
				$ent = array();
				foreach ( $faqs as $f ) {
					$ent[] = array( '@type' => 'Question', 'name' => $f->post_title, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => wp_strip_all_tags( apply_filters( 'the_content', $f->post_content ) ) ) );
				}
				$graph[] = array( '@type' => 'FAQPage', 'mainEntity' => $ent );
			}
		}

		$crumbs = self::breadcrumbs();
		if ( count( $crumbs ) > 1 ) {
			$list = array();
			foreach ( $crumbs as $i => $c ) {
				$list[] = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1] ?: self::canonical() );
			}
			$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $list );
		}

		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	/* ------------------------------------------------------- Robots etc. */

	public static function robots( array $r ): array {
		$noindex = false;
		if ( is_search() || is_404() ) {
			$noindex = true;
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			$noindex = true;
		}
		if ( is_singular() && get_post_meta( get_queried_object_id(), '_ci_seo_noindex', true ) ) {
			$noindex = true;
		}
		if ( $noindex ) {
			unset( $r['index'] );
			$r['noindex'] = true;
			$r['follow']  = true;
		}
		return $r;
	}

	public static function robots_txt( string $output, $public ): string {
		if ( ! $public ) {
			return $output;
		}
		$paths = array( '/cart/', '/checkout/', '/my-account/' );
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $k ) {
				$p = wp_parse_url( wc_get_page_permalink( $k ), PHP_URL_PATH );
				if ( $p ) {
					$paths[] = $p;
				}
			}
		}
		$extra = "\n# CirilInfo\n";
		foreach ( array_unique( $paths ) as $p ) {
			$extra .= 'Disallow: ' . $p . "\n";
		}
		$extra .= "Disallow: /*?add-to-cart=\nDisallow: /*?s=\nDisallow: /*?ci_\n";
		return $output . $extra;
	}

	private static function excluded_page_ids(): array {
		$ids = array();
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( array( 'cart', 'checkout', 'myaccount' ) as $k ) {
				$id = (int) wc_get_page_id( $k );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
		}
		return $ids;
	}

	public static function sitemap_types( array $types ): array {
		unset( $types['product'], $types['attachment'] );
		return $types;
	}

	public static function sitemap_taxes( array $taxes ): array {
		unset( $taxes['product_cat'], $taxes['product_tag'], $taxes['product_shipping_class'], $taxes['post_format'] );
		return $taxes;
	}

	public static function sitemap_providers( $provider, string $name ) {
		return 'users' === $name ? false : $provider;
	}

	public static function sitemap_query( array $args, string $post_type ): array {
		if ( 'page' === $post_type ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), self::excluded_page_ids() );
		}
		$meta   = (array) ( $args['meta_query'] ?? array() );
		$meta[] = array( 'relation' => 'OR', array( 'key' => '_ci_seo_noindex', 'compare' => 'NOT EXISTS' ), array( 'key' => '_ci_seo_noindex', 'value' => '1', 'compare' => '!=' ) );
		$args['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery
		return $args;
	}

	public static function image_attrs( array $attr ): array {
		if ( empty( $attr['decoding'] ) ) {
			$attr['decoding'] = 'async';
		}
		return $attr;
	}

	/* ------------------------------------------------ Tracking & events */

	public static function tracking(): void {
		$ga = preg_replace( '/[^A-Za-z0-9\-]/', '', (string) Settings::get( 'ga4_id', '' ) );
		$px = preg_replace( '/[^0-9]/', '', (string) Settings::get( 'pixel_id', '' ) );
		?>
<script>
(function(){
var cfg=<?php echo wp_json_encode( array( 'ga' => $ga, 'px' => $px, 'consent' => (bool) Settings::get( 'consent_required', 1 ) ) ); ?>;
window.ciQ=window.ciQ||[];
function flush(){while(window.ciQ.length){var e=window.ciQ.shift();if(window.gtag&&cfg.ga){gtag('event',e[0],e[1]);}if(window.fbq&&cfg.px&&e[2]&&e[2].fb){fbq('track',e[2].fb,e[2].fbp||{});}}}
window.ciEvent=function(n,p,f){window.ciQ.push([n,p||{},f||{}]);if(window.ciReady){flush();}};
window.ciLoadTracking=function(){if(window.ciReady){return;}window.ciReady=true;
if(cfg.ga){var s=document.createElement('script');s.async=true;s.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(cfg.ga);document.head.appendChild(s);window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments);};gtag('js',new Date());gtag('config',cfg.ga);}
if(cfg.px){!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');fbq('init',cfg.px);fbq('track','PageView');}
flush();};
if(!cfg.ga&&!cfg.px){window.ciReady=true;return;}
var ok=!cfg.consent;try{ok=ok||localStorage.getItem('ci_consent')==='granted';}catch(e){}
if(ok){window.ciLoadTracking();}
})();
</script>
		<?php
		if ( is_singular( 'ci_service' ) ) {
			self::event_script( 'view_item', array( 'item_name' => get_the_title(), 'item_category' => 'service' ), array( 'fb' => 'ViewContent', 'fbp' => array( 'content_name' => get_the_title(), 'content_type' => 'service' ) ) );
		}
	}

	public static function event_script( string $name, array $params, array $extra = array() ): void {
		echo '<script>window.ciEvent&&window.ciEvent(' . wp_json_encode( $name ) . ',' . wp_json_encode( $params ) . ',' . wp_json_encode( $extra ) . ');</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}

	public static function checkout_event(): void {
		if ( ! WC()->cart ) {
			return;
		}
		self::event_script( 'begin_checkout', array( 'currency' => 'INR', 'value' => (float) WC()->cart->get_total( 'edit' ) ), array( 'fb' => 'InitiateCheckout', 'fbp' => array( 'value' => (float) WC()->cart->get_total( 'edit' ), 'currency' => 'INR' ) ) );
	}

	/** GA4 purchase + Meta Purchase, once per order. */
	public static function purchase_event( $order_id ): void {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( '_ci_purchase_tracked' ) || ! ( $order->is_paid() || $order->has_status( 'on-hold' ) ) ) {
			return;
		}
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = array( 'item_name' => $item->get_name(), 'price' => (float) $order->get_line_subtotal( $item, false ), 'quantity' => 1 );
		}
		$value = (float) $order->get_total();
		self::event_script(
			'purchase',
			array( 'transaction_id' => (string) $order->get_order_number(), 'value' => $value, 'tax' => (float) $order->get_total_tax(), 'currency' => $order->get_currency(), 'items' => $items ),
			array( 'fb' => 'Purchase', 'fbp' => array( 'value' => $value, 'currency' => $order->get_currency() ) )
		);
		$order->update_meta_data( '_ci_purchase_tracked', 1 );
		$order->save();
	}

	public static function chat(): void {
		if ( Settings::get( 'chat_enabled', 0 ) && Settings::get( 'chat_script' ) && ! is_admin() ) {
			echo "\n" . Settings::get( 'chat_script' ) . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- admin-only unfiltered_html field.
		}
	}
}
