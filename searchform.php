<?php
/**
 * Search form.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="pw-search"><?php esc_html_e( 'Search for:', 'pubweb' ); ?></label>
	<input type="search" id="pw-search" class="search-field" placeholder="<?php esc_attr_e( 'Search…', 'pubweb' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
	<button type="submit" class="search-submit"><?php esc_html_e( 'Search', 'pubweb' ); ?></button>
</form>
