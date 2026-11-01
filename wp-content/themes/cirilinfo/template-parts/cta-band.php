<?php
/**
 * Call-to-action band.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
$a       = wp_parse_args( $args ?? array(), array( 'title' => '', 'text' => '' ) );
$contact = get_page_by_path( 'contact' );
$wa      = cirilinfo_core() ? \CirilInfo\Render::whatsapp_url( 'Hi CirilInfo, I would like to discuss a project.' ) : '';
?>
<section class="ci-cta">
	<div class="ci-container ci-cta__in">
		<div>
			<h2><?php echo esc_html( $a['title'] ?: __( 'Ready to grow your business online?', 'cirilinfo' ) ); ?></h2>
			<p><?php echo esc_html( $a['text'] ?: __( 'Tell us about your project and get a free, no-obligation quote within one business day.', 'cirilinfo' ) ); ?></p>
		</div>
		<div class="ci-cta__btns">
			<a class="ci-btn ci-btn--accent" href="<?php echo esc_url( $contact ? get_permalink( $contact ) . '#enquiry' : home_url( '/contact/' ) ); ?>"><?php esc_html_e( 'Get a free quote', 'cirilinfo' ); ?> <?php echo cirilinfo_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
			<?php if ( $wa ) : ?><a class="ci-btn ci-btn--ghost-light" href="<?php echo esc_url( $wa ); ?>" target="_blank" rel="noopener"><?php echo cirilinfo_icon( 'whatsapp', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> WhatsApp</a><?php endif; ?>
		</div>
	</div>
</section>
