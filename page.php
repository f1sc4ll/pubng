<?php
/**
 * Single page template.
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
			?>
			<article <?php post_class( 'single-article page' ); ?>>
				<header class="single-header">
					<h1 class="single-title"><?php the_title(); ?></h1>
				</header>
				<div class="single-content entry-content">
					<?php
					the_content();
					wp_link_pages( array(
						'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'pubweb' ),
						'after'  => '</nav>',
					) );
					?>
				</div>
			</article>
			<?php
			if ( comments_open() || get_comments_number() ) {
				comments_template();
			}
		endwhile;
		?>
	</main>

	<?php if ( pubweb_has_sidebar() ) : ?>
		<?php get_sidebar(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
