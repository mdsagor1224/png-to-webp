<?php
/**
 * Settings page and options handling.
 *
 * @package PNG_To_WebP_Converter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PTW_Settings
 */
class PTW_Settings {

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'ptw-settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Default plugin settings.
	 *
	 * @return array
	 */
	public static function get_default_settings() {
		return array(
			'quality'       => 80,
			'keep_original' => 1,
			'auto_convert'  => 0,
			'convert_jpg'   => 1,
			'convert_png'   => 1,
		);
	}

	/**
	 * Get sanitized plugin settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = self::get_default_settings();
		$saved    = get_option( PTW_OPTION_NAME, array() );

		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, $defaults );
	}

	/**
	 * Register settings, sections and fields via the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'ptw_settings_group',
			PTW_OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_default_settings(),
			)
		);

		add_settings_section(
			'ptw_main_section',
			__( 'Conversion Settings', 'png-to-webp-converter' ),
			'__return_false',
			self::PAGE_SLUG
		);

		add_settings_field(
			'ptw_quality',
			__( 'WebP Quality', 'png-to-webp-converter' ),
			array( $this, 'render_quality_field' ),
			self::PAGE_SLUG,
			'ptw_main_section'
		);

		add_settings_field(
			'ptw_keep_original',
			__( 'Keep Original Images', 'png-to-webp-converter' ),
			array( $this, 'render_keep_original_field' ),
			self::PAGE_SLUG,
			'ptw_main_section'
		);

		add_settings_field(
			'ptw_auto_convert',
			__( 'Automatic Conversion', 'png-to-webp-converter' ),
			array( $this, 'render_auto_convert_field' ),
			self::PAGE_SLUG,
			'ptw_main_section'
		);

		add_settings_field(
			'ptw_formats',
			__( 'Formats to Convert', 'png-to-webp-converter' ),
			array( $this, 'render_formats_field' ),
			self::PAGE_SLUG,
			'ptw_main_section'
		);
	}

	/**
	 * Sanitize settings input before saving.
	 *
	 * @param array $input Raw input.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$defaults  = self::get_default_settings();
		$sanitized = array();

		$quality              = isset( $input['quality'] ) ? absint( $input['quality'] ) : $defaults['quality'];
		$sanitized['quality'] = max( 1, min( 100, $quality ) );

		$sanitized['keep_original'] = ! empty( $input['keep_original'] ) ? 1 : 0;
		$sanitized['auto_convert']  = ! empty( $input['auto_convert'] ) ? 1 : 0;
		$sanitized['convert_jpg']   = ! empty( $input['convert_jpg'] ) ? 1 : 0;
		$sanitized['convert_png']   = ! empty( $input['convert_png'] ) ? 1 : 0;

		if ( empty( $sanitized['keep_original'] ) ) {
			add_settings_error(
				PTW_OPTION_NAME,
				'ptw_keep_original_warning',
				__( 'Warning: "Keep Original Images" is disabled. Future conversions may remove original files. This action cannot be undone automatically.', 'png-to-webp-converter' ),
				'warning'
			);
		}

		return $sanitized;
	}

	/**
	 * Render the quality range field.
	 */
	public function render_quality_field() {
		$settings = self::get_settings();
		?>
		<input type="range" id="ptw_quality" name="<?php echo esc_attr( PTW_OPTION_NAME ); ?>[quality]" min="1" max="100" step="1" value="<?php echo esc_attr( $settings['quality'] ); ?>" oninput="document.getElementById('ptw_quality_value').textContent = this.value;" />
		<span id="ptw_quality_value" aria-live="polite"><?php echo esc_html( $settings['quality'] ); ?></span>
		<p class="description"><?php esc_html_e( 'Higher values preserve more quality but produce larger files. 80 is recommended.', 'png-to-webp-converter' ); ?></p>
		<?php
	}

	/**
	 * Render the keep-original checkbox.
	 */
	public function render_keep_original_field() {
		$settings = self::get_settings();
		?>
		<label for="ptw_keep_original">
			<input type="checkbox" id="ptw_keep_original" name="<?php echo esc_attr( PTW_OPTION_NAME ); ?>[keep_original]" value="1" <?php checked( 1, $settings['keep_original'] ); ?> />
			<?php esc_html_e( 'Keep original PNG/JPG images after conversion', 'png-to-webp-converter' ); ?>
		</label>
		<p class="description"><?php esc_html_e( 'Recommended. If disabled, original files may be removed after conversion and this cannot be undone automatically.', 'png-to-webp-converter' ); ?></p>
		<?php
	}

	/**
	 * Render the automatic-conversion checkbox.
	 */
	public function render_auto_convert_field() {
		$settings = self::get_settings();
		?>
		<label for="ptw_auto_convert">
			<input type="checkbox" id="ptw_auto_convert" name="<?php echo esc_attr( PTW_OPTION_NAME ); ?>[auto_convert]" value="1" <?php checked( 1, $settings['auto_convert'] ); ?> />
			<?php esc_html_e( 'Automatically convert new PNG/JPG uploads to WebP', 'png-to-webp-converter' ); ?>
		</label>
		<?php
	}

	/**
	 * Render the source-format checkboxes.
	 */
	public function render_formats_field() {
		$settings = self::get_settings();
		?>
		<label for="ptw_convert_png">
			<input type="checkbox" id="ptw_convert_png" name="<?php echo esc_attr( PTW_OPTION_NAME ); ?>[convert_png]" value="1" <?php checked( 1, $settings['convert_png'] ); ?> />
			<?php esc_html_e( 'Convert PNG', 'png-to-webp-converter' ); ?>
		</label>
		<br />
		<label for="ptw_convert_jpg">
			<input type="checkbox" id="ptw_convert_jpg" name="<?php echo esc_attr( PTW_OPTION_NAME ); ?>[convert_jpg]" value="1" <?php checked( 1, $settings['convert_jpg'] ); ?> />
			<?php esc_html_e( 'Convert JPG / JPEG', 'png-to-webp-converter' ); ?>
		</label>
		<?php
	}
}
