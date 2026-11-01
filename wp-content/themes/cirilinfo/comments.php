<?php
/**
 * Comments.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="ci-comments">
	<?php if ( have_comments() ) : ?>
		<h2><?php printf( esc_html( _n( '%d comment', '%d comments', get_comments_number(), 'cirilinfo' ) ), (int) get_comments_number() ); ?></h2>
		<ol class="ci-comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'avatar_size' => 44 ) ); ?></ol>
		<?php the_comments_navigation(); ?>
	<?php endif; ?>
	<?php comment_form(); ?>
</section>
