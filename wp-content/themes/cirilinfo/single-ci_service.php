<?php
/**
 * Single service: details, packages with purchase, add-ons, FAQ and enquiry.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
while ( have_posts() ) :
	the_post();
	$id         = get_the_ID();
	$icon       = (string) get_post_meta( $id, '_ci_icon', true ) ?: 'web';
	$highlights = array_filter( array_map( 'trim', preg_split( '/\R/', (string) get_post_meta( $id, '_ci_highlights', true ) ) ) );
	$process    = array_filter( array_map( 'trim', preg_split( '/\R/', (string) get_post_meta( $id, '_ci_process', true ) ) ) );
	cirilinfo_page_hero( get_the_title(), (string) get_post_meta( $id, '_ci_tagline', true ), __( 'Service', 'cirilinfo' ) );
	?>
	<section class="ci-section">
		<div class="ci-container ci-split ci-split--top">
			<div class="ci-content"><?php the_content(); ?>
				<?php if ( $highlights ) : ?><ul class="ci-checks"><?php foreach ( $highlights as $h ) : ?><li><?php echo cirilinfo_icon( 'check', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput ?><span><?php echo esc_html( $h ); ?></span></li><?php endforeach; ?></ul><?php endif; ?>
			</div>
			<aside class="ci-sidecard">
				<span class="ci-service-card__icon"><?php echo cirilinfo_icon( $icon, 30 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				<strong><?php echo esc_html( cirilinfo_core() ? \CirilInfo\Render::from_price( $id ) : '' ); ?></strong>
				<p><?php esc_html_e( 'Choose a package below and check out securely, or ask us for a tailored quote.', 'cirilinfo' ); ?></p>
				<a class="ci-btn ci-btn--primary ci-btn--block" href="#packages"><?php esc_html_e( 'See packages', 'cirilinfo' ); ?></a>
				<a class="ci-btn ci-btn--ghost ci-btn--block" href="#enquiry"><?php esc_html_e( 'Request a quote', 'cirilinfo' ); ?></a>
			</aside>
		</div>
	</section>

	<?php if ( $process ) : ?>
	<section class="ci-section ci-section--tint"><div class="ci-container">
		<header class="ci-sechead"><h2><?php esc_html_e( 'How it works', 'cirilinfo' ); ?></h2></header>
		<ol class="ci-steps4 ci-steps4--light">
			<?php foreach ( array_values( $process ) as $i => $line ) : $p = array_map( 'trim', explode( '|', $line, 2 ) ); ?>
				<li><span><?php echo (int) $i + 1; ?></span><h3><?php echo esc_html( $p[0] ); ?></h3><p><?php echo esc_html( $p[1] ?? '' ); ?></p></li>
			<?php endforeach; ?>
		</ol>
	</div></section>
	<?php endif; ?>

	<section class="ci-section" id="packages"><div class="ci-container">
		<header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'Packages', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'Choose your package', 'cirilinfo' ); ?></h2><p><?php esc_html_e( 'Prices exclude GST. Pick optional add-ons, then buy now – you can submit requirements and track your project from your dashboard.', 'cirilinfo' ); ?></p></header>
		<?php echo do_shortcode( '[ci_packages service="' . (int) $id . '" compare="1"]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div></section>

	<?php $faq = cirilinfo_sc( '[ci_faq limit="5" search="0"]' ); if ( $faq ) : ?><section class="ci-section ci-section--tint"><div class="ci-container"><header class="ci-sechead"><h2><?php esc_html_e( 'Questions? Answered.', 'cirilinfo' ); ?></h2></header><?php echo $faq; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></section><?php endif; ?>

	<section class="ci-section" id="contact"><div class="ci-container ci-split"><div><p class="ci-eyebrow"><?php esc_html_e( 'Custom requirement?', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'Request a tailored quote', 'cirilinfo' ); ?></h2><p class="ci-muted"><?php esc_html_e( 'Describe what you need and upload reference files. We will reply with a detailed proposal.', 'cirilinfo' ); ?></p></div><?php echo do_shortcode( '[ci_enquiry_form type="quote" service="' . (int) $id . '"]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div></section>
	<?php
endwhile;
get_footer();
