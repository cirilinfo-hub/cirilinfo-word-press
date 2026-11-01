<?php
/**
 * Search form.
 *
 * @package CirilInfo_Theme
 */

defined( 'ABSPATH' ) || exit;
?>
<form role="search" method="get" class="ci-search" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="s-<?php echo esc_attr( wp_unique_id() ); ?>"><?php esc_html_e( 'Search for:', 'cirilinfo' ); ?></label>
	<input type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>" placeholder="<?php esc_attr_e( 'Search…', 'cirilinfo' ); ?>">
	<button type="submit" class="ci-btn ci-btn--primary ci-btn--sm"><?php esc_html_e( 'Search', 'cirilinfo' ); ?></button>
</form>
