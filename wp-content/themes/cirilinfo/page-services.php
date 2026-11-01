<?php
/**
 * Services listing (slug: services).
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
cirilinfo_page_hero( get_the_title(), __( 'Web development, design and digital marketing – choose a service, compare packages and buy online.', 'cirilinfo' ), __( 'Our services', 'cirilinfo' ) );
?>
<section class="ci-section"><div class="ci-container"><?php echo cirilinfo_sc( '[ci_services]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
<?php while ( have_posts() ) : the_post(); if ( get_the_content() ) : ?><div class="ci-content ci-mt"><?php the_content(); ?></div><?php endif; endwhile; ?></div></section>
<?php
cirilinfo_cta_band();
get_footer();
