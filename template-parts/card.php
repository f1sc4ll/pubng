<?php
/**
 * Post card used in the homepage / archive grid.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;
?>
<article <?php post_class( 'card' ); ?>>
	<a class="card__link" href="<?php the_permalink(); ?>">
		<?php if ( has_post_thumbnail() ) : ?>
			<div class="card__media">
				<?php
				the_post_thumbnail(
					'pubweb-card',
					array(
						'class'   => 'card__img',
						'loading' => 'lazy',
						'alt'     => the_title_attribute( array( 'echo' => false ) ),
					)
				);
				?>
			</div>
		<?php endif; ?>
		<div class="card__body">
			<?php
			$pubweb_cats = get_the_category();
			if ( ! empty( $pubweb_cats ) ) :
				?>
				<span class="card__cat"><?php echo esc_html( $pubweb_cats[0]->name ); ?></span>
			<?php endif; ?>
			<h2 class="card__title"><?php the_title(); ?></h2>
			<p class="card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php pubweb_entry_meta(); ?>
		</div>
	</a>
</article>
