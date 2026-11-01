<?php
/**
 * 404.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
cirilinfo_page_hero( __( 'Page not found', 'cirilinfo' ), __( 'The page you are looking for may have moved. Try search or head back home.', 'cirilinfo' ), '404' );
?>
<section class="ci-section"><div class="ci-container ci-container--narrow ci-center"><?php get_search_form(); ?><p class="ci-mt"><a class="ci-btn ci-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to home', 'cirilinfo' ); ?></a></p></div></section>
<?php
get_footer();
