<?php
/**
 * Default page template (also wraps cart, checkout and My Account).
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$wide = function_exists( 'is_woocommerce' ) && ( is_cart() || is_checkout() || is_account_page() );
	cirilinfo_page_hero( get_the_title() );
	?>
	<section class="ci-section ci-section--tight">
		<div class="ci-container<?php echo $wide ? '' : ' ci-container--narrow'; ?>">
			<article <?php post_class( 'ci-content' ); ?>><?php the_content(); ?></article>
		</div>
	</section>
	<?php
endwhile;
get_footer();
