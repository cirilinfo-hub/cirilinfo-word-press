<?php
/**
 * Blog post card.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'ci-post-card reveal' ); ?>>
	<a class="ci-post-card__img" href="<?php the_permalink(); ?>" tabindex="-1" aria-hidden="true">
		<?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'ci-card', array( 'loading' => 'lazy' ) ); } else { echo '<span>' . cirilinfo_icon( 'content', 40 ) . '</span>'; } // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</a>
	<div class="ci-post-card__body">
		<p class="ci-post-card__meta"><?php echo esc_html( get_the_date() ); ?><?php $c = get_the_category(); if ( $c ) { echo ' · ' . esc_html( $c[0]->name ); } ?></p>
		<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p><?php echo esc_html( get_the_excerpt() ); ?></p>
		<a class="ci-link" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Read article', 'cirilinfo' ); ?> →</a>
	</div>
</article>
