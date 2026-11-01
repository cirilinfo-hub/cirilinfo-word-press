<?php
/**
 * Single blog post: share links, tags, author, related posts.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$cats = wp_get_post_categories( get_the_ID() );
	cirilinfo_page_hero( get_the_title(), '', get_the_category() ? get_the_category()[0]->name : '' );
	?>
	<section class="ci-section"><div class="ci-container ci-blog">
		<article <?php post_class( 'ci-article' ); ?>>
			<p class="ci-post-card__meta"><?php echo esc_html( get_the_date() ); ?> · <?php echo esc_html( get_the_author() ); ?> · <?php echo esc_html( sprintf( /* translators: %d: minutes */ __( '%d min read', 'cirilinfo' ), max( 1, (int) ceil( str_word_count( wp_strip_all_tags( get_the_content() ) ) / 220 ) ) ) ); ?></p>
			<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'class' => 'ci-rounded' ) ); } ?>
			<div class="ci-content"><?php the_content(); wp_link_pages(); ?></div>
			<?php the_tags( '<ul class="ci-tags"><li>', '</li><li>', '</li></ul>' ); ?>
			<?php echo cirilinfo_share_links( get_the_ID() ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="ci-authorbox"><?php echo get_avatar( get_the_author_meta( 'ID' ), 64 ); ?><div><strong><?php the_author(); ?></strong><p><?php echo esc_html( get_the_author_meta( 'description' ) ?: __( 'Part of the CirilInfo team.', 'cirilinfo' ) ); ?></p></div></div>
			<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
		</article>
		<aside class="ci-sidebar"><?php get_search_form(); if ( is_active_sidebar( 'blog-sidebar' ) ) { dynamic_sidebar( 'blog-sidebar' ); } ?></aside>
	</div></section>
	<?php
	$related = new WP_Query( array( 'post_type' => 'post', 'posts_per_page' => 3, 'post__not_in' => array( get_the_ID() ), 'category__in' => $cats, 'ignore_sticky_posts' => true ) );
	if ( $related->have_posts() ) :
		?>
		<section class="ci-section ci-section--tint"><div class="ci-container"><header class="ci-sechead"><h2><?php esc_html_e( 'Related articles', 'cirilinfo' ); ?></h2></header><div class="ci-grid ci-grid--3"><?php while ( $related->have_posts() ) : $related->the_post(); get_template_part( 'template-parts/card-post' ); endwhile; wp_reset_postdata(); ?></div></div></section>
	<?php endif; ?>
	<?php
endwhile;
cirilinfo_cta_band();
get_footer();
