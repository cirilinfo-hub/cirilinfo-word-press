<?php
/**
 * FAQ with search (slug: faq).
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
cirilinfo_page_hero( get_the_title(), __( 'Quick answers about our services, payments, delivery and support.', 'cirilinfo' ), 'FAQ' );
?>
<section class="ci-section"><div class="ci-container"><?php echo cirilinfo_sc( '[ci_faq]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div></section>
<?php
cirilinfo_cta_band( __( 'Still have questions?', 'cirilinfo' ), __( 'Our team is happy to help – reach out and we will get back to you quickly.', 'cirilinfo' ) );
get_footer();
