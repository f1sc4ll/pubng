<?php
/**
 * WordPress admin settings panel for PubWeb.
 *
 * Tabbed UI over the settings tree, with a live preview, media-library
 * logo upload, AI-translation provider config, and token generation.
 * Capability-gated (manage_options) and nonce-protected. The same tree
 * is editable headlessly via the pubweb/v1 REST API.
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
			"admin_enqueue_scripts",
			static function ( $h ) use ( $hook ): void {
				if ( $h !== $hook ) {
					return;
				}
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_media();
			}
		);
	}
);

/** Process the settings form / token generation on POST. */
function pubweb_handle_admin_save(): void {
	if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
		return;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Token generation (separate action).
	if ( isset( $_POST['pubweb_token_nonce'] ) ) {
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pubweb_token_nonce'] ) ), 'pubweb_token' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'pubweb' ) );
		}
		if ( defined( 'PUBWEB_AI_TOKEN' ) && '' !== (string) PUBWEB_AI_TOKEN ) {
			set_transient( 'pubweb_admin_notice_err', __( 'Token is pinned by the PUBWEB_AI_TOKEN constant in wp-config.php.', 'pubweb' ), 30 );
			return;
		}
		$token = PubWeb_AI_Auth::rotate();
		set_transient( 'pubweb_new_token', $token, 60 );
		return;
	}

	if ( ! isset( $_POST['pubweb_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pubweb_nonce'] ) ), 'pubweb_save' ) ) {
		return;
	}

	$in = isset( $_POST['pw'] ) && is_array( $_POST['pw'] ) ? wp_unslash( $_POST['pw'] ) : array();
	$b  = static fn( string $g, string $k ): bool => ! empty( $in[ $g ][ $k ] );
	$s  = static fn( string $g, string $k, string $d = '' ): string => isset( $in[ $g ][ $k ] ) ? sanitize_text_field( (string) $in[ $g ][ $k ] ) : $d;

	$langs = array();
	if ( ! empty( $in['translation']['target_langs'] ) ) {
		foreach ( explode( ',', (string) $in['translation']['target_langs'] ) as $l ) {
			$l = sanitize_text_field( trim( $l ) );
			if ( '' !== $l ) {
				$langs[] = $l;
			}
		}
	}

	$patch = array(
		'branding'    => array(
			'accent_color'      => sanitize_hex_color( $s( 'branding', 'accent_color' ) ) ?: '#1769ff',
			'logo_id'           => max( 0, (int) ( $in['branding']['logo_id'] ?? 0 ) ),
			'logo_max_width'    => max( 60, (int) ( $in['branding']['logo_max_width'] ?? 180 ) ),
			'footer_disclaimer' => isset( $in['branding']['footer_disclaimer'] ) ? wp_kses_post( (string) $in['branding']['footer_disclaimer'] ) : '',
		),
		'colors'      => array(
			'header_bg' => sanitize_hex_color( $s( 'colors', 'header_bg' ) ) ?: '#ffffff',
			'footer_bg' => sanitize_hex_color( $s( 'colors', 'footer_bg' ) ) ?: '#0e1116',
			'body_bg'   => sanitize_hex_color( $s( 'colors', 'body_bg' ) ) ?: '#ffffff',
		),
		'layout'      => array(
			'home_variant'       => in_array( $in['layout']['home_variant'] ?? 'grid', array( 'grid', 'feed', 'magazine' ), true ) ? $in['layout']['home_variant'] : 'grid',
			'single_variant'     => in_array( $in['layout']['single_variant'] ?? 'centered', array( 'centered', 'sidebar', 'landing' ), true ) ? $in['layout']['single_variant'] : 'centered',
			'archive_variant'    => in_array( $in['layout']['archive_variant'] ?? 'grid', array( 'grid', 'list', 'headlines' ), true ) ? $in['layout']['archive_variant'] : 'grid',
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
		'performance' => array(
			'speculation_rules' => $b( 'performance', 'speculation_rules' ),
			'lazy_images'       => $b( 'performance', 'lazy_images' ),
			'remove_emoji'      => $b( 'performance', 'remove_emoji' ),
			'system_fonts'      => $b( 'performance', 'system_fonts' ),
			'disable_embeds'    => $b( 'performance', 'disable_embeds' ),
		),
		'translation' => array(
			'provider'       => in_array( $in['translation']['provider'] ?? 'none', array( 'none', 'openai', 'grok', 'claude', 'openrouter' ), true ) ? $in['translation']['provider'] : 'none',
			'api_key'        => isset( $in['translation']['api_key'] ) ? trim( (string) $in['translation']['api_key'] ) : '',
			'model'          => $s( 'translation', 'model' ),
			'source_lang'    => $s( 'translation', 'source_lang', 'en' ),
			'target_langs'   => $langs,
			'auto_translate' => $b( 'translation', 'auto_translate' ),
		),
		// Raw <script> in head/footer requires unfiltered_html (matters on
		// multisite, where manage_options is not super-admin).
		'custom_code' => array(
			'head_html'   => isset( $in['custom_code']['head_html'] ) ? ( current_user_can( 'unfiltered_html' ) ? (string) $in['custom_code']['head_html'] : wp_kses_post( (string) $in['custom_code']['head_html'] ) ) : '',
			'footer_html' => isset( $in['custom_code']['footer_html'] ) ? ( current_user_can( 'unfiltered_html' ) ? (string) $in['custom_code']['footer_html'] : wp_kses_post( (string) $in['custom_code']['footer_html'] ) ) : '',
			'custom_css'  => isset( $in['custom_code']['custom_css'] ) ? wp_strip_all_tags( (string) $in['custom_code']['custom_css'] ) : '',
		),
		'updater'     => array(
			'enabled'      => $b( 'updater', 'enabled' ),
			'manifest_url' => esc_url_raw( $s( 'updater', 'manifest_url' ) ),
		),
	);

	PubWeb_Settings::update( $patch );
	set_transient( 'pubweb_admin_notice', 1, 30 );
}

/** Render the settings page. */
function pubweb_render_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$g       = static fn( string $key, $d = '' ) => pubweb_settings( $key, $d );
	$ck      = static fn( $v ): string => $v ? ' checked' : '';
	$saved   = get_transient( 'pubweb_admin_notice' );
	$new_tok = get_transient( 'pubweb_new_token' );
	$err     = get_transient( 'pubweb_admin_notice_err' );
	delete_transient( 'pubweb_admin_notice' );
	delete_transient( 'pubweb_new_token' );
	delete_transient( 'pubweb_admin_notice_err' );
	$token_set = ( defined( 'PUBWEB_AI_TOKEN' ) && '' !== (string) PUBWEB_AI_TOKEN ) || '' !== (string) get_option( 'pubweb_ai_token_hash', '' );
	$logo_id   = (int) $g( 'branding.logo_id' );
	$logo_url  = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
	// Registered nav menus (for the interactive preview menu picker).
	$pw_menu_data = array();
	foreach ( wp_get_nav_menus() as $pw_m ) {
		$pw_items  = wp_get_nav_menu_items( $pw_m->term_id );
		$pw_labels = array();
		if ( is_array( $pw_items ) ) {
			foreach ( array_slice( $pw_items, 0, 6 ) as $pw_it ) {
				$pw_labels[] = $pw_it->title;
			}
		}
		$pw_menu_data[] = array(
			'id'    => (int) $pw_m->term_id,
			'name'  => $pw_m->name,
			'items' => $pw_labels,
		);
	}
	$tabs      = array(
		'design'  => __( 'Design', 'pubweb' ),
		'layout'  => __( 'Layout', 'pubweb' ),
		'perf'    => __( 'Performance', 'pubweb' ),
		'i18n'    => __( 'Translation', 'pubweb' ),
		'code'    => __( 'Custom code', 'pubweb' ),
		'api'     => __( 'API & Updates', 'pubweb' ),
	);
	?>
	<div class="wrap pubweb-admin">
		<h1><?php esc_html_e( 'PubWeb', 'pubweb' ); ?> <span style="font-size:13px;color:#787c82">v<?php echo esc_html( PUBWEB_VERSION ); ?></span></h1>
		<?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'pubweb' ); ?></p></div><?php endif; ?>
		<?php if ( $err ) : ?><div class="notice notice-warning is-dismissible"><p><?php echo esc_html( $err ); ?></p></div><?php endif; ?>
		<?php if ( $new_tok ) : ?>
			<div class="notice notice-success"><p><strong><?php esc_html_e( 'New API token (shown once — copy it now):', 'pubweb' ); ?></strong><br>
			<code style="font-size:14px;user-select:all"><?php echo esc_html( $new_tok ); ?></code></p></div>
		<?php endif; ?>

		<div class="pubweb-grid" style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start">
		<div>
			<h2 class="nav-tab-wrapper">
				<?php foreach ( $tabs as $id => $label ) : ?>
					<a href="#" class="nav-tab pw-tab-link" data-tab="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</h2>

			<form method="post" id="pubweb-form">
				<?php wp_nonce_field( 'pubweb_save', 'pubweb_nonce' ); ?>

				<div class="pw-tab" data-tab="design">
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'Logo', 'pubweb' ); ?></th><td>
							<img class="pw-logo-preview" src="<?php echo esc_url( $logo_url ); ?>" alt="" style="max-width:200px;max-height:60px;display:<?php echo $logo_url ? 'block' : 'none'; ?>;margin-bottom:8px">
							<input type="hidden" name="pw[branding][logo_id]" class="pw-logo-id" value="<?php echo esc_attr( $logo_id ); ?>">
							<button type="button" class="button pw-logo-upload"><?php esc_html_e( 'Select / upload logo', 'pubweb' ); ?></button>
							<button type="button" class="button-link pw-logo-remove" style="margin-left:8px;color:#b32d2e"><?php esc_html_e( 'Remove', 'pubweb' ); ?></button>
						</td></tr>
						<tr><th><?php esc_html_e( 'Logo max width (px)', 'pubweb' ); ?></th><td><input type="number" class="pw-live" data-prop="logoW" name="pw[branding][logo_max_width]" value="<?php echo esc_attr( $g( 'branding.logo_max_width', 180 ) ); ?>" min="60" max="400"></td></tr>
						<tr><th><?php esc_html_e( 'Accent color', 'pubweb' ); ?></th><td><input type="text" class="pw-color pw-live" data-prop="accent" name="pw[branding][accent_color]" value="<?php echo esc_attr( $g( 'branding.accent_color' ) ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Header background', 'pubweb' ); ?></th><td><input type="text" class="pw-color pw-live" data-prop="headerBg" name="pw[colors][header_bg]" value="<?php echo esc_attr( $g( 'colors.header_bg' ) ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Footer background', 'pubweb' ); ?></th><td><input type="text" class="pw-color pw-live" data-prop="footerBg" name="pw[colors][footer_bg]" value="<?php echo esc_attr( $g( 'colors.footer_bg' ) ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Body background', 'pubweb' ); ?></th><td><input type="text" class="pw-color pw-live" data-prop="bodyBg" name="pw[colors][body_bg]" value="<?php echo esc_attr( $g( 'colors.body_bg' ) ); ?>"></td></tr>
						<tr><th><?php esc_html_e( 'Footer disclaimer', 'pubweb' ); ?></th><td><textarea name="pw[branding][footer_disclaimer]" rows="2" class="large-text"><?php echo esc_textarea( $g( 'branding.footer_disclaimer' ) ); ?></textarea></td></tr>
					</table>
				</div>

				<div class="pw-tab" data-tab="layout" hidden>
					<table class="form-table" role="presentation">
						<tr><th colspan="2" style="padding-bottom:0"><strong><?php esc_html_e( 'Layout per page type', 'pubweb' ); ?></strong> <span class="description"><?php esc_html_e( '(each template has its own model — see the reference winners)', 'pubweb' ); ?></span></th></tr>
						<tr><th><?php esc_html_e( 'Home', 'pubweb' ); ?></th><td>
							<select name="pw[layout][home_variant]" class="pw-live" data-prop="homeVar">
								<option value="grid"<?php selected( $g( 'layout.home_variant' ), 'grid' ); ?>><?php esc_html_e( 'Grid (hero + card grid)', 'pubweb' ); ?></option>
								<option value="feed"<?php selected( $g( 'layout.home_variant' ), 'feed' ); ?>><?php esc_html_e( 'Feed (fast text list, no thumbs)', 'pubweb' ); ?></option>
								<option value="magazine"<?php selected( $g( 'layout.home_variant' ), 'magazine' ); ?>><?php esc_html_e( 'Magazine (full-width lead + grid)', 'pubweb' ); ?></option>
							</select></td></tr>
						<tr><th><?php esc_html_e( 'Post', 'pubweb' ); ?></th><td>
							<select name="pw[layout][single_variant]" class="pw-live" data-prop="singleVar">
								<option value="centered"<?php selected( $g( 'layout.single_variant' ), 'centered' ); ?>><?php esc_html_e( 'Centered (720px, no sidebar)', 'pubweb' ); ?></option>
								<option value="sidebar"<?php selected( $g( 'layout.single_variant' ), 'sidebar' ); ?>><?php esc_html_e( 'With right sidebar', 'pubweb' ); ?></option>
								<option value="landing"<?php selected( $g( 'layout.single_variant' ), 'landing' ); ?>><?php esc_html_e( 'Landing (minimal chrome)', 'pubweb' ); ?></option>
							</select></td></tr>
						<tr><th><?php esc_html_e( 'Category / archive', 'pubweb' ); ?></th><td>
							<select name="pw[layout][archive_variant]" class="pw-live" data-prop="archiveVar">
								<option value="grid"<?php selected( $g( 'layout.archive_variant' ), 'grid' ); ?>><?php esc_html_e( 'Grid (same as home)', 'pubweb' ); ?></option>
								<option value="list"<?php selected( $g( 'layout.archive_variant' ), 'list' ); ?>><?php esc_html_e( 'List rows (thumb + text)', 'pubweb' ); ?></option>
								<option value="headlines"<?php selected( $g( 'layout.archive_variant' ), 'headlines' ); ?>><?php esc_html_e( 'Headlines (title only)', 'pubweb' ); ?></option>
							</select></td></tr>
						<tr><th colspan="2" style="padding-bottom:0"><strong><?php esc_html_e( 'Shared options', 'pubweb' ); ?></strong></th></tr>
						<tr><th><?php esc_html_e( 'Card style', 'pubweb' ); ?></th><td>
							<select name="pw[layout][card_style]" class="pw-live" data-prop="cardStyle">
								<option value="classic"<?php selected( $g( 'layout.card_style' ), 'classic' ); ?>><?php esc_html_e( 'Classic (image + text)', 'pubweb' ); ?></option>
								<option value="overlay"<?php selected( $g( 'layout.card_style' ), 'overlay' ); ?>><?php esc_html_e( 'Overlay poster', 'pubweb' ); ?></option>
							</select></td></tr>
						<tr><th><?php esc_html_e( 'Desktop columns', 'pubweb' ); ?></th><td>
							<select name="pw[layout][posts_columns]"><option value="2"<?php selected( (int) $g( 'layout.posts_columns' ), 2 ); ?>>2</option><option value="3"<?php selected( (int) $g( 'layout.posts_columns' ), 3 ); ?>>3</option></select></td></tr>
						<tr><th><?php esc_html_e( 'Excerpt words', 'pubweb' ); ?></th><td><input type="number" name="pw[layout][excerpt_words]" value="<?php echo esc_attr( $g( 'layout.excerpt_words', 24 ) ); ?>" min="8" max="60"></td></tr>
						<?php
						$toggles = array(
							'layout.featured_hero'      => array( __( 'Featured hero on homepage', 'pubweb' ), '' ),
							'layout.section_heading'    => array( __( 'Section heading with accent bar', 'pubweb' ), 'sectionHeading' ),
							'layout.show_category_chip' => array( __( 'Colored category chip on cards', 'pubweb' ), 'chip' ),
							'layout.sticky_header'      => array( __( 'Sticky header', 'pubweb' ), '' ),
							'layout.sticky_shrink'      => array( __( 'Shrink header on scroll', 'pubweb' ), '' ),
							'layout.show_sidebar'       => array( __( 'Show sidebar on posts/archives', 'pubweb' ), '' ),
							'layout.back_to_top'        => array( __( 'Back-to-top button', 'pubweb' ), '' ),
							'layout.reading_progress'   => array( __( 'Reading-progress bar on posts', 'pubweb' ), '' ),
						);
						foreach ( $toggles as $path => $meta ) :
							[ $grp, $key ] = explode( '.', $path );
							?>
							<tr><th><?php echo esc_html( $meta[0] ); ?></th><td><label><input type="checkbox"<?php echo $meta[1] ? ' class="pw-live" data-prop="' . esc_attr( $meta[1] ) . '"' : ''; ?> name="pw[<?php echo esc_attr( $grp ); ?>][<?php echo esc_attr( $key ); ?>]" value="1"<?php echo $ck( $g( $path ) ); ?>></label></td></tr>
						<?php endforeach; ?>
					</table>
				</div>

				<div class="pw-tab" data-tab="perf" hidden>
					<table class="form-table" role="presentation">
						<?php
						$perf = array(
							'performance.system_fonts'      => __( 'System fonts (no web-font latency)', 'pubweb' ),
							'performance.speculation_rules' => __( 'Speculation Rules (prefetch next page)', 'pubweb' ),
							'performance.lazy_images'       => __( 'Lazy-load images', 'pubweb' ),
							'performance.remove_emoji'      => __( 'Remove emoji script', 'pubweb' ),
							'performance.disable_embeds'    => __( 'Disable oEmbed discovery', 'pubweb' ),
						);
						foreach ( $perf as $path => $label ) :
							[ $grp, $key ] = explode( '.', $path );
							?>
							<tr><th><?php echo esc_html( $label ); ?></th><td><label><input type="checkbox" name="pw[<?php echo esc_attr( $grp ); ?>][<?php echo esc_attr( $key ); ?>]" value="1"<?php echo $ck( $g( $path ) ); ?>></label></td></tr>
						<?php endforeach; ?>
						<tr><td colspan="2"><p class="description"><?php esc_html_e( 'Ad delivery is handled automatically by your ad stack (Ad Inserter / network loader) — the theme ships no ad code.', 'pubweb' ); ?></p></td></tr>
					</table>
				</div>

				<div class="pw-tab" data-tab="i18n" hidden>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'AI provider', 'pubweb' ); ?></th><td>
							<select name="pw[translation][provider]">
								<?php foreach ( PubWeb_AI_Translate::provider_labels() as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>"<?php selected( $g( 'translation.provider' ), $val ); ?>><?php echo esc_html( $label ); ?></option>
								<?php endforeach; ?>
							</select></td></tr>
						<tr><th><?php esc_html_e( 'API key', 'pubweb' ); ?></th><td><input type="password" name="pw[translation][api_key]" value="<?php echo esc_attr( $g( 'translation.api_key' ) ); ?>" class="large-text" autocomplete="off"></td></tr>
						<tr><th><?php esc_html_e( 'Model', 'pubweb' ); ?></th><td><input type="text" name="pw[translation][model]" value="<?php echo esc_attr( $g( 'translation.model' ) ); ?>" class="regular-text" placeholder="gpt-4o-mini / grok-2-latest / claude-3-5-haiku-latest"></td></tr>
						<tr><th><?php esc_html_e( 'Source language', 'pubweb' ); ?></th><td><input type="text" name="pw[translation][source_lang]" value="<?php echo esc_attr( $g( 'translation.source_lang', 'en' ) ); ?>" placeholder="en"></td></tr>
						<tr><th><?php esc_html_e( 'Target languages', 'pubweb' ); ?></th><td><input type="text" name="pw[translation][target_langs]" value="<?php echo esc_attr( implode( ', ', (array) $g( 'translation.target_langs', array() ) ) ); ?>" class="regular-text" placeholder="pt-BR, es, fr"><p class="description"><?php esc_html_e( 'Comma-separated BCP-47 codes.', 'pubweb' ); ?></p></td></tr>
						<tr><th><?php esc_html_e( 'Auto-translate new posts', 'pubweb' ); ?></th><td><label><input type="checkbox" name="pw[translation][auto_translate]" value="1"<?php echo $ck( $g( 'translation.auto_translate' ) ); ?>></label></td></tr>
						<tr><td colspan="2"><p class="description"><?php esc_html_e( 'Translate via POST /wp-json/pubweb/v1/translate {text, target}. Keys are stored in the database (admin-trust).', 'pubweb' ); ?></p></td></tr>
					</table>
				</div>

				<div class="pw-tab" data-tab="code" hidden>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'Head HTML', 'pubweb' ); ?></th><td><textarea name="pw[custom_code][head_html]" rows="3" class="large-text code"><?php echo esc_textarea( $g( 'custom_code.head_html' ) ); ?></textarea></td></tr>
						<tr><th><?php esc_html_e( 'Footer HTML', 'pubweb' ); ?></th><td><textarea name="pw[custom_code][footer_html]" rows="3" class="large-text code"><?php echo esc_textarea( $g( 'custom_code.footer_html' ) ); ?></textarea></td></tr>
						<tr><th><?php esc_html_e( 'Custom CSS', 'pubweb' ); ?></th><td><textarea name="pw[custom_code][custom_css]" rows="4" class="large-text code"><?php echo esc_textarea( $g( 'custom_code.custom_css' ) ); ?></textarea></td></tr>
					</table>
				</div>

				<div class="pw-tab" data-tab="api" hidden>
					<table class="form-table" role="presentation">
						<tr><th><?php esc_html_e( 'API status', 'pubweb' ); ?></th><td>
							<?php if ( $token_set ) : ?><span style="color:#008a20">●</span> <?php esc_html_e( 'Enabled — namespace pubweb/v1.', 'pubweb' ); ?>
							<?php else : ?><span style="color:#d63638">●</span> <?php esc_html_e( 'Disabled — generate a token below or set PUBWEB_AI_TOKEN in wp-config.php.', 'pubweb' ); ?><?php endif; ?>
						</td></tr>
						<tr><th><?php esc_html_e( 'Self-hosted updates', 'pubweb' ); ?></th><td><label><input type="checkbox" name="pw[updater][enabled]" value="1"<?php echo $ck( $g( 'updater.enabled' ) ); ?>></label></td></tr>
						<tr><th><?php esc_html_e( 'Update manifest URL', 'pubweb' ); ?></th><td><input type="url" name="pw[updater][manifest_url]" value="<?php echo esc_attr( $g( 'updater.manifest_url' ) ); ?>" class="large-text"></td></tr>
					</table>
				</div>

				<?php submit_button( __( 'Save settings', 'pubweb' ) ); ?>
			</form>

			<div class="pw-tab" data-tab="api" hidden>
				<hr>
				<h3><?php esc_html_e( 'Token', 'pubweb' ); ?></h3>
				<p class="description"><?php esc_html_e( 'The token is the credential for the pubweb/v1 API. Only its SHA-256 hash is stored; the plaintext is shown once on generation. Or pin PUBWEB_AI_TOKEN in wp-config.php.', 'pubweb' ); ?></p>
				<form method="post">
					<?php wp_nonce_field( 'pubweb_token', 'pubweb_token_nonce' ); ?>
					<?php submit_button( $token_set ? __( 'Regenerate token', 'pubweb' ) : __( 'Generate token', 'pubweb' ), 'secondary', 'submit', false ); ?>
					<span class="description" style="margin-left:8px"><?php esc_html_e( 'Regenerating invalidates the previous token.', 'pubweb' ); ?></span>
				</form>
			</div>
		</div>

		<!-- Live preview: scrollable mobile mockups per page type -->
		<div class="pw-preview-wrap" style="position:sticky;top:40px">
			<div class="pw-pv-tabs" style="display:flex;gap:4px;margin-bottom:8px;justify-content:center">
				<button type="button" class="button button-small pw-pv-tab" data-pv="home"><?php esc_html_e( 'Home', 'pubweb' ); ?></button>
				<button type="button" class="button button-small pw-pv-tab" data-pv="post"><?php esc_html_e( 'Post', 'pubweb' ); ?></button>
				<button type="button" class="button button-small pw-pv-tab" data-pv="category"><?php esc_html_e( 'Category', 'pubweb' ); ?></button>
			</div>
			<div class="pw-phone" style="width:300px;margin:0 auto;background:#111418;border-radius:28px;padding:12px 9px;box-shadow:0 8px 28px rgba(0,0,0,.22)">
				<div style="width:90px;height:5px;background:#2b3038;border-radius:3px;margin:1px auto 9px"></div>
				<div class="pw-phone-screen" style="height:540px;overflow-y:auto;border-radius:18px;background:#fff;font-family:system-ui,-apple-system,sans-serif;font-size:13px;line-height:1.45;color:#16181d"></div>
			</div>
			<p class="description" style="text-align:center;margin-top:8px"><?php esc_html_e( 'Mobile preview — switch page type, scroll inside.', 'pubweb' ); ?></p>
			<div style="margin-top:10px;font-size:12px">
				<label><?php esc_html_e( 'Preview menu', 'pubweb' ); ?>:
					<select class="pw-menu-select">
						<option value="">— <?php esc_html_e( 'none', 'pubweb' ); ?> —</option>
						<?php foreach ( $pw_menu_data as $pw_md ) : ?>
							<option value="<?php echo esc_attr( $pw_md['id'] ); ?>"><?php echo esc_html( $pw_md['name'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<a class="pw-menu-edit" href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>" target="_blank" rel="noopener" style="margin-left:8px"><?php esc_html_e( 'Edit menus', 'pubweb' ); ?> →</a>
				<?php if ( empty( $pw_menu_data ) ) : ?>
					<p class="description"><?php esc_html_e( 'No menus yet — create one in Appearance → Menus.', 'pubweb' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		</div>
	</div>

	<script>
	jQuery(function ($) {
		// ---- Settings tabs (left form) ----
		var $links = $('.pw-tab-link'), $tabs = $('.pw-tab');
		function showTab(tab) {
			$links.removeClass('nav-tab-active').filter('[data-tab="' + tab + '"]').addClass('nav-tab-active');
			$tabs.attr('hidden', true).filter('[data-tab="' + tab + '"]').removeAttr('hidden');
		}
		$links.on('click', function (e) { e.preventDefault(); showTab($(this).data('tab')); });
		showTab('design');

		// ---- Live mobile preview ----
		var SITE = <?php echo wp_json_encode( get_bloginfo( 'name' ) ); ?>;
		var PW_TXT = <?php echo wp_json_encode( array(
			'latest'        => __( 'Latest', 'pubweb' ),
			'related'       => __( 'Related', 'pubweb' ),
			'mostRead'      => __( 'Most read', 'pubweb' ),
			'menu'          => __( 'Menu', 'pubweb' ),
			'sampleCat'     => __( 'Finance', 'pubweb' ),
			'sampleTitle'   => __( ''+PW_TXT.sampleTitle+'', 'pubweb' ),
			'sampleExcerpt' => __( ''+PW_TXT.sampleExcerpt+'', 'pubweb' ),
			'sampleHero'    => __( ''+PW_TXT.sampleHero+'', 'pubweb' ),
			'latestInCat'   => __( ''+PW_TXT.latestInCat+'', 'pubweb' ),
			/* translators: %s: sample author name in the preview. */
			'byline'        => sprintf( __( 'By %s', 'pubweb' ), 'Megan Caldwell' ) . ' · ' . gmdate( 'M Y' ),
			/* translators: %d: sample reading time in minutes. */
			'minRead'       => sprintf( __( '%d min read', 'pubweb' ), 4 ),
		) ); ?>;
		var PW_MENUS = <?php echo wp_json_encode( $pw_menu_data ); ?>;
		var MENU_EDIT = <?php echo wp_json_encode( admin_url( 'nav-menus.php?action=edit&menu=' ) ); ?>;
		var MENU_BASE = <?php echo wp_json_encode( admin_url( 'nav-menus.php' ) ); ?>;
		var pvMenu = null;
		var pvType = 'home';
		function v(sel){ return $(sel).val(); }
		function st(){
			return {
				accent: v('input[data-prop="accent"]') || '#1769ff',
				headerBg: v('input[data-prop="headerBg"]') || '#ffffff',
				footerBg: v('input[data-prop="footerBg"]') || '#0e1116',
				bodyBg: v('input[data-prop="bodyBg"]') || '#ffffff',
				overlay: v('select[data-prop="cardStyle"]') === 'overlay',
				chip: $('input[data-prop="chip"]').is(':checked'),
				heading: $('input[data-prop="sectionHeading"]').is(':checked'),
				hero: $('input[name="pw[layout][featured_hero]"]').is(':checked'),
				homeVar: v('select[data-prop="homeVar"]') || 'grid',
				singleVar: v('select[data-prop="singleVar"]') || 'centered',
				archiveVar: v('select[data-prop="archiveVar"]') || 'grid',
				logo: $('.pw-logo-preview').is(':visible') ? $('.pw-logo-preview').attr('src') : ''
			};
		}
		function esc(x){ return String(x).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
		function header(s){
			var b = s.logo ? '<img src="'+esc(s.logo)+'" style="max-height:22px;max-width:120px">' : '<strong style="font-size:14px;font-weight:900">'+esc(SITE)+'</strong>';
			var nav = (pvMenu && pvMenu.items.length) ? pvMenu.items.slice(0,3).map(esc).join(' · ') : (esc(PW_TXT.menu)+' ≡');
			return '<div style="display:flex;align-items:center;padding:10px 12px;border-bottom:1px solid #eceef1;background:'+s.headerBg+'">'+b+'<span style="margin-left:auto;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:'+s.accent+'">'+nav+'</span></div>';
		}
		function footer(s){ return '<div style="padding:14px 12px;font-size:10px;color:#c5c9d1;background:'+s.footerBg+'">© '+esc(SITE)+'</div>'; }
		function chip(s){ return s.chip ? '<span style="display:inline-block;font-size:9px;font-weight:800;text-transform:uppercase;color:#fff;background:'+s.accent+';padding:2px 7px;border-radius:4px;margin-bottom:5px">'+PW_TXT.sampleCat+'</span><br>' : ''; }
		function heading(s,t){ return s.heading ? '<div style="font-size:11px;text-transform:uppercase;letter-spacing:.1em;font-weight:800;margin:0 0 10px"><span style="display:inline-block;padding-bottom:5px;border-bottom:3px solid '+s.accent+'">'+t+'</span></div>' : ''; }
		function lines(n){ var h=''; for(var i=0;i<n;i++){ h+='<div style="height:8px;background:#eceef1;border-radius:3px;margin:6px 0;width:'+(i%3===2?'70%':'100%')+'"></div>'; } return h; }
		function img(h){ return '<div style="height:'+h+'px;background:linear-gradient(135deg,#dfe3ea,#cfd5dd)"></div>'; }
		function excerpt(){ return '<div style="color:#52565e;font-size:11px;margin-top:3px">'+PW_TXT.sampleExcerpt+'</div>'; }
		function cardClassic(s,ih){ return '<div style="border:1px solid #eceef1;border-radius:10px;overflow:hidden;margin-bottom:12px">'+img(ih||95)+'<div style="padding:10px 11px">'+chip(s)+'<div style="font-weight:800;font-size:14px">'+PW_TXT.sampleTitle+'</div>'+excerpt()+'</div></div>'; }
		function cardOverlay(s){ return '<div style="position:relative;border-radius:10px;overflow:hidden;margin-bottom:12px;min-height:120px;background:linear-gradient(180deg,rgba(0,0,0,.12),rgba(0,0,0,.72)),linear-gradient(135deg,#5a6270,#2b3340)"><div style="position:absolute;left:0;right:0;bottom:0;padding:11px">'+chip(s)+'<div style="font-weight:800;font-size:14px;color:#fff">'+PW_TXT.sampleTitle+'</div></div></div>'; }
		function card(s){ return s.overlay ? cardOverlay(s) : cardClassic(s); }
		function mockHome(s){
			var h = header(s) + '<div style="padding:13px">';
			if (s.hero){ h += '<div style="border-radius:10px;overflow:hidden;margin-bottom:14px">'+img(110)+'<div style="padding:10px 0">'+chip(s)+'<div style="font-weight:900;font-size:16px">'+PW_TXT.sampleHero+'</div>'+excerpt()+'</div></div>'; }
			h += heading(s,PW_TXT.latest);
			if (s.homeVar==='feed'){ for(var i=0;i<4;i++){ h+='<div style="padding:11px 0;border-bottom:1px solid #eceef1">'+chip(s)+'<div style="font-weight:800;font-size:13px">'+PW_TXT.sampleTitle+'</div>'+excerpt()+'</div>'; } }
			else if (s.homeVar==='magazine'){ h += card(s) + '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'+cardClassic(s,55)+cardClassic(s,55)+'</div>'; }
			else { h += card(s)+card(s)+card(s); }
			return h + '</div>' + footer(s);
		}
		function mockPost(s){
			var landing = s.singleVar==='landing';
			var h = header(s) + '<div style="padding:14px">';
			h += chip(s) + '<div style="font-weight:900;font-size:19px;line-height:1.2">'+PW_TXT.sampleTitle+'</div>';
			h += '<div style="font-size:10px;color:#80858d;margin:6px 0 12px">By Megan Caldwell · Jun 2026'+(landing?'':' · 4 min read')+'</div>';
			if (!landing){ h += '<div style="border-radius:10px;overflow:hidden;margin-bottom:12px">'+img(130)+'</div>'; }
			h += lines(7);
			if (s.singleVar==='sidebar'){ h += '<div style="margin-top:14px;padding:11px;background:#f6f7f9;border-radius:10px"><div style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#80858d;font-weight:800;margin-bottom:8px">'+PW_TXT.mostRead+'</div>'+lines(4)+'</div>'; }
			if (!landing){ h += '<div style="margin-top:16px">'+heading(s,PW_TXT.related)+'<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">'+cardClassic(s,55)+cardClassic(s,55)+'</div></div>'; }
			return h + '</div>' + footer(s);
		}
		function mockCategory(s){
			var h = header(s) + '<div style="padding:14px"><div style="font-weight:900;font-size:18px;margin-bottom:4px">'+PW_TXT.sampleCat+'</div><div style="font-size:11px;color:#80858d;margin-bottom:14px">'+PW_TXT.latestInCat+'</div>';
			if (s.archiveVar==='list'){ for(var i=0;i<4;i++){ h+='<div style="display:flex;gap:10px;padding:10px 0;border-bottom:1px solid #eceef1"><div style="flex:0 0 90px">'+img(50)+'</div><div>'+chip(s)+'<div style="font-weight:800;font-size:12px">'+PW_TXT.sampleTitle+'</div></div></div>'; } }
			else if (s.archiveVar==='headlines'){ for(var j=0;j<5;j++){ h+='<div style="padding:9px 0;border-bottom:1px solid #eceef1;font-weight:700;font-size:12px">'+PW_TXT.sampleTitle+'</div>'; } }
			else { h += card(s)+card(s)+card(s); }
			return h + '</div>' + footer(s);
		}
		function renderPhone(){
			var s = st();
			var html = pvType==='post' ? mockPost(s) : (pvType==='category' ? mockCategory(s) : mockHome(s));
			$('.pw-phone-screen').css('background', s.bodyBg).html(html);
			$('.pw-pv-tab').css('box-shadow','none').filter('[data-pv="'+pvType+'"]').css('box-shadow','inset 0 -3px 0 '+s.accent);
		}
		$('.pw-pv-tab').on('click', function(){ pvType = $(this).data('pv'); renderPhone(); });
		$('.pw-menu-select').on('change', function(){
			var id = $(this).val(); pvMenu = null;
			for (var i = 0; i < PW_MENUS.length; i++) { if (String(PW_MENUS[i].id) === id) { pvMenu = PW_MENUS[i]; } }
			$('.pw-menu-edit').attr('href', id ? MENU_EDIT + id : MENU_BASE);
			renderPhone();
		});
		$('.pw-color').wpColorPicker({ change: function(){ setTimeout(renderPhone, 30); } });
		$('#pubweb-form').on('change input', 'select, input[type=checkbox], input[type=number]', renderPhone);
		renderPhone();

		// ---- Logo media uploader ----
		var frame;
		$('.pw-logo-upload').on('click', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({ title: '<?php echo esc_js( __( 'Select logo', 'pubweb' ) ); ?>', button: { text: '<?php echo esc_js( __( 'Use logo', 'pubweb' ) ); ?>' }, multiple: false });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				$('.pw-logo-id').val(a.id);
				$('.pw-logo-preview').attr('src', a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url).show();
				renderPhone();
			});
			frame.open();
		});
		$('.pw-logo-remove').on('click', function (e) { e.preventDefault(); $('.pw-logo-id').val(0); $('.pw-logo-preview').hide(); renderPhone(); });
	});
	</script>
	<?php
}
