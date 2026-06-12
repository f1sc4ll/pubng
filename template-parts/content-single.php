<?php
/**
 * Single post body. In-content ad slots are auto-injected by the ads
 * module's the_content filter; before/after slots are placed here.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'single-article' ); ?>>
	<header class="single-header">
		<?php
		$pubweb_cats = get_the_category();
		if ( ! empty( $pubweb_cats ) ) :
			?>
			<a class="single-cat" href="<?php echo esc_url( get_category_link( $pubweb_cats[0]->term_id ) ); ?>"><?php echo esc_html( $pubweb_cats[0]->name ); ?></a>
		<?php endif; ?>

		<h1 class="single-title"><?php the_title(); ?></h1>

		<div class="single-meta">
			<?php pubweb_entry_meta(); ?>
			<span class="reading-time"> · <?php printf( esc_html__( '%d min read', 'pubweb' ), pubweb_reading_time() ); ?></span>
		</div>
	</header>

	<?php pubweb_ad_slot( 'before_content' ); ?>

	<?php if ( has_post_thumbnail() ) : ?>
		<figure class="single-featured">
			<?php the_post_thumbnail( 'large', array( 'fetchpriority' => 'high' ) ); ?>
		</figure>
	<?php endif; ?>

	<div class="single-content entry-content">
		<?php
		the_content();
		wp_link_pages( array(
			'before' => '<nav class="page-links">' . esc_html__( 'Pages:', 'pubweb' ),
			'after'  => '</nav>',
		) );
		?>
	</div>

	<?php pubweb_ad_slot( 'after_content' ); ?>

	<footer class="single-footer">
		<?php the_tags( '<div class="tags">', '', '</div>' ); ?>
	</footer>
</article>

<?php
if ( comments_open() || get_comments_number() ) {
	comments_template();
}
