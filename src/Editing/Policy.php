<?php
/**
 * What listing representatives may change from the front end.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Editing;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Support\Settings;

/**
 * Every editable item has an access level:
 *  - edit:   saved immediately.
 *  - review: saved as a proposed change that staff approve in the Approvals inbox.
 *  - none:   never shown to representatives.
 *
 * Items are the registry fields plus four "core" items stored on the post itself:
 * business_name (title), description (content), categories and cover (featured image).
 * Precedence: private fields are always none, then the Settings override, then the field's own
 * `member_access` key, then the defaults below.
 */
final class Policy {

	public const EDIT   = 'edit';
	public const REVIEW = 'review';
	public const NONE   = 'none';

	public const LEVELS = array( self::EDIT, self::REVIEW, self::NONE );

	/** Items that live on the post rather than in meta. */
	public const CORE_ITEMS = array( 'business_name', 'description', 'categories', 'cover' );

	/** Reviewed by default: public identity, location and anything that embeds content. */
	private const REVIEW_DEFAULTS = array(
		'business_name',
		'description',
		'categories',
		'cover',
		'logo',
		'tagline',
		'summary',
		'online_only',
		'address_1',
		'address_2',
		'city',
		'state',
		'postal_code',
		'country',
		'service_area',
		'latitude',
		'longitude',
		'video_url',
	);

	/** Staff-only by default. */
	private const NONE_DEFAULTS = array( 'featured', 'member_since' );

	/**
	 * Core items as pseudo field definitions (normalized like registry fields).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function coreItems(): array {
		$items = array(
			'business_name' => array(
				'id'        => 'business_name',
				'label'     => __( 'Business name', 'favr-directory' ),
				'type'      => 'text',
				'tab'       => 'overview',
				'maxlength' => 120,
				'required'  => true,
			),
			'description'   => array(
				'id'          => 'description',
				'label'       => __( 'Description', 'favr-directory' ),
				'type'        => 'textarea',
				'tab'         => 'overview',
				'description' => __( 'Tell visitors what you do. Plain text; blank lines start new paragraphs.', 'favr-directory' ),
			),
			'categories'    => array(
				'id'      => 'categories',
				'label'   => __( 'Categories', 'favr-directory' ),
				'type'    => 'checkboxes',
				'tab'     => 'overview',
				'options' => array(), // Filled in by the form from the category taxonomy.
			),
			'cover'         => array(
				'id'          => 'cover',
				'label'       => __( 'Cover photo', 'favr-directory' ),
				'type'        => 'image',
				'tab'         => 'media',
				'description' => __( 'A wide photo shown at the top of your listing.', 'favr-directory' ),
			),
		);
		return array_map( array( FieldRegistry::class, 'normalizeItem' ), $items );
	}

	/**
	 * Built-in default for an item (ignores the Settings override).
	 *
	 * @param string $id Item id.
	 */
	public static function defaultFor( string $id ): string {
		$field = FieldRegistry::get( $id );
		if ( $field && ! empty( $field['private'] ) ) {
			return self::NONE;
		}
		if ( $field && in_array( $field['member_access'] ?? '', self::LEVELS, true ) ) {
			return (string) $field['member_access'];
		}
		if ( in_array( $id, self::NONE_DEFAULTS, true ) ) {
			return self::NONE;
		}
		return in_array( $id, self::REVIEW_DEFAULTS, true ) ? self::REVIEW : self::EDIT;
	}

	/**
	 * Effective access for an item.
	 *
	 * @param string $id Item id.
	 */
	public static function access( string $id ): string {
		if ( ! in_array( $id, self::CORE_ITEMS, true ) ) {
			$field = FieldRegistry::get( $id );
			if ( ! $field ) {
				return self::NONE;
			}
			if ( ! empty( $field['private'] ) ) {
				return self::NONE; // Staff-only data is never exposed, whatever the settings say.
			}
		}
		$overrides = (array) Settings::get( 'member_access' );
		$level     = isset( $overrides[ $id ] ) && in_array( $overrides[ $id ], self::LEVELS, true ) ? (string) $overrides[ $id ] : self::defaultFor( $id );

		/**
		 * Filter a representative's access to one listing item.
		 *
		 * @param string $level edit | review | none.
		 * @param string $id    Field or core item id.
		 */
		$level = (string) apply_filters( 'favr_directory_member_access', $level, $id );
		return in_array( $level, self::LEVELS, true ) ? $level : self::NONE;
	}

	/**
	 * Every item that can be configured (core items first, then registry fields), excluding
	 * private fields.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function configurable(): array {
		$items = array();
		foreach ( self::coreItems() as $id => $item ) {
			$items[ $id ] = $item;
		}
		foreach ( FieldRegistry::all() as $id => $field ) {
			if ( empty( $field['private'] ) ) {
				$items[ $id ] = $field;
			}
		}
		return $items;
	}

	/**
	 * Items visible to representatives.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function visible(): array {
		return array_filter(
			self::configurable(),
			static fn( array $item ): bool => self::NONE !== self::access( (string) $item['id'] )
		);
	}
}
