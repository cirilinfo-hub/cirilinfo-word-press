<?php
/**
 * Archive.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
cirilinfo_page_hero( is_home() ? __( 'Blog', 'cirilinfo' ) : get_the_archive_title(), is_home() ? __( 'Guides and ideas on web development, SEO and digital marketing.', 'cirilinfo' ) : wp_strip_all_tags( get_the_archive_description() ), __( 'Insights', 'cirilinfo' ) );
?>
<section class="ci-section"><div class="ci-container ci-blog">
	<div>
		<?php if ( have_posts() ) : ?>
			<div class="ci-grid ci-grid--2"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/card-post' ); endwhile; ?></div>
			<?php cirilinfo_pagination(); ?>
		<?php else : ?>
			<div class="ci-empty"><p><?php esc_html_e( 'No articles found.', 'cirilinfo' ); ?></p><?php get_search_form(); ?></div>
		<?php endif; ?>
	</div>
	<aside class="ci-sidebar"><?php get_search_form(); if ( is_active_sidebar( 'blog-sidebar' ) ) { dynamic_sidebar( 'blog-sidebar' ); } else { the_widget( 'WP_Widget_Recent_Posts' ); the_widget( 'WP_Widget_Categories' ); the_widget( 'WP_Widget_Tag_Cloud' ); } ?></aside>
</div></section>
<?php
get_footer();
