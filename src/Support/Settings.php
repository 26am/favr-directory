<?php
/**
 * Typed access to the plugin settings option.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Support;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * Settings are one serialized option. Reads go through here so defaults live in one place.
 */
final class Settings {

	/** @var array<string, mixed>|null */
	private static ?array $cache = null;

	/**
	 * Default values.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'directory_slug'    => 'directory',
			'category_slug'     => 'category',
			'directory_title'   => __( 'Business Directory', 'favr-directory' ),
			'organization_name' => '',
			'per_page'          => 12,
			'layout'            => 'grid',
			'map_provider'      => 'google',
			'show_open_now'     => '1',
			'show_letters'      => '1',
			'accent_color'      => '',
			'sections'          => array_keys( self::sectionChoices() ),
			'delete_data'       => '0',
			'listing_kind'      => 'business', // business | person (e.g. attorneys, professionals).
			'noun_singular'     => '',
			'noun_plural'       => '',
			'member_access'     => array(),
			'claims'            => '1',
			'notify_email'      => '',
			'edit_page'         => 0,
		);
	}

	/**
	 * Public profile sections an admin can switch off site-wide.
	 *
	 * @return array<string, string>
	 */
	public static function sectionChoices(): array {
		return array(
			'contact'    => __( 'Contact details', 'favr-directory' ),
			'hours'      => __( 'Opening hours', 'favr-directory' ),
			'map'        => __( 'Map', 'favr-directory' ),
			'social'     => __( 'Social links', 'favr-directory' ),
			'gallery'    => __( 'Photo gallery', 'favr-directory' ),
			'video'      => __( 'Video', 'favr-directory' ),
			'deal'       => __( 'Member deal', 'favr-directory' ),
			'highlights' => __( 'Highlights', 'favr-directory' ),
			'links'      => __( 'Additional links', 'favr-directory' ),
			'membership' => __( 'Membership info (member since, level)', 'favr-directory' ),
		);
	}

	/**
	 * All settings merged over defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null === self::$cache ) {
			$stored      = get_option( ID::OPTION_SETTINGS, array() );
			self::$cache = array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
		}
		return self::$cache;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		return self::all()[ $key ] ?? ( self::defaults()[ $key ] ?? null );
	}

	/** Listings describe people (bar associations, professional societies) rather than businesses. */
	public static function listsPeople(): bool {
		return 'person' === self::get( 'listing_kind' );
	}

	/**
	 * What a listing is called on the site ("business"/"businesses", "member"/"members"…).
	 *
	 * @param bool $plural Plural form.
	 */
	public static function noun( bool $plural = true ): string {
		$custom = trim( (string) self::get( $plural ? 'noun_plural' : 'noun_singular' ) );
		if ( '' !== $custom ) {
			return $custom;
		}
		if ( self::listsPeople() ) {
			return $plural ? __( 'members', 'favr-directory' ) : __( 'member', 'favr-directory' );
		}
		return $plural ? __( 'businesses', 'favr-directory' ) : __( 'business', 'favr-directory' );
	}

	/**
	 * Whether a public profile section is enabled.
	 *
	 * @param string $section Section key.
	 */
	public static function sectionEnabled( string $section ): bool {
		return in_array( $section, (array) self::get( 'sections' ), true );
	}

	/** Forget the cached option (after an update). */
	public static function flush(): void {
		self::$cache = null;
	}
}
