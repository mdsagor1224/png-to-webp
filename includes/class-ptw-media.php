<?php
/**
 * Media Library integration: row actions, auto-conversion, stats helpers.
 *
 * @package PNG_To_WebP_Converter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PTW_Media
 */
class PTW_Media {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'media_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_grid_view_status' ), 10, 2 );
		add_action( 'wp_generate_attachment_metadata', array( $this, 'maybe_auto_convert' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'render_admin_notices' ) );
	}

	/**
	 * Add a "Convert to WebP" / status row action in the Media Library list view.
	 *
	 * @param array   $actions Existing row actions.
	 * @param WP_Post $post    Attachment post object.
	 * @return array
	 */
	public function add_row_action( $actions, $post ) {
		if ( ! current_user_can( 'upload_files' ) ) {
			return $actions;
		}

		$allowed = PTW_Converter::get_allowed_mime_types();
		if ( empty( $allowed ) || ! array_key_exists( $post->post_mime_type, $allowed ) ) {
			return $actions;
		}

		// Skip if this attachment IS itself a generated WebP file.
		if ( get_post_meta( $post->ID, PTW_Converter::META_ORIGINAL_ID, true ) ) {
			return $actions;
		}

		$existing_webp_id = PTW_Converter::get_existing_webp_id( $post->ID );

		if ( $existing_webp_id ) {
			$actions['ptw_status'] = sprintf(
				'<span class="ptw-status ptw-status-exists">%s</span> <a href="#" class="ptw-regenerate" data-attachment-id="%d">%s</a>',
				esc_html__( 'WebP Already Exists', 'png-to-webp-converter' ),
				(int) $post->ID,
				esc_html__( 'Regenerate WebP', 'png-to-webp-converter' )
			);
		} else {
			$actions['ptw_convert'] = sprintf(
				'<a href="#" class="ptw-convert" data-attachment-id="%d">%s</a>',
				(int) $post->ID,
				esc_html__( 'Convert to WebP', 'png-to-webp-converter' )
			);
		}

		return $actions;
	}

	/**
	 * Add a WebP status note to the attachment edit/grid view fields.
	 *
	 * @param array   $form_fields Existing fields.
	 * @param WP_Post $post        Attachment post object.
	 * @return array
	 */
	public function add_grid_view_status( $form_fields, $post ) {
		$allowed = PTW_Converter::get_allowed_mime_types();
		if ( empty( $allowed ) || ! array_key_exists( $post->post_mime_type, $allowed ) ) {
			return $form_fields;
		}

		$existing_webp_id = PTW_Converter::get_existing_webp_id( $post->ID );

		$form_fields['ptw_webp_status'] = array(
			'label' => __( 'WebP Status', 'png-to-webp-converter' ),
			'input' => 'html',
			'html'  => $existing_webp_id
				? '<span class="ptw-status ptw-status-exists">' . esc_html__( 'WebP Created', 'png-to-webp-converter' ) . ' &#10003;</span>'
				: '<span class="ptw-status ptw-status-none">' . esc_html__( 'Not converted', 'png-to-webp-converter' ) . '</span>',
		);

		return $form_fields;
	}

	/**
	 * Automatically convert a newly uploaded PNG/JPG to WebP, if enabled in settings.
	 * Runs on wp_generate_attachment_metadata so the original upload has fully completed
	 * and is never disrupted by a WebP failure.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Unmodified metadata (this method never alters the original upload).
	 */
	public function maybe_auto_convert( $metadata, $attachment_id ) {
		$settings = PTW_Settings::get_settings();

		if ( empty( $settings['auto_convert'] ) ) {
			return $metadata;
		}

		// Never process a file that is itself a generated WebP (avoids loops).
		if ( get_post_meta( $attachment_id, PTW_Converter::META_ORIGINAL_ID, true ) ) {
			return $metadata;
		}

		// Avoid duplicate work if a WebP already exists for this attachment.
		if ( PTW_Converter::get_existing_webp_id( $attachment_id ) ) {
			return $metadata;
		}

		$validation = PTW_Converter::validate_attachment( $attachment_id );
		if ( is_wp_error( $validation ) ) {
			// Silently skip unsupported files; do not break the upload process.
			return $metadata;
		}

		$result = PTW_Converter::convert_attachment( $attachment_id );

		if ( is_wp_error( $result ) ) {
			set_transient( 'ptw_auto_convert_error_' . $attachment_id, $result->get_error_message(), 60 );
		}

		// Always return the original metadata untouched -- the original upload
		// must never be broken or delayed by a WebP conversion failure.
		return $metadata;
	}

	/**
	 * Render a dismissible admin notice if an automatic conversion recently failed.
	 */
	public function render_admin_notices() {
		global $pagenow;

		if ( 'upload.php' !== $pagenow ) {
			return;
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return;
		}

		// Look for any recent auto-convert failure transients for attachments visible to this user.
		// Kept lightweight: only checked on the Media Library screen itself.
		if ( isset( $_GET['ptw_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$notice = sanitize_key( wp_unslash( $_GET['ptw_notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

			if ( 'converted' === $notice ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Image successfully converted to WebP.', 'png-to-webp-converter' ) . '</p></div>';
			} elseif ( 'failed' === $notice ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'WebP conversion failed. Please check your server image processing support.', 'png-to-webp-converter' ) . '</p></div>';
			}
		}

		if ( ! PTW_Converter::is_webp_supported() ) {
			echo '<div class="notice notice-warning"><p>' .
				esc_html__( 'WebP conversion is not available on this server. Please contact your hosting provider or enable WebP support in GD/Imagick.', 'png-to-webp-converter' ) .
				'</p></div>';
		}
	}

	/**
	 * Get aggregate conversion statistics for the dashboard.
	 *
	 * @return array
	 */
	public static function get_stats() {
		global $wpdb;

		$cache_key = 'ptw_dashboard_stats';
		$cached    = wp_cache_get( $cache_key, 'ptw' );

		if ( false !== $cached ) {
			return $cached;
		}

		$converted_ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => PTW_Converter::META_WEBP_ID, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$total_original = 0;
		$total_webp     = 0;
		$converted      = 0;

		foreach ( $converted_ids as $id ) {
			$stats = get_post_meta( $id, PTW_Converter::META_STATS, true );
			if ( is_array( $stats ) ) {
				$total_original += isset( $stats['original_size'] ) ? (int) $stats['original_size'] : 0;
				$total_webp     += isset( $stats['webp_size'] ) ? (int) $stats['webp_size'] : 0;
				$converted++;
			}
		}

		$eligible_total = self::count_eligible_images();
		$conversion_rate = $eligible_total > 0 ? round( ( $converted / $eligible_total ) * 100 ) : 0;

		$stats = array(
			'images_converted' => $converted,
			'space_saved'      => max( 0, $total_original - $total_webp ),
			'webp_images'      => $converted,
			'conversion_rate'  => $conversion_rate,
		);

		wp_cache_set( $cache_key, $stats, 'ptw', 5 * MINUTE_IN_SECONDS );

		return $stats;
	}

	/**
	 * Count total PNG/JPG attachments eligible for conversion.
	 *
	 * @return int
	 */
	public static function count_eligible_images() {
		$allowed = array_keys( PTW_Converter::get_allowed_mime_types() );

		if ( empty( $allowed ) ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => $allowed,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => PTW_Converter::META_ORIGINAL_ID,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		return (int) $query->found_posts;
	}
}
