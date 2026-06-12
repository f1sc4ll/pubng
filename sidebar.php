<?php
/**
 * Optional right sidebar (enabled via layout.show_sidebar).
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

if ( ! is_active_sidebar( 'sidebar-1' ) ) {
	return;
}
?>
<aside class="sidebar widget-area" role="complementary">
	<?php pubweb_ad_slot( 'sidebar' ); ?>
	<?php dynamic_sidebar( 'sidebar-1' ); ?>
</aside>
