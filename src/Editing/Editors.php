<?php
/**
 * Who may edit a listing from the front end.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Editing;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * Listing managers are ordinary WordPress users stored as repeated `_favr_manager` meta on the
 * business. Other plugins grant access through filters: Favr Members lets representatives of
 * the linked, active member record edit it.
 */
final class Editors {

	public const META = '_favr_manager';

	/**
	 * Whether a user may edit a listing from the front end.
	 *
	 * @param int $user_id User.
	 * @param int $post_id Business.
	 */
	public static function canEdit( int $user_id, int $post_id ): bool {
		if ( $user_id <= 0 || ID::POST_TYPE !== get_post_type( $post_id ) || 'trash' === get_post_status( $post_id ) ) {
			return false;
		}
		$allowed = in_array( $user_id, self::managers( $post_id ), true );

		/**
		 * Filter whether a person may edit a listing from the front end.
		 *
		 * @param bool $allowed Whether they are a listing manager.
		 * @param int  $user_id User.
		 * @param int  $post_id Business.
		 */
		return (bool) apply_filters( 'favr_directory_can_member_edit', $allowed, $user_id, $post_id );
	}

	/**
	 * Listing managers stored on the business.
	 *
	 * @param int $post_id Business.
	 * @return list<int>
	 */
	public static function managers( int $post_id ): array {
		return array_values( array_unique( array_filter( array_map( 'intval', (array) get_post_meta( $post_id, self::META, false ) ) ) ) );
	}

	/**
	 * Add a manager.
	 *
	 * @param int $post_id Business.
	 * @param int $user_id User.
	 */
	public static function add( int $post_id, int $user_id ): void {
		if ( $user_id > 0 && ! in_array( $user_id, self::managers( $post_id ), true ) ) {
			add_post_meta( $post_id, self::META, $user_id );
		}
	}

	/**
	 * Remove a manager.
	 *
	 * @param int $post_id Business.
	 * @param int $user_id User.
	 */
	public static function remove( int $post_id, int $user_id ): void {
		delete_post_meta( $post_id, self::META, $user_id );
	}

	/**
	 * Listings a user may edit (published, pending or draft; never trashed).
	 *
	 * @param int $user_id User.
	 * @return list<int>
	 */
	public static function listingsFor( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}
		$ids = get_posts(
			array(
				'post_type'      => ID::POST_TYPE,
				'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, indexed by post.
					array(
						'key'   => self::META,
						'value' => (string) $user_id,
					),
				),
			)
		);

		/**
		 * Filter the listings a person may edit (e.g. Favr Members adds their member's listing).
		 *
		 * @param list<int> $ids     Business ids.
		 * @param int       $user_id User.
		 */
		$ids = (array) apply_filters( 'favr_directory_member_listings', array_map( 'intval', $ids ), $user_id );
		$ids = array_values( array_unique( array_map( 'intval', $ids ) ) );
		return array_values( array_filter( $ids, static fn( int $id ): bool => self::canEdit( $user_id, $id ) ) );
	}
}
