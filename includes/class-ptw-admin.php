<?php
/**
 * Admin menus, dashboard, bulk conversion UI, and asset loading.
 *
 * @package PNG_To_WebP_Converter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PTW_Admin
 */
class PTW_Admin {

	/**
	 * Top-level menu slug (dashboard).
	 */
	const DASHBOARD_SLUG = 'ptw-dashboard';

	/**
	 * Bulk conversion submenu slug.
	 */
	const BULK_SLUG = 'ptw-bulk-convert';

	/**
	 * Hook suffixes for the plugin's own admin pages (used to scope asset loading).
	 *
	 * @var array
	 */
	private $plugin_hooks = array();

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register the plugin's admin menu pages.
	 */
	public function register_menus() {
		$this->plugin_hooks[] = add_menu_page(
			__( 'PNG to WebP Converter', 'png-to-webp-converter' ),
			__( 'PNG to WebP', 'png-to-webp-converter' ),
			'upload_files',
			self::DASHBOARD_SLUG,
			array( $this, 'render_dashboard_page' ),
			'dashicons-images-alt2',
			80
		);

		$this->plugin_hooks[] = add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'Convert Images', 'png-to-webp-converter' ),
			__( 'Convert Images', 'png-to-webp-converter' ),
			'upload_files',
			self::BULK_SLUG,
			array( $this, 'render_bulk_page' )
		);

		$this->plugin_hooks[] = add_submenu_page(
			self::DASHBOARD_SLUG,
			__( 'PNG to WebP Settings', 'png-to-webp-converter' ),
			__( 'Settings', 'png-to-webp-converter' ),
			'manage_options',
			PTW_Settings::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin CSS/JS only on the plugin's own pages, plus the Media Library
	 * (which needs the JS for row actions).
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_assets( $hook ) {
		$media_screens = array( 'upload.php', 'post.php' );

		if ( ! in_array( $hook, $this->plugin_hooks, true ) && ! in_array( $hook, $media_screens, true ) ) {
			return;
		}

		// The bulk conversion page needs the core Media Library JS (wp.media / wp.Uploader).
		if ( false !== strpos( (string) $hook, self::BULK_SLUG ) ) {
			wp_enqueue_media();
		}

		wp_enqueue_style(
			'ptw-admin',
			PTW_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			PTW_VERSION
		);

		wp_enqueue_script(
			'ptw-admin',
			PTW_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			PTW_VERSION,
			true
		);

		wp_localize_script(
			'ptw-admin',
			'ptwAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'ptw_ajax_nonce' ),
				'i18n'    => array(
					'converting'        => __( 'Converting…', 'png-to-webp-converter' ),
					'converted'         => __( 'WebP Created', 'png-to-webp-converter' ),
					'failed'            => __( 'Conversion failed', 'png-to-webp-converter' ),
					'confirmRegenerate' => __( 'Regenerate the WebP version of this image?', 'png-to-webp-converter' ),
					'noImagesFound'     => __( 'No convertible images were found.', 'png-to-webp-converter' ),
					'startConversion'   => __( 'Start Conversion', 'png-to-webp-converter' ),
					'dropHere'          => __( 'Drop images here', 'png-to-webp-converter' ),
				),
			)
		);
	}

	/**
	 * Render the main dashboard page.
	 */
	public function render_dashboard_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'png-to-webp-converter' ) );
		}

		$stats          = PTW_Media::get_stats();
		$webp_supported = PTW_Converter::is_webp_supported();
		?>
		<div class="wrap ptw-wrap">
			<h1><?php esc_html_e( 'PNG to WebP Converter', 'png-to-webp-converter' ); ?></h1>
			<p class="ptw-subtitle"><?php esc_html_e( 'Convert your WordPress images to modern WebP format.', 'png-to-webp-converter' ); ?></p>

			<?php if ( ! $webp_supported ) : ?>
				<div class="notice notice-error inline">
					<p>
						<?php esc_html_e( 'WebP conversion is not available on this server.', 'png-to-webp-converter' ); ?><br />
						<?php esc_html_e( 'Please contact your hosting provider or enable WebP support in GD/Imagick.', 'png-to-webp-converter' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<div class="ptw-cards">
				<div class="ptw-card">
					<span class="ptw-card-label"><?php esc_html_e( 'Images Converted', 'png-to-webp-converter' ); ?></span>
					<span class="ptw-card-value"><?php echo esc_html( number_format_i18n( $stats['images_converted'] ) ); ?></span>
				</div>
				<div class="ptw-card">
					<span class="ptw-card-label"><?php esc_html_e( 'Space Saved', 'png-to-webp-converter' ); ?></span>
					<span class="ptw-card-value"><?php echo esc_html( size_format( $stats['space_saved'], 1 ) ); ?></span>
				</div>
				<div class="ptw-card">
					<span class="ptw-card-label"><?php esc_html_e( 'WebP Images', 'png-to-webp-converter' ); ?></span>
					<span class="ptw-card-value"><?php echo esc_html( number_format_i18n( $stats['webp_images'] ) ); ?></span>
				</div>
				<div class="ptw-card">
					<span class="ptw-card-label"><?php esc_html_e( 'Conversion Rate', 'png-to-webp-converter' ); ?></span>
					<span class="ptw-card-value"><?php echo esc_html( $stats['conversion_rate'] ); ?>%</span>
				</div>
			</div>

			<div class="ptw-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::BULK_SLUG ) ); ?>" class="button button-primary"><?php esc_html_e( 'Convert Images', 'png-to-webp-converter' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . PTW_Settings::PAGE_SLUG ) ); ?>" class="button"><?php esc_html_e( 'Settings', 'png-to-webp-converter' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button"><?php esc_html_e( 'View Media Library', 'png-to-webp-converter' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the bulk/drag-and-drop conversion page.
	 */
	public function render_bulk_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'png-to-webp-converter' ) );
		}
		?>
		<div class="wrap ptw-wrap">
			<h1><?php esc_html_e( 'Convert Images to WebP', 'png-to-webp-converter' ); ?></h1>
			<p><?php esc_html_e( 'Select images from your Media Library and convert them to WebP. Large libraries are processed one image at a time so your browser stays responsive.', 'png-to-webp-converter' ); ?></p>

			<div class="ptw-bulk-toolbar">
				<button type="button" class="button button-primary" id="ptw-select-images"><?php esc_html_e( 'Select Images', 'png-to-webp-converter' ); ?></button>
				<button type="button" class="button button-primary" id="ptw-convert-all" data-label="<?php esc_attr_e( 'Convert All Eligible Images', 'png-to-webp-converter' ); ?>"><?php esc_html_e( 'Convert All Eligible Images', 'png-to-webp-converter' ); ?></button>
				<button type="button" class="button" id="ptw-start-selected" disabled><?php esc_html_e( 'Convert Selected', 'png-to-webp-converter' ); ?></button>
			</div>

			<div id="ptw-dropzone" class="ptw-dropzone" tabindex="0" role="button" aria-label="<?php esc_attr_e( 'Drop images here or select images to convert', 'png-to-webp-converter' ); ?>">
				<p><?php esc_html_e( 'Drop images here', 'png-to-webp-converter' ); ?></p>
				<p><?php esc_html_e( 'or use Select Images above', 'png-to-webp-converter' ); ?></p>
				<p class="ptw-dropzone-note"><?php esc_html_e( 'PNG / JPG / JPEG → WebP', 'png-to-webp-converter' ); ?></p>
				<p class="ptw-dropzone-note description"><?php esc_html_e( 'Note: images must already exist in your Media Library. Drag-and-drop here selects matching Library items; it does not bypass WordPress upload handling.', 'png-to-webp-converter' ); ?></p>
			</div>

			<div id="ptw-progress-wrap" class="ptw-progress-wrap" hidden>
				<h2><?php esc_html_e( 'Conversion Progress', 'png-to-webp-converter' ); ?></h2>
				<div class="ptw-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="ptw-progress-bar">
					<div class="ptw-progress-bar-fill" id="ptw-progress-bar-fill"></div>
				</div>
				<p id="ptw-progress-label">0%</p>

				<ul class="ptw-progress-stats">
					<li><?php esc_html_e( 'Converted:', 'png-to-webp-converter' ); ?> <span id="ptw-count-converted">0</span></li>
					<li><?php esc_html_e( 'Skipped:', 'png-to-webp-converter' ); ?> <span id="ptw-count-skipped">0</span></li>
					<li><?php esc_html_e( 'Failed:', 'png-to-webp-converter' ); ?> <span id="ptw-count-failed">0</span></li>
				</ul>

				<p>
					<?php esc_html_e( 'Original Size:', 'png-to-webp-converter' ); ?> <span id="ptw-size-original">0 B</span> —
					<?php esc_html_e( 'WebP Size:', 'png-to-webp-converter' ); ?> <span id="ptw-size-webp">0 B</span> —
					<strong><?php esc_html_e( 'Space Saved:', 'png-to-webp-converter' ); ?> <span id="ptw-size-saved">0 B</span></strong>
				</p>

				<table class="widefat striped ptw-results-table" id="ptw-results-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Filename', 'png-to-webp-converter' ); ?></th>
							<th><?php esc_html_e( 'Original', 'png-to-webp-converter' ); ?></th>
							<th><?php esc_html_e( 'WebP', 'png-to-webp-converter' ); ?></th>
							<th><?php esc_html_e( 'Saved', 'png-to-webp-converter' ); ?></th>
							<th><?php esc_html_e( 'Status', 'png-to-webp-converter' ); ?></th>
						</tr>
					</thead>
					<tbody id="ptw-results-body"></tbody>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the settings page (fields provided by PTW_Settings).
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'png-to-webp-converter' ) );
		}
		?>
		<div class="wrap ptw-wrap">
			<h1><?php esc_html_e( 'PNG to WebP Settings', 'png-to-webp-converter' ); ?></h1>
			<?php settings_errors( PTW_OPTION_NAME ); ?>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'ptw_settings_group' );
				do_settings_sections( PTW_Settings::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
