<?php
/**
 * Portfolio (slug: portfolio).
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
cirilinfo_page_hero( get_the_title(), __( 'A selection of websites, stores and campaigns we have delivered.', 'cirilinfo' ), __( 'Our work', 'cirilinfo' ) );
?>
<section class="ci-section"><div class="ci-container"><?php echo cirilinfo_sc( '[ci_portfolio limit="24"]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div></section>
<?php
cirilinfo_cta_band();
get_footer();
