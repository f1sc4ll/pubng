<?php
/**
 * Main template — blog index and archive fallback.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="content-area<?php echo pubweb_has_sidebar() ? ' has-aside' : ''; ?>">
	<main class="site-main" role="main">
		<?php if ( have_posts() ) : ?>

			<?php if ( is_archive() ) : ?>
				<header class="archive-header">
					<h1 class="archive-title"><?php the_archive_title(); ?></h1>
					<?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
				</header>
			<?php endif; ?>

			<div class="post-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/card' );
				endwhile;
				?>
			</div>

			<?php
			the_posts_pagination( array(
				'mid_size'  => 1,
				'prev_text' => __( 'Previous', 'pubweb' ),
				'next_text' => __( 'Next', 'pubweb' ),
			) );
			?>

		<?php else : ?>
			<?php get_template_part( 'template-parts/content-none' ); ?>
		<?php endif; ?>
	</main>

	<?php if ( pubweb_has_sidebar() ) : ?>
		<?php get_sidebar(); ?>
	<?php endif; ?>
</div>
<?php
get_footer();
