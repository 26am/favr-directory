<?php
/**
 * Image uploads for listing representatives.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Editing;

use FavrDirectory\Vendor\FavrCore\Moderation\Uploads;
use FavrDirectory\Vendor\FavrCore\Support\RateLimit;

/**
 * POST /favr-directory/v1/upload (multipart: file, parent). Only people who can edit the parent
 * listing may upload; images only, size-limited, owned by the uploader and attached to the
 * listing. No wp-admin or media library access is involved.
 */
final class UploadRoute {

	public const NAMESPACE = 'favr-directory/v1';
	public const ROUTE     = '/upload';

	/** Uploads per person per hour. */
	private const LIMIT = 40;

	/** Hook. */
	public function hook(): void {
		add_action( 'rest_api_init', array( $this, 'register' ) );
	}

	/** Register the route. */
	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			self::ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'upload' ),
				'permission_callback' => array( $this, 'allowed' ),
				'args'                => array(
					'parent' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission: logged in and allowed to edit the listing.
	 *
	 * @param \WP_REST_Request $request Request.
	 */
	public function allowed( \WP_REST_Request $request ): bool {
		$user_id = get_current_user_id();
		return $user_id > 0 && Editors::canEdit( $user_id, (int) $request->get_param( 'parent' ) );
	}

	/**
	 * Handle the upload.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function upload( \WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		if ( ! RateLimit::hit( 'listing_upload_' . $user_id, self::LIMIT ) ) {
			return new \WP_Error( 'favr_upload_limit', __( 'You’ve uploaded a lot of images recently. Please try again later.', 'favr-directory' ), array( 'status' => 429 ) );
		}
		$files = $request->get_file_params();
		if ( empty( $files['file'] ) || ! is_array( $files['file'] ) ) {
			return new \WP_Error( 'favr_upload', __( 'No file received.', 'favr-directory' ), array( 'status' => 400 ) );
		}

		/**
		 * Maximum size of a representative's image upload, in bytes.
		 *
		 * @param int $bytes Default 8 MB (capped by the server's own limit).
		 */
		$max = min( (int) apply_filters( 'favr_directory_upload_max_bytes', 8 * MB_IN_BYTES ), (int) wp_max_upload_size() );
		$id  = Uploads::handle( $files['file'], $user_id, (int) $request->get_param( 'parent' ), $max );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return new \WP_REST_Response(
			array(
				'id'    => $id,
				'thumb' => (string) wp_get_attachment_image_url( $id, 'thumbnail' ),
			),
			201
		);
	}
}
