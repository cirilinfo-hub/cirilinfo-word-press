<?php
/**
 * CirilInfo theme bootstrap.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'CITHEME_VERSION', '1.0.0' );

add_action( 'after_setup_theme', static function () {
	load_theme_textdomain( 'cirilinfo', get_template_directory() . '/languages' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'custom-logo', array( 'height' => 60, 'width' => 220, 'flex-width' => true, 'flex-height' => true ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'woocommerce' );
	add_editor_style( 'assets/css/main.css' );
	add_image_size( 'ci-card', 720, 450, true );
} );

add_action( 'init', static function () {
	register_nav_menus( array(
		'primary'        => __( 'Primary menu', 'cirilinfo' ),
		'footer_company' => __( 'Footer – Company', 'cirilinfo' ),
		'footer_legal'   => __( 'Footer – Legal', 'cirilinfo' ),
	) );
}, 0 );

add_action( 'widgets_init', static function () {
	register_sidebar( array(
		'name' => __( 'Blog sidebar', 'cirilinfo' ), 'id' => 'blog-sidebar',
		'before_widget' => '<section id="%1$s" class="widget %2$s">', 'after_widget' => '</section>',
		'before_title' => '<h3 class="widget-title">', 'after_title' => '</h3>',
	) );
} );

add_action( 'wp_enqueue_scripts', static function () {
	wp_enqueue_style( 'cirilinfo-theme', get_template_directory_uri() . '/assets/css/main.css', array(), CITHEME_VERSION );
	wp_enqueue_script( 'cirilinfo-theme', get_template_directory_uri() . '/assets/js/main.js', array(), CITHEME_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
	// Block library CSS is not needed by the classic templates on non-post pages.
	if ( ! is_singular( 'post' ) ) {
		wp_dequeue_style( 'wp-block-library' );
		wp_dequeue_style( 'wp-block-library-theme' );
		wp_dequeue_style( 'global-styles' );
	}
}, 20 );

// Preload the self-hosted font to avoid a layout shift.
add_action( 'wp_head', static function () {
	printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( get_template_directory_uri() . '/assets/fonts/plus-jakarta-sans-latin-wght-normal.woff2' ) );
}, 2 );

// Performance: drop emoji scripts; lazy-load is default for images in core.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'excerpt_length', static fn() => 24 );
add_filter( 'excerpt_more', static fn() => '…' );

add_filter( 'body_class', static function ( array $c ): array {
	$c[] = 'ci-site';
	return $c;
} );

/** True when the Core plugin is active. */
function cirilinfo_core(): bool {
	return class_exists( 'CirilInfo\\Plugin' );
}

function cirilinfo_setting( string $key, $default = '' ) {
	return cirilinfo_core() ? \CirilInfo\Settings::get( $key, $default ) : $default;
}

function cirilinfo_icon( string $name, int $size = 24 ): string {
	return cirilinfo_core() ? \CirilInfo\Icons::svg( $name, $size ) : '';
}

function cirilinfo_sc( string $shortcode ): string {
	return cirilinfo_core() ? do_shortcode( $shortcode ) : '';
}

function cirilinfo_logo( bool $light = false ): void {
	if ( has_custom_logo() ) {
		the_custom_logo();
		return;
	}
	$file = $light ? 'logo-light.svg' : 'logo.svg';
	printf(
		'<a class="ci-logo" href="%s" rel="home" aria-label="%s"><img src="%s" width="168" height="44" alt="%s"></a>',
		esc_url( home_url( '/' ) ),
		esc_attr( get_bloginfo( 'name' ) ),
		esc_url( get_template_directory_uri() . '/assets/img/' . $file ),
		esc_attr( get_bloginfo( 'name' ) )
	);
}

/** Cart link with item count (WooCommerce). */
function cirilinfo_cart_link(): string {
	if ( ! function_exists( 'wc_get_cart_url' ) || ! WC()->cart ) {
		return '';
	}
	$n = (int) WC()->cart->get_cart_contents_count();
	return '<a class="ci-iconbtn" href="' . esc_url( wc_get_cart_url() ) . '" aria-label="' . esc_attr__( 'Cart', 'cirilinfo' ) . '">' . cirilinfo_icon( 'cart', 22 ) . ( $n ? '<span class="ci-count">' . $n . '</span>' : '' ) . '</a>';
}

add_filter( 'woocommerce_add_to_cart_fragments', static function ( $f ) {
	$f['a.ci-iconbtn[aria-label]'] = cirilinfo_cart_link();
	return $f;
} );

function cirilinfo_page_hero( string $title, string $sub = '', string $eyebrow = '' ): void {
	get_template_part( 'template-parts/page-hero', null, compact( 'title', 'sub', 'eyebrow' ) );
}

function cirilinfo_cta_band( string $title = '', string $text = '' ): void {
	get_template_part( 'template-parts/cta-band', null, compact( 'title', 'text' ) );
}

function cirilinfo_pagination(): void {
	the_posts_pagination( array( 'mid_size' => 1, 'prev_text' => '←', 'next_text' => '→' ) );
}

function cirilinfo_share_links( int $post_id ): string {
	$url   = rawurlencode( get_permalink( $post_id ) );
	$title = rawurlencode( get_the_title( $post_id ) );
	$links = array(
		'Facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url,
		'X'        => 'https://twitter.com/intent/tweet?url=' . $url . '&text=' . $title,
		'LinkedIn' => 'https://www.linkedin.com/sharing/share-offsite/?url=' . $url,
		'WhatsApp' => 'https://wa.me/?text=' . $title . '%20' . $url,
	);
	$out = '<div class="ci-share"><span>' . esc_html__( 'Share:', 'cirilinfo' ) . '</span>';
	foreach ( $links as $label => $href ) {
		$out .= '<a href="' . esc_url( $href ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $label ) . '</a>';
	}
	return $out . '</div>';
}

add_action( 'admin_notices', static function () {
	if ( ! cirilinfo_core() && current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p>' . esc_html__( 'The CirilInfo theme needs the CirilInfo Core plugin for services, pricing, checkout and forms. Please activate it.', 'cirilinfo' ) . '</p></div>';
	}
} );
