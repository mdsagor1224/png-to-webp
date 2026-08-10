<?php
/**
 * Core plugin bootstrap class.
 *
 * @package PNG_To_WebP_Converter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PTW_Plugin
 *
 * Wires up all plugin components and handles i18n loading.
 */
final class PTW_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var PTW_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Admin handler.
	 *
	 * @var PTW_Admin
	 */
	public $admin;

	/**
	 * Media library handler.
	 *
	 * @var PTW_Media
	 */
	public $media;

	/**
	 * AJAX handler.
	 *
	 * @var PTW_Ajax
	 */
	public $ajax;

	/**
	 * Settings handler.
	 *
	 * @var PTW_Settings
	 */
	public $settings;

	/**
	 * Get singleton instance.
	 *
	 * @return PTW_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Private to enforce singleton usage.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'plugins_loaded', array( $this, 'init_components' ) );
	}

	/**
	 * Load plugin translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( PTW_TEXT_DOMAIN, false, dirname( PTW_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Instantiate all plugin components.
	 */
	public function init_components() {
		$this->settings = new PTW_Settings();
		$this->media    = new PTW_Media();
		$this->admin    = new PTW_Admin();
		$this->ajax     = new PTW_Ajax();
	}
}
