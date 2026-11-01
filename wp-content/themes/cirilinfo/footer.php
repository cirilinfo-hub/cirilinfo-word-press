<?php
/**
 * Site footer.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
?>
</main>
<footer class="ci-footer">
	<div class="ci-container ci-footer__grid">
		<div class="ci-footer__brand">
			<?php cirilinfo_logo( true ); ?>
			<p><?php echo esc_html( cirilinfo_setting( 'tagline', 'Your Digital Growth Partner' ) ); ?></p>
			<p class="ci-footer__blurb"><?php esc_html_e( 'We help businesses establish, grow and strengthen their online presence through innovative web solutions and result-oriented digital marketing.', 'cirilinfo' ); ?></p>
			<?php echo cirilinfo_core() ? \CirilInfo\Render::social_links() : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<div>
			<h3><?php esc_html_e( 'Company', 'cirilinfo' ); ?></h3>
			<?php wp_nav_menu( array( 'theme_location' => 'footer_company', 'container' => false, 'menu_class' => 'ci-footer__list', 'fallback_cb' => '__return_empty_string' ) ); ?>
		</div>
		<div>
			<h3><?php esc_html_e( 'Services', 'cirilinfo' ); ?></h3>
			<ul class="ci-footer__list">
				<?php foreach ( cirilinfo_core() ? \CirilInfo\Render::services_query( 7 ) : array() as $s ) : ?>
					<li><a href="<?php echo esc_url( get_permalink( $s ) ); ?>"><?php echo esc_html( get_the_title( $s ) ); ?></a></li>
				<?php endforeach; ?>
			</ul>
		</div>
		<div>
			<h3><?php esc_html_e( 'Get in touch', 'cirilinfo' ); ?></h3>
			<ul class="ci-footer__list ci-footer__contact">
				<?php if ( cirilinfo_setting( 'phone' ) ) : ?><li><a href="<?php echo esc_url( \CirilInfo\Render::tel_url() ); ?>"><?php echo esc_html( cirilinfo_setting( 'phone' ) ); ?></a></li><?php endif; ?>
				<?php if ( cirilinfo_core() && \CirilInfo\Settings::business_email() ) : ?><li><a href="mailto:<?php echo esc_attr( \CirilInfo\Settings::business_email() ); ?>"><?php echo esc_html( \CirilInfo\Settings::business_email() ); ?></a></li><?php endif; ?>
				<?php if ( cirilinfo_setting( 'address' ) ) : ?><li><?php echo esc_html( cirilinfo_setting( 'address' ) ); ?></li><?php endif; ?>
			</ul>
			<h3 class="ci-footer__sub"><?php esc_html_e( 'Newsletter', 'cirilinfo' ); ?></h3>
			<?php echo cirilinfo_sc( '[ci_newsletter]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
	</div>
	<div class="ci-footer__bar">
		<div class="ci-container ci-footer__bar-in">
			<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( cirilinfo_setting( 'company_name', get_bloginfo( 'name' ) ) ); ?>. <?php esc_html_e( 'All rights reserved.', 'cirilinfo' ); ?></span>
			<?php wp_nav_menu( array( 'theme_location' => 'footer_legal', 'container' => false, 'menu_class' => 'ci-footer__legal', 'fallback_cb' => '__return_empty_string' ) ); ?>
		</div>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
