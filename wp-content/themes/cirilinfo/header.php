<?php
/**
 * Site header.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'cirilinfo' ); ?></a>

<?php
$promo = cirilinfo_core() ? get_posts( array( 'post_type' => 'ci_banner', 'numberposts' => 1, 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_ci_placement', 'value' => 'promo' ), array( 'key' => '_ci_active', 'value' => '1' ) ) ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
if ( $promo ) :
	$pu = (string) get_post_meta( $promo[0]->ID, '_ci_cta_url', true );
	$pt = (string) get_post_meta( $promo[0]->ID, '_ci_cta_text', true );
	?>
	<div class="ci-promo"><span><?php echo esc_html( $promo[0]->post_title ); ?></span><?php if ( $pu ) : ?> <a href="<?php echo esc_url( $pu ); ?>"><?php echo esc_html( $pt ?: __( 'Learn more', 'cirilinfo' ) ); ?> →</a><?php endif; ?></div>
<?php endif; ?>

<header class="ci-header" data-ci-header>
	<div class="ci-container ci-header__in">
		<?php cirilinfo_logo(); ?>
		<button class="ci-burger" type="button" aria-expanded="false" aria-controls="ci-nav" data-ci-burger><span class="screen-reader-text"><?php esc_html_e( 'Menu', 'cirilinfo' ); ?></span><i></i><i></i><i></i></button>
		<nav id="ci-nav" class="ci-nav" aria-label="<?php esc_attr_e( 'Primary', 'cirilinfo' ); ?>">
			<?php
			wp_nav_menu( array( 'theme_location' => 'primary', 'container' => false, 'menu_class' => 'ci-menu', 'fallback_cb' => static function () {
				echo '<ul class="ci-menu"><li><a href="' . esc_url( home_url( '/' ) ) . '">' . esc_html__( 'Home', 'cirilinfo' ) . '</a></li></ul>';
			} ) );
			?>
			<div class="ci-nav__actions">
				<?php echo cirilinfo_cart_link(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( function_exists( 'wc_get_page_permalink' ) ) : ?>
					<a class="ci-iconbtn" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" aria-label="<?php echo esc_attr( is_user_logged_in() ? __( 'My account', 'cirilinfo' ) : __( 'Log in', 'cirilinfo' ) ); ?>"><?php echo cirilinfo_icon( 'user', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<?php endif; ?>
				<?php $contact = get_page_by_path( 'contact' ); ?>
				<a class="ci-btn ci-btn--primary ci-btn--sm" href="<?php echo esc_url( $contact ? get_permalink( $contact ) . '#enquiry' : home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Get a Quote', 'cirilinfo' ); ?></a>
			</div>
		</nav>
	</div>
</header>
<main id="main" class="ci-main">
