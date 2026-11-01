<?php
/**
 * About us (slug: about).
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();
cirilinfo_page_hero( get_the_title(), cirilinfo_setting( 'seo_default_desc', '' ), __( 'About CirilInfo', 'cirilinfo' ) );
?>
<section class="ci-section"><div class="ci-container ci-split">
	<div><p class="ci-eyebrow"><?php esc_html_e( 'Company profile', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'Your digital growth partner', 'cirilinfo' ); ?></h2>
	<p><?php esc_html_e( 'CirilInfo is a website development and digital marketing company. We help businesses establish, grow and strengthen their online presence through innovative web solutions and result-oriented digital marketing services.', 'cirilinfo' ); ?></p>
	<p><?php esc_html_e( 'Our team brings together designers, developers, SEO specialists and marketers who plan, build and promote your digital presence as one accountable team.', 'cirilinfo' ); ?></p>
	<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?></div>
	<div class="ci-grid ci-grid--2">
		<div class="ci-feature"><span class="ci-feature__icon"><?php echo cirilinfo_icon( 'target', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><h3><?php esc_html_e( 'Our mission', 'cirilinfo' ); ?></h3><p><?php esc_html_e( 'To make professional web and digital marketing services accessible, transparent and effective for every business.', 'cirilinfo' ); ?></p></div>
		<div class="ci-feature"><span class="ci-feature__icon"><?php echo cirilinfo_icon( 'rocket', 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><h3><?php esc_html_e( 'Our vision', 'cirilinfo' ); ?></h3><p><?php esc_html_e( 'To be the most trusted digital growth partner for small and growing businesses.', 'cirilinfo' ); ?></p></div>
	</div>
</div></section>
<section class="ci-section ci-section--tint"><div class="ci-container">
	<header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'Our expertise', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'What we are good at', 'cirilinfo' ); ?></h2></header>
	<div class="ci-grid ci-grid--3">
		<?php foreach ( array( array( 'code', 'Web development', 'WordPress, WooCommerce, custom web applications and APIs.' ), array( 'design', 'Design & UX', 'Brand identity, UI/UX and conversion-focused layouts.' ), array( 'seo', 'SEO & content', 'Technical SEO, content marketing and local search.' ), array( 'ads', 'Paid & social', 'Google Ads, social media marketing and promotions.' ), array( 'hosting', 'Hosting & care', 'Domains, hosting, backups and maintenance.' ), array( 'chart', 'Analytics', 'Tracking, dashboards and monthly insight reports.' ) ) as $e ) : ?>
			<div class="ci-feature reveal"><span class="ci-feature__icon"><?php echo cirilinfo_icon( $e[0], 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><h3><?php echo esc_html( $e[1] ); ?></h3><p><?php echo esc_html( $e[2] ); ?></p></div>
		<?php endforeach; ?>
	</div>
</div></section>
<section class="ci-section"><div class="ci-container">
	<header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'Development methodology', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'How we work', 'cirilinfo' ); ?></h2></header>
	<ol class="ci-steps4 ci-steps4--light">
		<?php foreach ( array( array( __( 'Discover', 'cirilinfo' ), __( 'Workshops to understand goals, users and constraints.', 'cirilinfo' ) ), array( __( 'Plan', 'cirilinfo' ), __( 'Scope, milestones and timeline you sign off.', 'cirilinfo' ) ), array( __( 'Build', 'cirilinfo' ), __( 'Iterative delivery with demos and progress updates.', 'cirilinfo' ) ), array( __( 'Launch & improve', 'cirilinfo' ), __( 'Go-live, training, measurement and continuous improvement.', 'cirilinfo' ) ) ) as $i => $s ) : ?>
			<li><span><?php echo (int) $i + 1; ?></span><h3><?php echo esc_html( $s[0] ); ?></h3><p><?php echo esc_html( $s[1] ); ?></p></li>
		<?php endforeach; ?>
	</ol>
</div></section>
<?php $team = cirilinfo_sc( '[ci_team]' ); if ( $team ) : ?><section class="ci-section ci-section--tint"><div class="ci-container"><header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'Team', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'Meet the people behind CirilInfo', 'cirilinfo' ); ?></h2></header><?php echo $team; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></section><?php endif; ?>
<?php
cirilinfo_cta_band();
get_footer();
