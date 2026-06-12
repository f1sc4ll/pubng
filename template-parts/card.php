<?php
/**
 * Post card for the homepage / archive grid.
 *
 * Two looks driven by layout.card_style:
 *  - classic: image on top, then chip + title + excerpt + meta.
 *  - overlay: full-bleed image with a dark scrim and text overlaid
 *    (the "poster" card seen on the reference publisher sites).
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

$pubweb_overlay = ( 'overlay' === pubweb_settings( 'layout.card_style' ) );
$pubweb_thumb   = get_the_post_thumbnail_url( null, 'pubweb-card' );
?>
<article <?php post_class( pubweb_card_class() ); ?><?php
	if ( $pubweb_overlay && $pubweb_thumb ) {
		echo ' style="background-image:url(' . esc_url( $pubweb_thumb ) . ')"';
	}
?>>
	<?php if ( $pubweb_overlay ) : ?>
		<a class="card__link" href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
			<div class="card__overlay">
				<?php pubweb_category_chip(); ?>
				<h2 class="card__title"><?php the_title(); ?></h2>
				<span class="card__more"><?php esc_html_e( 'Read more', 'pubweb' ); ?></span>
			</div>
		</a>
	<?php else : ?>
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
				<?php pubweb_category_chip(); ?>
				<h2 class="card__title"><?php the_title(); ?></h2>
				<p class="card__excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
				<?php pubweb_entry_meta(); ?>
			</div>
		</a>
	<?php endif; ?>
</article>
