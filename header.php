<?php
/**
 * Site header and document head.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php
	// Operator-supplied head code (ad/analytics tags) — admin-trust, raw.
	$pubweb_head = (string) pubweb_settings( 'custom_code.head_html' );
	if ( '' !== $pubweb_head ) {
		echo $pubweb_head . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	$pubweb_css = (string) pubweb_settings( 'custom_code.custom_css' );
	if ( '' !== $pubweb_css ) {
		echo '<style id="pubweb-custom-css">' . wp_strip_all_tags( $pubweb_css ) . '</style>' . "\n";
	}
	wp_head();
	?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#content"><?php esc_html_e( 'Skip to content', 'pubweb' ); ?></a>

<header class="site-header" role="banner">
	<div class="container header-inner">
		<div class="site-branding"><?php pubweb_branding(); ?></div>

		<button class="nav-toggle" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Menu', 'pubweb' ); ?>">
			<span class="nav-toggle__bar"></span>
			<span class="nav-toggle__bar"></span>
			<span class="nav-toggle__bar"></span>
		</button>

		<nav class="primary-nav" role="navigation" aria-label="<?php esc_attr_e( 'Primary', 'pubweb' ); ?>">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'menu_id'        => 'primary-menu',
				'container'      => false,
				'fallback_cb'    => false,
				'depth'          => 2,
			) );
			?>
			<button class="search-toggle" aria-label="<?php esc_attr_e( 'Search', 'pubweb' ); ?>" aria-expanded="false">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
			</button>
		</nav>
	</div>
	<div class="header-search" hidden><div class="container"><?php get_search_form(); ?></div></div>
</header>

<?php pubweb_ad_slot( 'header' ); ?>

<div id="content" class="site-content container">
