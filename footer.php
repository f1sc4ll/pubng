<?php
/**
 * Site footer.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;
?>
</div><!-- .site-content -->

<?php pubweb_ad_slot( 'footer' ); ?>

<footer class="site-footer" role="contentinfo">
	<div class="container footer-inner">
		<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
			<div class="footer-widgets"><?php dynamic_sidebar( 'footer-1' ); ?></div>
		<?php endif; ?>

		<?php
		if ( has_nav_menu( 'footer' ) ) {
			wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => 'nav',
				'container_class'=> 'footer-nav',
				'depth'          => 1,
				'fallback_cb'    => false,
			) );
		}

		$pubweb_disclaimer = (string) pubweb_settings( 'branding.footer_disclaimer' );
		if ( '' !== $pubweb_disclaimer ) {
			echo '<p class="footer-disclaimer">' . wp_kses_post( $pubweb_disclaimer ) . '</p>';
		}
		?>
		<p class="site-info">&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?></p>
	</div>
</footer>

<?php
// Operator-supplied footer code (ad/conversion tags) — admin-trust, raw.
$pubweb_footer = (string) pubweb_settings( 'custom_code.footer_html' );
if ( '' !== $pubweb_footer ) {
	echo $pubweb_footer . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
}
wp_footer();
?>
</body>
</html>
