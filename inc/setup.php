<?php
/**
 * Theme setup: supports, menus, sidebar, image sizes.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function (): void {
		load_theme_textdomain( 'pubweb', PUBWEB_DIR . 'languages' );

		add_theme_support( 'automatic-feed-links' );
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'custom-logo', array(
			'height'      => 60,
			'width'       => 200,
			'flex-height' => true,
			'flex-width'  => true,
		) );
		add_theme_support( 'html5', array(
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
			'navigation-widgets',
		) );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'align-wide' );

		register_nav_menus( array(
			'primary' => __( 'Primary Menu', 'pubweb' ),
			'footer'  => __( 'Footer Menu', 'pubweb' ),
		) );

		// Card thumbnail tuned for the homepage grid (16:9, cropped).
		add_image_size( 'pubweb-card', 600, 338, true );
	}
);

add_action(
	'widgets_init',
	static function (): void {
		register_sidebar( array(
			'name'          => __( 'Sidebar', 'pubweb' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'Shown on posts/archives when the sidebar layout is enabled.', 'pubweb' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		) );
		register_sidebar( array(
			'name'          => __( 'Footer', 'pubweb' ),
			'id'            => 'footer-1',
			'description'   => __( 'Footer widget area.', 'pubweb' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title">',
			'after_title'   => '</h2>',
		) );
	}
);

/**
 * Content width for embeds/images in the main column.
 */
add_action(
	'after_setup_theme',
	static function (): void {
		$GLOBALS['content_width'] = 760;
	},
	0
);
