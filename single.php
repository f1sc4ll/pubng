<?php
/**
 * Single post template.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="content-area<?php echo pubweb_has_sidebar() ? ' has-aside' : ''; ?>">
	<main class="site-main" role="main">
		<?php
		while ( have_posts() ) :
			the_post();
			get_template_part( 'template-parts/content-single' );
		endwhile;
		?>
	</main>

	<?php if ( pubweb_has_sidebar() ) : ?>
		<?php get_sidebar(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
