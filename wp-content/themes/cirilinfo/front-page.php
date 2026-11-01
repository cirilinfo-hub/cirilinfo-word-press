<?php
/**
 * Home page.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
get_header();

$banner = cirilinfo_core() ? get_posts( array( 'post_type' => 'ci_banner', 'numberposts' => 1, 'meta_query' => array( 'relation' => 'AND', array( 'key' => '_ci_placement', 'value' => 'hero' ), array( 'key' => '_ci_active', 'value' => '1' ) ) ) ) : array(); // phpcs:ignore WordPress.DB.SlowDBQuery
$h1     = $banner ? $banner[0]->post_title : __( 'Websites & digital marketing that grow your business', 'cirilinfo' );
$lead   = $banner ? wp_strip_all_tags( $banner[0]->post_content ) : __( 'CirilInfo helps businesses establish, grow and strengthen their online presence through innovative web solutions and result-oriented digital marketing services.', 'cirilinfo' );
$cta    = $banner ? (string) get_post_meta( $banner[0]->ID, '_ci_cta_text', true ) : '';
$cta_u  = $banner ? (string) get_post_meta( $banner[0]->ID, '_ci_cta_url', true ) : '';
$svc    = get_page_by_path( 'services' );
$pricing = get_page_by_path( 'pricing' );
$contact = get_page_by_path( 'contact' );
?>
<section class="ci-hero">
	<div class="ci-container ci-hero__in">
		<div class="ci-hero__copy">
			<p class="ci-eyebrow ci-eyebrow--light"><?php echo esc_html( cirilinfo_setting( 'tagline', 'Your Digital Growth Partner' ) ); ?></p>
			<h1><?php echo esc_html( $h1 ); ?></h1>
			<p class="ci-lead"><?php echo esc_html( $lead ); ?></p>
			<div class="ci-hero__btns">
				<a class="ci-btn ci-btn--accent" href="<?php echo esc_url( $cta_u ?: ( $pricing ? get_permalink( $pricing ) : '#' ) ); ?>"><?php echo esc_html( $cta ?: __( 'View packages & pricing', 'cirilinfo' ) ); ?> <?php echo cirilinfo_icon( 'arrow', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
				<a class="ci-btn ci-btn--ghost-light" href="<?php echo esc_url( $contact ? get_permalink( $contact ) . '#enquiry' : '#contact' ); ?>"><?php esc_html_e( 'Get a free quote', 'cirilinfo' ); ?></a>
			</div>
			<ul class="ci-hero__trust">
				<li><?php echo cirilinfo_icon( 'shield', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Secure online payments', 'cirilinfo' ); ?></li>
				<li><?php echo cirilinfo_icon( 'bolt', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Fast delivery', 'cirilinfo' ); ?></li>
				<li><?php echo cirilinfo_icon( 'support', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput ?> <?php esc_html_e( 'Dedicated support', 'cirilinfo' ); ?></li>
			</ul>
		</div>
		<?php get_template_part( 'template-parts/hero-art' ); ?>
	</div>
</section>

<section class="ci-section">
	<div class="ci-container">
		<header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'What we do', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'Everything you need to win online', 'cirilinfo' ); ?></h2><p><?php esc_html_e( 'From your first website to full-funnel digital marketing – pick a service, choose a package and get started in minutes.', 'cirilinfo' ); ?></p></header>
		<?php echo cirilinfo_sc( '[ci_services limit="8"]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( $svc ) : ?><p class="ci-center ci-mt"><a class="ci-btn ci-btn--ghost" href="<?php echo esc_url( get_permalink( $svc ) ); ?>"><?php esc_html_e( 'Explore all services', 'cirilinfo' ); ?></a></p><?php endif; ?>
	</div>
</section>

<section class="ci-section ci-section--tint">
	<div class="ci-container">
		<header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'Why choose CirilInfo', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'A partner focused on your growth', 'cirilinfo' ); ?></h2></header>
		<div class="ci-grid ci-grid--4">
			<?php
			$why = array(
				array( 'target', __( 'Result-oriented', 'cirilinfo' ), __( 'Every project starts with your business goals – leads, sales or visibility – and is measured against them.', 'cirilinfo' ) ),
				array( 'rocket', __( 'Fast & transparent', 'cirilinfo' ), __( 'Clear timelines, fixed package prices and live project tracking from your own dashboard.', 'cirilinfo' ) ),
				array( 'shield', __( 'Secure & reliable', 'cirilinfo' ), __( 'Security-first builds, HTTPS, backups and trusted payment gateways – we never store card details.', 'cirilinfo' ) ),
				array( 'users', __( 'One accountable team', 'cirilinfo' ), __( 'Design, development, SEO and marketing under one roof, so everything works together.', 'cirilinfo' ) ),
			);
			foreach ( $why as $w ) :
				?>
				<div class="ci-feature reveal"><span class="ci-feature__icon"><?php echo cirilinfo_icon( $w[0], 26 ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span><h3><?php echo esc_html( $w[1] ); ?></h3><p><?php echo esc_html( $w[2] ); ?></p></div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php $work = cirilinfo_sc( '[ci_portfolio limit="3" filter="0"]' ); if ( $work && false === strpos( $work, 'being updated' ) ) : ?>
<section class="ci-section">
	<div class="ci-container">
		<header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'Featured projects', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'Work we are proud of', 'cirilinfo' ); ?></h2></header>
		<?php echo $work; // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
</section>
<?php endif; ?>

<section class="ci-section ci-section--dark">
	<div class="ci-container">
		<header class="ci-sechead ci-sechead--light"><p class="ci-eyebrow ci-eyebrow--light"><?php esc_html_e( 'Our methodology', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'From idea to launch in four steps', 'cirilinfo' ); ?></h2></header>
		<ol class="ci-steps4">
			<?php foreach ( array( array( __( 'Discover', 'cirilinfo' ), __( 'Goals, audience, competitors and scope.', 'cirilinfo' ) ), array( __( 'Design', 'cirilinfo' ), __( 'Wireframes and visuals you approve.', 'cirilinfo' ) ), array( __( 'Develop', 'cirilinfo' ), __( 'Agile builds with regular updates.', 'cirilinfo' ) ), array( __( 'Deliver & grow', 'cirilinfo' ), __( 'Launch, training, SEO and ongoing support.', 'cirilinfo' ) ) ) as $i => $s ) : ?>
				<li class="reveal"><span><?php echo (int) $i + 1; ?></span><h3><?php echo esc_html( $s[0] ); ?></h3><p><?php echo esc_html( $s[1] ); ?></p></li>
			<?php endforeach; ?>
		</ol>
		<div class="ci-tech" aria-label="<?php esc_attr_e( 'Technology expertise', 'cirilinfo' ); ?>">
			<?php foreach ( array( 'WordPress', 'WooCommerce', 'PHP', 'JavaScript', 'React', 'Angular', 'TypeScript', 'MySQL', 'REST APIs', 'Figma', 'Google Ads', 'GA4' ) as $t ) : ?><span><?php echo esc_html( $t ); ?></span><?php endforeach; ?>
		</div>
	</div>
</section>

<section class="ci-section">
	<div class="ci-container">
		<header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'Pricing highlights', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'Simple packages. Transparent prices.', 'cirilinfo' ); ?></h2><p><?php esc_html_e( 'Website Development packages shown – every service has Basic, Professional and Enterprise options. Prices exclude GST.', 'cirilinfo' ); ?></p></header>
		<?php echo cirilinfo_sc( '[ci_pricing_highlights]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( $pricing ) : ?><p class="ci-center ci-mt"><a class="ci-btn ci-btn--ghost" href="<?php echo esc_url( get_permalink( $pricing ) ); ?>"><?php esc_html_e( 'Compare all packages', 'cirilinfo' ); ?></a></p><?php endif; ?>
	</div>
</section>

<?php $t = cirilinfo_sc( '[ci_testimonials limit="3"]' ); if ( $t ) : ?>
<section class="ci-section ci-section--tint"><div class="ci-container"><header class="ci-sechead"><p class="ci-eyebrow"><?php esc_html_e( 'Testimonials', 'cirilinfo' ); ?></p><h2><?php esc_html_e( 'What our clients say', 'cirilinfo' ); ?></h2></header><?php echo $t; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></section>
<?php endif; ?>

<?php $c = cirilinfo_sc( '[ci_clients]' ); if ( $c ) : ?>
<section class="ci-section ci-section--slim"><div class="ci-container"><p class="ci-center ci-muted"><?php esc_html_e( 'Trusted by growing businesses', 'cirilinfo' ); ?></p><?php echo $c; // phpcs:ignore WordPress.Security.EscapeOutput ?></div></section>
<?php endif; ?>

<?php $faq = cirilinfo_sc( '[ci_faq limit="6" search="0"]' ); if ( $faq ) : ?>
<section class="ci-section ci-section--tint"><div class="ci-container"><header class="ci-sechead"><p class="ci-eyebrow">FAQ</p><h2><?php esc_html_e( 'Frequently asked questions', 'cirilinfo' ); ?></h2></header><?php echo $faq; // phpcs:ignore WordPress.Security.EscapeOutput ?><?php $fp = get_page_by_path( 'faq' ); if ( $fp ) : ?><p class="ci-center ci-mt"><a class="ci-link" href="<?php echo esc_url( get_permalink( $fp ) ); ?>"><?php esc_html_e( 'See all questions', 'cirilinfo' ); ?> →</a></p><?php endif; ?></div></section>
<?php endif; ?>

<section class="ci-section" id="contact">
	<div class="ci-container ci-split">
		<div>
			<p class="ci-eyebrow"><?php esc_html_e( 'Contact & enquiry', 'cirilinfo' ); ?></p>
			<h2><?php esc_html_e( 'Let’s talk about your project', 'cirilinfo' ); ?></h2>
			<p class="ci-muted"><?php esc_html_e( 'Share a few details and we will reply within one business day with ideas and a quote.', 'cirilinfo' ); ?></p>
			<?php echo cirilinfo_sc( '[ci_contact_info]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php echo cirilinfo_sc( '[ci_enquiry_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	</div>
</section>
<?php
get_footer();
