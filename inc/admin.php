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

		<!-- Live preview -->
		<div class="pw-preview-wrap" style="position:sticky;top:40px">
			<p style="font-weight:600;margin:.4em 0"><?php esc_html_e( 'Live preview', 'pubweb' ); ?></p>
			<div class="pw-pv" style="border:1px solid #dcdcde;border-radius:10px;overflow:hidden;font-family:system-ui,sans-serif">
				<div class="pw-pv-header" style="display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid #eceef1">
					<strong class="pw-pv-logo" style="font-size:15px;font-weight:900"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></strong>
					<span style="margin-left:auto;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em" class="pw-pv-nav">Home · Finance</span>
				</div>
				<div style="padding:14px">
					<h4 class="pw-pv-heading" style="font-size:12px;text-transform:uppercase;letter-spacing:.12em;font-weight:800;margin:0 0 12px"><span style="display:inline-block;padding-bottom:6px;border-bottom:3px solid">Latest</span></h4>
					<div class="pw-pv-card" style="border:1px solid #eceef1;border-radius:10px;overflow:hidden;background-size:cover;background-position:center">
						<div class="pw-pv-card-img" style="height:90px;background:#dfe3ea"></div>
						<div class="pw-pv-card-body" style="padding:11px 12px">
							<span class="pw-pv-chip" style="display:inline-block;font-size:10px;font-weight:800;text-transform:uppercase;color:#fff;padding:2px 8px;border-radius:4px;margin-bottom:6px">Finance</span>
							<div class="pw-pv-card-title" style="font-weight:800;font-size:14px">How credit cards actually work</div>
						</div>
					</div>
					<button class="pw-pv-btn" type="button" style="margin-top:12px;border:0;color:#fff;border-radius:8px;padding:8px 16px;font-weight:700;cursor:default"><?php esc_html_e( 'Accent button', 'pubweb' ); ?></button>
				</div>
				<div class="pw-pv-footer" style="padding:12px 14px;font-size:11px;color:#c5c9d1">© <?php echo esc_html( get_bloginfo( 'name' ) ); ?></div>
			</div>
		</div>
		</div>
	</div>

	<script>
	jQuery(function ($) {
		// Tabs.
		var $links = $('.pw-tab-link'), $tabs = $('.pw-tab');
		function show(tab) {
			$links.removeClass('nav-tab-active').filter('[data-tab="' + tab + '"]').addClass('nav-tab-active');
			$tabs.attr('hidden', true).filter('[data-tab="' + tab + '"]').removeAttr('hidden');
		}
		$links.on('click', function (e) { e.preventDefault(); show($(this).data('tab')); });
		show('design');

		// Live preview.
		var pv = {
			accent: $('input[data-prop="accent"]').val(),
			headerBg: $('input[data-prop="headerBg"]').val(),
			footerBg: $('input[data-prop="footerBg"]').val(),
			bodyBg: $('input[data-prop="bodyBg"]').val()
		};
		function render() {
			var a = pv.accent || '#1769ff';
			$('.pw-pv-header').css('background', pv.headerBg || '#fff');
			$('.pw-pv-footer').css('background', pv.footerBg || '#0e1116');
			$('.pw-pv').css('background', pv.bodyBg || '#fff');
			$('.pw-pv-nav, .pw-pv-heading span').css({ 'color': a, 'border-color': a });
			$('.pw-pv-chip, .pw-pv-btn').css('background', a);
			var overlay = $('select[data-prop="cardStyle"]').val() === 'overlay';
			var feed = $('select[data-prop="homeVar"]').val() === 'feed';
			$('.pw-pv-chip').toggle($('input[data-prop="chip"]').is(':checked'));
			$('.pw-pv-heading').toggle($('input[data-prop="sectionHeading"]').is(':checked'));
			$('.pw-pv-card').css(overlay && !feed ? { 'min-height': '110px', 'background-image': 'linear-gradient(180deg,rgba(0,0,0,.1),rgba(0,0,0,.7)),url(https://picsum.photos/300/160)' } : { 'min-height': '', 'background-image': '' });
			$('.pw-pv-card').css('border', feed ? '0' : '1px solid #eceef1');
			$('.pw-pv-card-img').toggle(!overlay && !feed);
			$('.pw-pv-card-title').css('color', overlay && !feed ? '#fff' : '#16181d');
		}
		$('.pw-color').wpColorPicker({ change: function (e, ui) {
			var prop = $(e.target).data('prop'); if (prop) { pv[prop] = ui.color.toString(); render(); }
		} });
		$('.pw-live').on('change input', function () {
			var p = $(this).data('prop'); if (p && pv.hasOwnProperty(p)) { pv[p] = $(this).val(); } render();
		});
		render();

		// Logo media uploader.
		var frame;
		$('.pw-logo-upload').on('click', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({ title: '<?php echo esc_js( __( 'Select logo', 'pubweb' ) ); ?>', button: { text: '<?php echo esc_js( __( 'Use logo', 'pubweb' ) ); ?>' }, multiple: false });
			frame.on('select', function () {
				var a = frame.state().get('selection').first().toJSON();
				$('.pw-logo-id').val(a.id);
				$('.pw-logo-preview').attr('src', a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url).show();
				$('.pw-pv-logo').text(''); // logo set
			});
			frame.open();
		});
		$('.pw-logo-remove').on('click', function (e) { e.preventDefault(); $('.pw-logo-id').val(0); $('.pw-logo-preview').hide(); });
	});
	</script>
	<?php
}
