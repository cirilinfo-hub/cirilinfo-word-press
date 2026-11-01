<?php
/**
 * Single portfolio project.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id   = get_the_ID();
	$url  = (string) get_post_meta( $id, '_ci_url', true );
	$tech = wp_get_post_terms( $id, 'ci_tech', array( 'fields' => 'names' ) );
	cirilinfo_page_hero( get_the_title(), '', __( 'Case study', 'cirilinfo' ) );
	?>
	<section class="ci-section"><div class="ci-container ci-split ci-split--top">
		<article class="ci-content"><?php if ( has_post_thumbnail() ) { the_post_thumbnail( 'large', array( 'class' => 'ci-rounded' ) ); } the_content(); ?></article>
		<aside class="ci-sidecard">
			<?php if ( get_post_meta( $id, '_ci_client', true ) ) : ?><p><strong><?php esc_html_e( 'Client', 'cirilinfo' ); ?></strong><br><?php echo esc_html( get_post_meta( $id, '_ci_client', true ) ); ?></p><?php endif; ?>
			<?php if ( get_post_meta( $id, '_ci_result', true ) ) : ?><p><strong><?php esc_html_e( 'Result', 'cirilinfo' ); ?></strong><br><?php echo esc_html( get_post_meta( $id, '_ci_result', true ) ); ?></p><?php endif; ?>
			<?php if ( $tech && ! is_wp_error( $tech ) ) : ?><p><strong><?php esc_html_e( 'Technologies', 'cirilinfo' ); ?></strong></p><ul class="ci-tags"><?php foreach ( $tech as $t ) : ?><li><?php echo esc_html( $t ); ?></li><?php endforeach; ?></ul><?php endif; ?>
			<?php if ( $url ) : ?><a class="ci-btn ci-btn--primary ci-btn--block" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Visit live project', 'cirilinfo' ); ?></a><?php endif; ?>
		</aside>
	</div></section>
	<?php
endwhile;
cirilinfo_cta_band( __( 'Want results like this?', 'cirilinfo' ) );
get_footer();
