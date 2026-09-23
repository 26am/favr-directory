<?php
/**
 * REST API privacy.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Model;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * Staff-only fields are never registered for REST. This additionally hides a business's
 * email from anonymous REST reads when "Show email address publicly" is off, so the API
 * never reveals more than the public profile does.
 */
final class RestPrivacy {

	/** Hook. */
	public function hook(): void {
		add_filter( 'rest_prepare_' . ID::POST_TYPE, array( $this, 'filter' ), 10, 2 );
	}

	/**
	 * Remove the email when it is not public and the viewer cannot edit the business.
	 *
	 * @param \WP_REST_Response $response Response.
	 * @param \WP_Post          $post     Business.
	 */
	public function filter( \WP_REST_Response $response, \WP_Post $post ): \WP_REST_Response {
		$data = $response->get_data();
		if ( ! is_array( $data ) || ! isset( $data['meta'] ) || ! is_array( $data['meta'] ) ) {
			return $response;
		}
		$public = '1' === (string) get_post_meta( $post->ID, ID::meta( 'show_email' ), true );
		if ( ! $public && ! current_user_can( 'edit_post', $post->ID ) ) {
			$data['meta'][ ID::meta( 'email' ) ] = '';
			$response->set_data( $data );
		}
		return $response;
	}
}
