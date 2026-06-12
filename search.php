<?php
/**
 * Search results template.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="content-area">
	<main class="site-main" role="main">
		<header class="archive-header">
			<h1 class="archive-title">
				<?php
				/* translators: %s: search query. */
				printf( esc_html__( 'Results for: %s', 'pubweb' ), '<span>' . esc_html( get_search_query() ) . '</span>' );
				?>
			</h1>
		</header>

		<?php if ( have_posts() ) : ?>
			<div class="post-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/card' );
				endwhile;
				?>
			</div>
			<?php the_posts_pagination(); ?>
		<?php else : ?>
			<?php get_template_part( 'template-parts/content-none' ); ?>
		<?php endif; ?>
	</main>
</div>
<?php
get_footer();
