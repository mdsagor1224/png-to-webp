<?php
/**
 * Secure AJAX endpoints for single and bulk conversion.
 *
 * @package PNG_To_WebP_Converter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PTW_Ajax
 */
class PTW_Ajax {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'wp_ajax_ptw_convert_single', array( $this, 'handle_convert_single' ) );
		add_action( 'wp_ajax_ptw_convert_batch_item', array( $this, 'handle_convert_batch_item' ) );
		add_action( 'wp_ajax_ptw_get_convertible_ids', array( $this, 'handle_get_convertible_ids' ) );
		add_action( 'wp_ajax_ptw_get_stats', array( $this, 'handle_get_stats' ) );
	}

	/**
	 * Verify the shared plugin nonce and required capability.
	 * Halts execution with a JSON error response on failure.
	 */
	private function verify_request() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'ptw_ajax_nonce' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed. Please refresh the page and try again.', 'png-to-webp-converter' ) ),
				403
			);
		}

		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'You do not have permission to perform this action.', 'png-to-webp-converter' ) ),
				403
			);
		}
	}

	/**
	 * Handle conversion of a single attachment (used by the Media Library row action).
	 */
	public function handle_convert_single() {
		$this->verify_request();

		if ( empty( $_POST['attachment_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in self::verify_request() above.
			wp_send_json_error( array( 'message' => __( 'Missing attachment ID.', 'png-to-webp-converter' ) ) );
		}

		$attachment_id = absint( $_POST['attachment_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in self::verify_request() above.
		$regenerate    = ! empty( $_POST['regenerate'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in self::verify_request() above.

		$result = PTW_Converter::convert_attachment( $attachment_id, array( 'regenerate' => $regenerate ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $this->format_result( $result ) );
	}

	/**
	 * Handle conversion of one item within a bulk conversion batch.
	 * Processes exactly one attachment per request so the browser never freezes
	 * and progress can be reported incrementally.
	 */
	public function handle_convert_batch_item() {
		$this->verify_request();

		if ( empty( $_POST['attachment_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in self::verify_request() above.
			wp_send_json_error( array( 'message' => __( 'Missing attachment ID.', 'png-to-webp-converter' ) ) );
		}

		$attachment_id = absint( $_POST['attachment_id'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce already verified in self::verify_request() above.

		$result = PTW_Converter::convert_attachment( $attachment_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_success(
				array(
					'status'        => 'failed',
					'message'       => $result->get_error_message(),
					'attachment_id' => $attachment_id,
				)
			);
		}

		wp_send_json_success( $this->format_result( $result ) );
	}

	/**
	 * Return the list of attachment IDs eligible for bulk conversion.
	 */
	public function handle_get_convertible_ids() {
		$this->verify_request();

		$allowed = array_keys( PTW_Converter::get_allowed_mime_types() );

		if ( empty( $allowed ) ) {
			wp_send_json_success( array( 'ids' => array() ) );
		}

		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => $allowed,
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'relation' => 'AND',
					array(
						'key'     => PTW_Converter::META_ORIGINAL_ID,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => PTW_Converter::META_WEBP_ID,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		wp_send_json_success( array( 'ids' => array_map( 'absint', $query->posts ) ) );
	}

	/**
	 * Return current dashboard stats (used to refresh cards after conversions).
	 */
	public function handle_get_stats() {
		$this->verify_request();

		wp_send_json_success( PTW_Media::get_stats() );
	}

	/**
	 * Normalize a converter result array for JSON output, with escaped strings.
	 *
	 * @param array $result Raw result from PTW_Converter::convert_attachment().
	 * @return array
	 */
	private function format_result( $result ) {
		return array(
			'status'          => isset( $result['status'] ) ? sanitize_key( $result['status'] ) : '',
			'message'         => isset( $result['message'] ) ? wp_strip_all_tags( $result['message'] ) : '',
			'attachment_id'   => isset( $result['attachment_id'] ) ? (int) $result['attachment_id'] : 0,
			'webp_id'         => isset( $result['webp_id'] ) ? (int) $result['webp_id'] : 0,
			'original_name'   => isset( $result['original_name'] ) ? sanitize_file_name( $result['original_name'] ) : '',
			'webp_name'       => isset( $result['webp_name'] ) ? sanitize_file_name( $result['webp_name'] ) : '',
			'original_size'   => isset( $result['original_size'] ) ? (int) $result['original_size'] : 0,
			'webp_size'       => isset( $result['webp_size'] ) ? (int) $result['webp_size'] : 0,
			'savings_percent' => isset( $result['savings_percent'] ) ? (float) $result['savings_percent'] : 0,
		);
	}
}
