<?php
/**
 * Pricing & packages (slug: pricing).
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
cirilinfo_page_hero( get_the_title(), __( 'Choose a service, compare Basic, Professional and Enterprise packages, and check out securely online.', 'cirilinfo' ), __( 'Pricing', 'cirilinfo' ) );
?>
<section class="ci-section"><div class="ci-container">
	<?php echo cirilinfo_sc( '[ci_pricing]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<ul class="ci-notes-list ci-mt">
		<li><?php esc_html_e( 'All prices are in Indian Rupees and exclude GST, which is calculated at checkout.', 'cirilinfo' ); ?></li>
		<li><?php esc_html_e( 'Pay securely with UPI, cards, net banking, wallets or bank transfer.', 'cirilinfo' ); ?></li>
		<li><?php esc_html_e( 'Need something different? Request a custom quote on any package.', 'cirilinfo' ); ?></li>
	</ul>
</div></section>
<?php
cirilinfo_cta_band( __( 'Not sure which package fits?', 'cirilinfo' ), __( 'Talk to our team and we will recommend the right plan for your goals and budget.', 'cirilinfo' ) );
get_footer();
