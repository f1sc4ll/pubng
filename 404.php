<?php
/**
 * 404 template.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="content-area">
	<main class="site-main error-404" role="main">
		<h1 class="page-title"><?php esc_html_e( 'Page not found', 'pubweb' ); ?></h1>
		<p><?php esc_html_e( 'The page you were looking for is not here.', 'pubweb' ); ?></p>
		<?php get_search_form(); ?>
	</main>
</div>
<?php
get_footer();
