<?php
/**
 * Comments template (minimal; comments are off on most ad sites).
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}
?>
<section id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$pubweb_count = get_comments_number();
			/* translators: %s: comment count. */
			printf( esc_html( _n( '%s comment', '%s comments', $pubweb_count, 'pubweb' ) ), esc_html( number_format_i18n( $pubweb_count ) ) );
			?>
		</h2>
		<ol class="comment-list">
			<?php
			wp_list_comments( array(
				'style'      => 'ol',
				'short_ping' => true,
				'avatar_size'=> 40,
			) );
			?>
		</ol>
		<?php the_comments_pagination(); ?>
	<?php endif; ?>

	<?php comment_form(); ?>
</section>
