<?php
/**
 * Handles the actual PNG/JPG -> WebP conversion logic.
 *
 * @package PNG_To_WebP_Converter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PTW_Converter
 */
class PTW_Converter {

	/**
	 * Meta key storing the attachment ID of the generated WebP image,
	 * set on the ORIGINAL attachment.
	 */
	const META_WEBP_ID = '_ptw_webp_attachment_id';

	/**
	 * Meta key storing the original attachment ID, set on the WEBP attachment.
	 */
	const META_ORIGINAL_ID = '_ptw_original_attachment_id';

	/**
	 * Meta key storing conversion stats (sizes, savings) on the original attachment.
	 */
	const META_STATS = '_ptw_conversion_stats';

	/**
	 * Allowed source MIME types mapped to typical extensions.
	 *
	 * @return array
	 */
	public static function get_allowed_mime_types() {
		$settings = PTW_Settings::get_settings();
		$allowed  = array();

		if ( ! empty( $settings['convert_png'] ) ) {
			$allowed['image/png'] = array( 'png' );
		}
		if ( ! empty( $settings['convert_jpg'] ) ) {
			$allowed['image/jpeg'] = array( 'jpg', 'jpeg' );
		}

		return $allowed;
	}

	/**
	 * Check whether the server can encode WebP images.
	 *
	 * @return bool
	 */
	public static function is_webp_supported() {
		// GD: imagewebp() is the most reliable runtime check.
		if ( function_exists( 'imagewebp' ) && function_exists( 'imagecreatefrompng' ) ) {
			return true;
		}

		// Some PHP/GD builds expose capability information through gd_info().
		if ( function_exists( 'gd_info' ) ) {
			$gd = gd_info();
			if ( ! empty( $gd['WebP Support'] ) && function_exists( 'imagewebp' ) ) {
				return true;
			}
		}

		// Imagick: check the actual WEBP coder.
		if ( class_exists( 'Imagick' ) ) {
			try {
				$formats = \Imagick::queryFormats( 'WEBP' );
				if ( ! empty( $formats ) ) {
					return true;
				}
			} catch ( \Throwable $e ) {
				unset( $e );
				// Ignore and report unsupported below.
			}
		}

		return false;
	}

	/**
	 * Validate that an attachment is a real, supported image before conversion.
	 * Never trusts the file extension alone -- verifies real MIME type.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return true|WP_Error
	 */
	public static function validate_attachment( $attachment_id ) {
		$attachment_id = absint( $attachment_id );

		if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
			return new WP_Error( 'ptw_invalid_attachment', __( 'Invalid attachment.', 'png-to-webp-converter' ) );
		}

		$file = get_attached_file( $attachment_id );

		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'ptw_missing_file', __( 'The original image file could not be found on the server.', 'png-to-webp-converter' ) );
		}

		// Verify real file type/MIME, not just the extension or stored meta.
		$filetype  = wp_check_filetype_and_ext( $file, basename( $file ) );
		$real_mime = ! empty( $filetype['type'] ) ? $filetype['type'] : get_post_mime_type( $attachment_id );

		$allowed = self::get_allowed_mime_types();

		if ( empty( $allowed ) ) {
			return new WP_Error( 'ptw_no_formats_enabled', __( 'No source formats are enabled. Please enable PNG and/or JPG conversion in the plugin settings.', 'png-to-webp-converter' ) );
		}

		if ( empty( $real_mime ) || ! array_key_exists( $real_mime, $allowed ) ) {
			return new WP_Error( 'ptw_unsupported_format', __( 'This file type is not supported for WebP conversion.', 'png-to-webp-converter' ) );
		}

		// Double-check with getimagesize as a defense-in-depth measure.
		$image_info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $image_info || empty( $image_info['mime'] ) || ! array_key_exists( $image_info['mime'], $allowed ) ) {
			return new WP_Error( 'ptw_unsupported_format', __( 'This file does not appear to be a valid, supported image.', 'png-to-webp-converter' ) );
		}

		if ( ! self::is_webp_supported() ) {
			return new WP_Error( 'ptw_webp_unsupported', __( 'WebP conversion is not available on this server. Please contact your hosting provider or enable WebP support in GD/Imagick.', 'png-to-webp-converter' ) );
		}

		return true;
	}

	/**
	 * Get the existing WebP attachment ID for a given original attachment, if any.
	 *
	 * @param int $attachment_id Original attachment ID.
	 * @return int 0 if none exists.
	 */
	public static function get_existing_webp_id( $attachment_id ) {
		$webp_id = (int) get_post_meta( $attachment_id, self::META_WEBP_ID, true );

		if ( $webp_id && get_post( $webp_id ) ) {
			return $webp_id;
		}

		return 0;
	}

	/**
	 * Convert a single attachment to WebP.
	 *
	 * @param int   $attachment_id Attachment ID to convert.
	 * @param array $args {
	 *     Optional arguments.
	 *
	 *     @type bool $regenerate Force regeneration even if a WebP already exists.
	 * }
	 * @return array|WP_Error Result data on success, WP_Error on failure.
	 */
	public static function convert_attachment( $attachment_id, $args = array() ) {
		$attachment_id = absint( $attachment_id );
		$args          = wp_parse_args(
			$args,
			array(
				'regenerate' => false,
			)
		);

		$validation = self::validate_attachment( $attachment_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$existing_webp_id = self::get_existing_webp_id( $attachment_id );

		if ( $existing_webp_id && ! $args['regenerate'] ) {
			return array(
				'status'        => 'skipped',
				'message'       => __( 'WebP Already Exists', 'png-to-webp-converter' ),
				'attachment_id' => $attachment_id,
				'webp_id'       => $existing_webp_id,
			);
		}

		$settings = PTW_Settings::get_settings();
		$quality  = isset( $settings['quality'] ) ? absint( $settings['quality'] ) : 80;
		$quality  = max( 1, min( 100, $quality ) );

		$source_file   = get_attached_file( $attachment_id );
		$original_size = file_exists( $source_file ) ? filesize( $source_file ) : 0;

		$path_info  = pathinfo( $source_file );
		$upload_dir = wp_upload_dir();

		$new_filename = wp_unique_filename( $path_info['dirname'], $path_info['filename'] . '.webp' );
		$new_path     = trailingslashit( $path_info['dirname'] ) . $new_filename;

		/*
		 * Use WordPress's image editor first. If the active editor incorrectly
		 * reports that WebP is unsupported, fall back to the native GD or
		 * Imagick APIs. This is important on local XAMPP installations where
		 * the WordPress editor and PHP extension capability can disagree.
		 */
		$saved      = false;
		$last_error = null;

		$editor = wp_get_image_editor( $source_file );

		if ( ! is_wp_error( $editor ) ) {
			$editor->set_quality( $quality );

			$editor_supports_webp = true;
			if ( method_exists( $editor, 'supports_mime_type' ) ) {
				try {
					$editor_supports_webp = (bool) $editor->supports_mime_type( 'image/webp' );
				} catch ( \Throwable $e ) {
					$editor_supports_webp = false;
				}
			}

			if ( $editor_supports_webp ) {
				$editor_result = $editor->save( $new_path, 'image/webp' );
				if ( ! is_wp_error( $editor_result ) && ! empty( $editor_result['path'] ) && file_exists( $editor_result['path'] ) ) {
					$saved = $editor_result;
				} elseif ( is_wp_error( $editor_result ) ) {
					$last_error = $editor_result;
				}
			}
		} elseif ( is_wp_error( $editor ) ) {
			$last_error = $editor;
		}

		// Direct GD fallback.
		if ( false === $saved && function_exists( 'imagewebp' ) ) {
			$gd_image = false;
			$mime     = get_post_mime_type( $attachment_id );

			if ( 'image/png' === $mime && function_exists( 'imagecreatefrompng' ) ) {
				$gd_image = @imagecreatefrompng( $source_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			} elseif ( 'image/jpeg' === $mime && function_exists( 'imagecreatefromjpeg' ) ) {
				$gd_image = @imagecreatefromjpeg( $source_file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}

			if ( $gd_image ) {
				if ( 'image/png' === $mime ) {
					imagealphablending( $gd_image, false );
					imagesavealpha( $gd_image, true );
				}
				if ( imagewebp( $gd_image, $new_path, $quality ) && file_exists( $new_path ) ) {
					$saved = array(
						'path'      => $new_path,
						'file'      => basename( $new_path ),
						'width'     => imagesx( $gd_image ),
						'height'    => imagesy( $gd_image ),
						'mime-type' => 'image/webp',
					);
				}
				imagedestroy( $gd_image );
			}
		}

		// Direct Imagick fallback.
		if ( false === $saved && class_exists( 'Imagick' ) ) {
			try {
				$imagick = new \Imagick( $source_file );
				$imagick->setImageFormat( 'webp' );
				$imagick->setImageCompressionQuality( $quality );
				$imagick->writeImage( $new_path );
				if ( file_exists( $new_path ) ) {
					$saved = array(
						'path'      => $new_path,
						'file'      => basename( $new_path ),
						'width'     => $imagick->getImageWidth(),
						'height'    => $imagick->getImageHeight(),
						'mime-type' => 'image/webp',
					);
				}
				$imagick->clear();
				$imagick->destroy();
			} catch ( \Throwable $e ) {
				$last_error = new WP_Error( 'ptw_imagick_error', $e->getMessage() );
			}
		}

		if ( false === $saved || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
			if ( $last_error instanceof WP_Error && $last_error->get_error_message() ) {
				return new WP_Error(
					'ptw_conversion_failed',
					sprintf(
						/* translators: %s: underlying image processing error. */
						__( 'WebP conversion failed: %s', 'png-to-webp-converter' ),
						sanitize_text_field( $last_error->get_error_message() )
					)
				);
			}
			return new WP_Error( 'ptw_webp_unsupported', __( 'WebP conversion is not available. Enable WebP support in PHP GD or Imagick.', 'png-to-webp-converter' ) );
		}

		$webp_size = filesize( $saved['path'] );

		// Insert (or update) the WebP file as its own Media Library attachment.
		if ( $existing_webp_id && $args['regenerate'] ) {
			$webp_attachment_id = $existing_webp_id;
			update_attached_file( $webp_attachment_id, $saved['path'] );
		} else {
			$filetype = wp_check_filetype( $saved['file'], null );

			$attachment_data = array(
				'guid'           => trailingslashit( $upload_dir['url'] ) . _wp_relative_upload_path( $saved['path'] ),
				'post_mime_type' => $filetype['type'] ? $filetype['type'] : 'image/webp',
				'post_title'     => sanitize_file_name( pathinfo( $saved['path'], PATHINFO_FILENAME ) ),
				'post_content'   => '',
				'post_status'    => 'inherit',
				'post_parent'    => 0,
			);

			$webp_attachment_id = wp_insert_attachment( $attachment_data, $saved['path'] );

			if ( is_wp_error( $webp_attachment_id ) || ! $webp_attachment_id ) {
				return new WP_Error( 'ptw_attachment_insert_failed', __( 'The WebP file was created but could not be added to the Media Library.', 'png-to-webp-converter' ) );
			}
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_metadata = wp_generate_attachment_metadata( $webp_attachment_id, $saved['path'] );
		wp_update_attachment_metadata( $webp_attachment_id, $attachment_metadata );

		// Link the two attachments together via post meta.
		update_post_meta( $attachment_id, self::META_WEBP_ID, $webp_attachment_id );
		update_post_meta( $webp_attachment_id, self::META_ORIGINAL_ID, $attachment_id );

		$savings_percent = $original_size > 0 ? round( ( ( $original_size - $webp_size ) / $original_size ) * 100, 1 ) : 0;

		$stats = array(
			'original_size'   => $original_size,
			'webp_size'       => $webp_size,
			'savings_percent' => $savings_percent,
			'converted_at'    => time(),
		);
		update_post_meta( $attachment_id, self::META_STATS, $stats );

		// Optionally remove the original file if the admin has explicitly opted in.
		if ( empty( $settings['keep_original'] ) ) {
			self::maybe_delete_original( $attachment_id, $source_file );
		}

		return array(
			'status'          => 'converted',
			'message'         => __( 'Image successfully converted to WebP.', 'png-to-webp-converter' ),
			'attachment_id'   => $attachment_id,
			'webp_id'         => $webp_attachment_id,
			'original_name'   => basename( $source_file ),
			'webp_name'       => basename( $saved['path'] ),
			'original_size'   => $original_size,
			'webp_size'       => $webp_size,
			'savings_percent' => $savings_percent,
		);
	}

	/**
	 * Delete the original image file only when the administrator has explicitly
	 * disabled "Keep Original Images" in settings. This never runs silently
	 * without that explicit, saved setting.
	 *
	 * @param int    $attachment_id Original attachment ID.
	 * @param string $source_file   Absolute path to the original file.
	 */
	private static function maybe_delete_original( $attachment_id, $source_file ) {
		/**
		 * Filters whether the original file should actually be deleted.
		 * Defaults to false (safest option) even if the setting is disabled,
		 * so site owners can override via code if desired.
		 *
		 * @param bool $allow_delete Whether to allow deletion.
		 * @param int  $attachment_id Attachment ID.
		 */
		$allow_delete = apply_filters( 'ptw_allow_original_deletion', false, $attachment_id );

		if ( $allow_delete && file_exists( $source_file ) ) {
			wp_delete_file( $source_file );
		}
	}
}
