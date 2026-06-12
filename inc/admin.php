<?php
/**
 * WordPress admin settings panel for PubWeb.
 *
 * A human-facing UI over the same settings tree the REST API edits, so
 * the theme is configurable from wp-admin (Appearance → PubWeb) as well
 * as headlessly. Capability-gated (manage_options) and nonce-protected.
 *
 * @package PubWeb
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'admin_menu',
	static function (): void {
		$hook = add_theme_page(
			__( 'PubWeb', 'pubweb' ),
			__( 'PubWeb', 'pubweb' ),
			'manage_options',
			'pubweb',
			'pubweb_render_admin_page'
		);
		add_action( "load-$hook", 'pubweb_handle_admin_save' );
		add_action(
			"admin_print_styles-$hook",
			static function (): void {
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
			}
		);
	}
);

/**
 * Process the settings form on POST (runs before the page renders).
 */
function pubweb_handle_admin_save(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['pubweb_nonce'] ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pubweb_nonce'] ) ), 'pubweb_save' ) ) {
		wp_die( esc_html__( 'Permission denied.', 'pubweb' ) );
	}

	$in = isset( $_POST['pw'] ) && is_array( $_POST['pw'] ) ? wp_unslash( $_POST['pw'] ) : array();

	$b  = static fn( string $g, string $k ): bool => ! empty( $in[ $g ][ $k ] );
	$s  = static fn( string $g, string $k, string $d = '' ): string => isset( $in[ $g ][ $k ] ) ? sanitize_text_field( (string) $in[ $g ][ $k ] ) : $d;

	$patch = array(
		'branding'    => array(
			'accent_color'      => sanitize_hex_color( $s( 'branding', 'accent_color' ) ) ?: '#1769ff',
			'logo_max_width'    => max( 60, (int) ( $in['branding']['logo_max_width'] ?? 180 ) ),
			'footer_disclaimer' => isset( $in['branding']['footer_disclaimer'] ) ? wp_kses_post( (string) $in['branding']['footer_disclaimer'] ) : '',
		),
		'layout'      => array(
			'homepage_style'     => 'list' === ( $in['layout']['homepage_style'] ?? 'grid' ) ? 'list' : 'grid',
			'card_style'         => 'overlay' === ( $in['layout']['card_style'] ?? 'classic' ) ? 'overlay' : 'classic',
			'posts_columns'      => in_array( (int) ( $in['layout']['posts_columns'] ?? 3 ), array( 2, 3 ), true ) ? (int) $in['layout']['posts_columns'] : 3,
			'excerpt_words'      => max( 8, (int) ( $in['layout']['excerpt_words'] ?? 24 ) ),
			'show_sidebar'       => $b( 'layout', 'show_sidebar' ),
			'sticky_header'      => $b( 'layout', 'sticky_header' ),
			'sticky_shrink'      => $b( 'layout', 'sticky_shrink' ),
			'featured_hero'      => $b( 'layout', 'featured_hero' ),
			'section_heading'    => $b( 'layout', 'section_heading' ),
			'show_category_chip' => $b( 'layout', 'show_category_chip' ),
			'back_to_top'        => $b( 'layout', 'back_to_top' ),
			'reading_progress'   => $b( 'layout', 'reading_progress' ),
		),
		'colors'      => array(
			'header_bg' => sanitize_hex_color( $s( 'colors', 'header_bg' ) ) ?: '#ffffff',
			'footer_bg' => sanitize_hex_color( $s( 'colors', 'footer_bg' ) ) ?: '#0e1116',
			'body_bg'   => sanitize_hex_color( $s( 'colors', 'body_bg' ) ) ?: '#ffffff',
		),
		'performance' => array(
			'preload_gpt'       => $b( 'performance', 'preload_gpt' ),
			'speculation_rules' => $b( 'performance', 'speculation_rules' ),
			'lazy_images'       => $b( 'performance', 'lazy_images' ),
			'remove_emoji'      => $b( 'performance', 'remove_emoji' ),
			'system_fonts'      => $b( 'performance', 'system_fonts' ),
			'disable_embeds'    => $b( 'performance', 'disable_embeds' ),
		),
		'ads'         => array(
			'enabled'           => $b( 'ads', 'enabled' ),
			'ads_on_homepage'   => $b( 'ads', 'ads_on_homepage' ),
			'gam_network_code'  => preg_replace( '/[^0-9]/', '', $s( 'ads', 'gam_network_code' ) ),
			'loader_script_url' => esc_url_raw( $s( 'ads', 'loader_script_url' ) ),
			'label_text'        => $s( 'ads', 'label_text', 'Anúncios' ),
		),
		'schema'      => array(
			'enabled'        => $b( 'schema', 'enabled' ),
			'org_name'       => $s( 'schema', 'org_name' ),
			'publisher_type' => 'NewsMediaOrganization' === ( $in['schema']['publisher_type'] ?? '' ) ? 'NewsMediaOrganization' : 'Organization',
			'article_type'   => in_array( $in['schema']['article_type'] ?? '', array( 'Article', 'BlogPosting', 'NewsArticle' ), true ) ? $in['schema']['article_type'] : 'Article',
		),
		'seo'         => array(
			'enabled'      => $b( 'seo', 'enabled' ),
			'twitter_site' => $s( 'seo', 'twitter_site' ),
		),
		'custom_code' => array(
			'head_html'   => isset( $in['custom_code']['head_html'] ) ? (string) $in['custom_code']['head_html'] : '',
			'footer_html' => isset( $in['custom_code']['footer_html'] ) ? (string) $in['custom_code']['footer_html'] : '',
			'custom_css'  => isset( $in['custom_code']['custom_css'] ) ? wp_strip_all_tags( (string) $in['custom_code']['custom_css'] ) : '',
		),
		'updater'     => array(
			'enabled'      => $b( 'updater', 'enabled' ),
			'manifest_url' => esc_url_raw( $s( 'updater', 'manifest_url' ) ),
		),
	);

	PubWeb_Settings::update( $patch );
	add_settings_error( 'pubweb', 'saved', __( 'Settings saved.', 'pubweb' ), 'updated' );
	set_transient( 'pubweb_admin_notice', 1, 30 );
}

/** Render the settings page. */
function pubweb_render_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$g       = static fn( string $key, $d = '' ) => pubweb_settings( $key, $d );
	$checked = static fn( $v ): string => $v ? ' checked' : '';
	$saved   = get_transient( 'pubweb_admin_notice' );
	delete_transient( 'pubweb_admin_notice' );
	$token_set = ( defined( 'PUBWEB_AI_TOKEN' ) && '' !== (string) PUBWEB_AI_TOKEN ) || '' !== (string) get_option( 'pubweb_ai_token_hash', '' );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'PubWeb', 'pubweb' ); ?> <span style="font-size:13px;color:#787c82">v<?php echo esc_html( PUBWEB_VERSION ); ?></span></h1>
		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'pubweb' ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'pubweb_save', 'pubweb_nonce' ); ?>

			<h2 class="title"><?php esc_html_e( 'Branding & Colors', 'pubweb' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Accent color', 'pubweb' ); ?></th><td><input type="text" class="pw-color" name="pw[branding][accent_color]" value="<?php echo esc_attr( $g( 'branding.accent_color' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Header background', 'pubweb' ); ?></th><td><input type="text" class="pw-color" name="pw[colors][header_bg]" value="<?php echo esc_attr( $g( 'colors.header_bg' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Footer background', 'pubweb' ); ?></th><td><input type="text" class="pw-color" name="pw[colors][footer_bg]" value="<?php echo esc_attr( $g( 'colors.footer_bg' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'Logo max width (px)', 'pubweb' ); ?></th><td><input type="number" name="pw[branding][logo_max_width]" value="<?php echo esc_attr( $g( 'branding.logo_max_width', 180 ) ); ?>" min="60" max="400"></td></tr>
				<tr><th><?php esc_html_e( 'Footer disclaimer', 'pubweb' ); ?></th><td><textarea name="pw[branding][footer_disclaimer]" rows="2" class="large-text"><?php echo esc_textarea( $g( 'branding.footer_disclaimer' ) ); ?></textarea></td></tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Layout', 'pubweb' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Card style', 'pubweb' ); ?></th><td>
					<select name="pw[layout][card_style]">
						<option value="classic"<?php selected( $g( 'layout.card_style' ), 'classic' ); ?>><?php esc_html_e( 'Classic (image + text)', 'pubweb' ); ?></option>
						<option value="overlay"<?php selected( $g( 'layout.card_style' ), 'overlay' ); ?>><?php esc_html_e( 'Overlay poster (text over image)', 'pubweb' ); ?></option>
					</select></td></tr>
				<tr><th><?php esc_html_e( 'Desktop columns', 'pubweb' ); ?></th><td>
					<select name="pw[layout][posts_columns]">
						<option value="2"<?php selected( (int) $g( 'layout.posts_columns' ), 2 ); ?>>2</option>
						<option value="3"<?php selected( (int) $g( 'layout.posts_columns' ), 3 ); ?>>3</option>
					</select></td></tr>
				<tr><th><?php esc_html_e( 'Excerpt words', 'pubweb' ); ?></th><td><input type="number" name="pw[layout][excerpt_words]" value="<?php echo esc_attr( $g( 'layout.excerpt_words', 24 ) ); ?>" min="8" max="60"></td></tr>
				<?php
				$toggles = array(
					'layout.featured_hero'      => __( 'Featured hero post on homepage', 'pubweb' ),
					'layout.section_heading'    => __( 'Section heading with accent bar', 'pubweb' ),
					'layout.show_category_chip' => __( 'Colored category chip on cards', 'pubweb' ),
					'layout.sticky_header'      => __( 'Sticky header', 'pubweb' ),
					'layout.sticky_shrink'      => __( 'Shrink header on scroll', 'pubweb' ),
					'layout.show_sidebar'       => __( 'Show sidebar on posts/archives', 'pubweb' ),
					'layout.back_to_top'        => __( 'Back-to-top button', 'pubweb' ),
					'layout.reading_progress'   => __( 'Reading-progress bar on posts', 'pubweb' ),
				);
				foreach ( $toggles as $path => $label ) :
					[ $grp, $key ] = explode( '.', $path );
					?>
					<tr><th><?php echo esc_html( $label ); ?></th><td><label><input type="checkbox" name="pw[<?php echo esc_attr( $grp ); ?>][<?php echo esc_attr( $key ); ?>]" value="1"<?php echo $checked( $g( $path ) ); ?>></label></td></tr>
				<?php endforeach; ?>
			</table>

			<h2 class="title"><?php esc_html_e( 'Performance', 'pubweb' ); ?></h2>
			<table class="form-table" role="presentation">
				<?php
				$perf = array(
					'performance.system_fonts'      => __( 'System fonts (no web-font latency)', 'pubweb' ),
					'performance.preload_gpt'       => __( 'Preload GPT (warm ad stack)', 'pubweb' ),
					'performance.speculation_rules' => __( 'Speculation Rules (prefetch next page)', 'pubweb' ),
					'performance.lazy_images'       => __( 'Lazy-load images', 'pubweb' ),
					'performance.remove_emoji'      => __( 'Remove emoji script', 'pubweb' ),
					'performance.disable_embeds'    => __( 'Disable oEmbed discovery', 'pubweb' ),
				);
				foreach ( $perf as $path => $label ) :
					[ $grp, $key ] = explode( '.', $path );
					?>
					<tr><th><?php echo esc_html( $label ); ?></th><td><label><input type="checkbox" name="pw[<?php echo esc_attr( $grp ); ?>][<?php echo esc_attr( $key ); ?>]" value="1"<?php echo $checked( $g( $path ) ); ?>></label></td></tr>
				<?php endforeach; ?>
			</table>

			<h2 class="title"><?php esc_html_e( 'Ads', 'pubweb' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Enable ads', 'pubweb' ); ?></th><td><label><input type="checkbox" name="pw[ads][enabled]" value="1"<?php echo $checked( $g( 'ads.enabled' ) ); ?>></label></td></tr>
				<tr><th><?php esc_html_e( 'Ads on homepage', 'pubweb' ); ?></th><td><label><input type="checkbox" name="pw[ads][ads_on_homepage]" value="1"<?php echo $checked( $g( 'ads.ads_on_homepage' ) ); ?>></label> <span class="description"><?php esc_html_e( 'Off keeps the lander fast.', 'pubweb' ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'GAM network code', 'pubweb' ); ?></th><td><input type="text" name="pw[ads][gam_network_code]" value="<?php echo esc_attr( $g( 'ads.gam_network_code' ) ); ?>" class="regular-text" placeholder="21885211673"></td></tr>
				<tr><th><?php esc_html_e( 'Loader script URL', 'pubweb' ); ?></th><td><input type="url" name="pw[ads][loader_script_url]" value="<?php echo esc_attr( $g( 'ads.loader_script_url' ) ); ?>" class="large-text" placeholder="https://scr.actview.net/&lt;domain&gt;.js"></td></tr>
				<tr><th><?php esc_html_e( 'Ad label', 'pubweb' ); ?></th><td><input type="text" name="pw[ads][label_text]" value="<?php echo esc_attr( $g( 'ads.label_text', 'Anúncios' ) ); ?>"></td></tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Schema & SEO', 'pubweb' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Auto JSON-LD schema', 'pubweb' ); ?></th><td><label><input type="checkbox" name="pw[schema][enabled]" value="1"<?php echo $checked( $g( 'schema.enabled' ) ); ?>></label></td></tr>
				<tr><th><?php esc_html_e( 'Organization name', 'pubweb' ); ?></th><td><input type="text" name="pw[schema][org_name]" value="<?php echo esc_attr( $g( 'schema.org_name' ) ); ?>" class="regular-text"></td></tr>
				<tr><th><?php esc_html_e( 'Publisher type', 'pubweb' ); ?></th><td>
					<select name="pw[schema][publisher_type]">
						<option value="Organization"<?php selected( $g( 'schema.publisher_type' ), 'Organization' ); ?>>Organization</option>
						<option value="NewsMediaOrganization"<?php selected( $g( 'schema.publisher_type' ), 'NewsMediaOrganization' ); ?>>NewsMediaOrganization</option>
					</select></td></tr>
				<tr><th><?php esc_html_e( 'Article type', 'pubweb' ); ?></th><td>
					<select name="pw[schema][article_type]">
						<?php foreach ( array( 'Article', 'BlogPosting', 'NewsArticle' ) as $t ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>"<?php selected( $g( 'schema.article_type' ), $t ); ?>><?php echo esc_html( $t ); ?></option>
						<?php endforeach; ?>
					</select></td></tr>
				<tr><th><?php esc_html_e( 'Theme meta/OG tags', 'pubweb' ); ?></th><td><label><input type="checkbox" name="pw[seo][enabled]" value="1"<?php echo $checked( $g( 'seo.enabled' ) ); ?>></label> <span class="description"><?php esc_html_e( 'Auto-disabled when an SEO plugin is active.', 'pubweb' ); ?></span></td></tr>
				<tr><th><?php esc_html_e( 'Twitter @site', 'pubweb' ); ?></th><td><input type="text" name="pw[seo][twitter_site]" value="<?php echo esc_attr( $g( 'seo.twitter_site' ) ); ?>"></td></tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Custom code', 'pubweb' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'Head HTML', 'pubweb' ); ?></th><td><textarea name="pw[custom_code][head_html]" rows="3" class="large-text code"><?php echo esc_textarea( $g( 'custom_code.head_html' ) ); ?></textarea><p class="description"><?php esc_html_e( 'Ad/analytics tags. Output verbatim — admin trust.', 'pubweb' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'Footer HTML', 'pubweb' ); ?></th><td><textarea name="pw[custom_code][footer_html]" rows="3" class="large-text code"><?php echo esc_textarea( $g( 'custom_code.footer_html' ) ); ?></textarea></td></tr>
				<tr><th><?php esc_html_e( 'Custom CSS', 'pubweb' ); ?></th><td><textarea name="pw[custom_code][custom_css]" rows="4" class="large-text code"><?php echo esc_textarea( $g( 'custom_code.custom_css' ) ); ?></textarea></td></tr>
			</table>

			<h2 class="title"><?php esc_html_e( 'Headless API & Updates', 'pubweb' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( 'API status', 'pubweb' ); ?></th><td>
					<?php if ( $token_set ) : ?>
						<span style="color:#008a20">●</span> <?php esc_html_e( 'Enabled — namespace pubweb/v1 (token configured).', 'pubweb' ); ?>
					<?php else : ?>
						<span style="color:#d63638">●</span> <?php esc_html_e( 'Disabled — set PUBWEB_AI_TOKEN in wp-config.php or rotate a token.', 'pubweb' ); ?>
					<?php endif; ?>
				</td></tr>
				<tr><th><?php esc_html_e( 'Self-hosted updates', 'pubweb' ); ?></th><td><label><input type="checkbox" name="pw[updater][enabled]" value="1"<?php echo $checked( $g( 'updater.enabled' ) ); ?>></label></td></tr>
				<tr><th><?php esc_html_e( 'Update manifest URL', 'pubweb' ); ?></th><td><input type="url" name="pw[updater][manifest_url]" value="<?php echo esc_attr( $g( 'updater.manifest_url' ) ); ?>" class="large-text"></td></tr>
			</table>

			<?php submit_button( __( 'Save settings', 'pubweb' ) ); ?>
		</form>
	</div>
	<script>jQuery(function($){ $('.pw-color').wpColorPicker(); });</script>
	<?php
}
