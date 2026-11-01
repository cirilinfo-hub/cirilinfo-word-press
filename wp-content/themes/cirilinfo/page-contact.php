<?php
/**
 * Contact (slug: contact).
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
cirilinfo_page_hero( get_the_title(), __( 'Tell us about your project – we usually reply within one business day.', 'cirilinfo' ), __( 'Contact', 'cirilinfo' ) );
?>
<section class="ci-section"><div class="ci-container ci-split">
	<div>
		<h2><?php esc_html_e( 'Get in touch', 'cirilinfo' ); ?></h2>
		<?php echo cirilinfo_sc( '[ci_contact_info]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<div class="ci-mt"><?php echo cirilinfo_sc( '[ci_whatsapp]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<div class="ci-mt ci-social-dark"><?php echo cirilinfo_sc( '[ci_social]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
	</div>
	<?php echo cirilinfo_sc( '[ci_enquiry_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php $map = cirilinfo_sc( '[ci_map]' ); if ( $map ) : ?><div class="ci-container ci-mt"><?php echo $map; // phpcs:ignore WordPress.Security.EscapeOutput ?></div><?php endif; ?>
</section>
<?php
get_footer();
