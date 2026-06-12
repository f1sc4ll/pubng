<?php
/**
 * Blog posts index (homepage when set to "Your latest posts", or the
 * dedicated posts page). A featured lead post above the card grid.
 *
 * Note: this never overrides a static front page — that uses page.php.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="content-area">
	<main class="site-main" role="main">
		<?php if ( have_posts() ) : ?>

			<?php if ( ! is_paged() ) : ?>
				<?php the_post(); // Pull the first post for the featured lead. ?>
				<article <?php post_class( 'featured-post' ); ?>>
					<a class="featured-post__link" href="<?php the_permalink(); ?>">
						<?php if ( has_post_thumbnail() ) : ?>
							<div class="featured-post__media">
								<?php the_post_thumbnail( 'large', array( 'fetchpriority' => 'high', 'class' => 'featured-post__img' ) ); ?>
							</div>
						<?php endif; ?>
						<div class="featured-post__body">
							<?php pubweb_category_chip(); ?>
							<h1 class="featured-post__title"><?php the_title(); ?></h1>
							<p class="featured-post__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
							<?php pubweb_entry_meta(); ?>
						</div>
					</a>
				</article>
			<?php endif; ?>

			<?php pubweb_section_heading( __( 'Latest', 'pubweb' ) ); ?>
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
</div>
<?php
get_footer();
