<?php
/**
 * Shown when a query returns no posts.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="no-results">
	<h1 class="page-title"><?php esc_html_e( 'Nothing found', 'pubweb' ); ?></h1>
	<p><?php esc_html_e( 'Try a search instead.', 'pubweb' ); ?></p>
	<?php get_search_form(); ?>
</section>
