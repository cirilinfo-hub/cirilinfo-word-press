<?php
/**
 * Search results.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
/* translators: %s: query */
cirilinfo_page_hero( sprintf( __( 'Search: %s', 'cirilinfo' ), get_search_query() ) );
?>
<section class="ci-section"><div class="ci-container ci-container--narrow">
	<?php get_search_form(); ?>
	<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<article class="ci-result"><h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2><p><?php echo esc_html( get_the_excerpt() ); ?></p></article>
	<?php endwhile; cirilinfo_pagination(); else : ?>
		<div class="ci-empty"><p><?php esc_html_e( 'Nothing matched your search. Try different keywords.', 'cirilinfo' ); ?></p></div>
	<?php endif; ?>
</div></section>
<?php
get_footer();
