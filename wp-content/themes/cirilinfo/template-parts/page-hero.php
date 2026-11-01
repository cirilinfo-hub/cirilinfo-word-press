<?php
/**
 * Inner-page hero with breadcrumb.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
$a = wp_parse_args( $args ?? array(), array( 'title' => '', 'sub' => '', 'eyebrow' => '' ) );
?>
<section class="ci-pagehero">
	<div class="ci-container">
		<?php echo cirilinfo_core() ? \CirilInfo\Seo::breadcrumb_html() : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		<?php if ( $a['eyebrow'] ) : ?><p class="ci-eyebrow"><?php echo esc_html( $a['eyebrow'] ); ?></p><?php endif; ?>
		<h1><?php echo esc_html( $a['title'] ); ?></h1>
		<?php if ( $a['sub'] ) : ?><p class="ci-lead"><?php echo esc_html( $a['sub'] ); ?></p><?php endif; ?>
	</div>
</section>
