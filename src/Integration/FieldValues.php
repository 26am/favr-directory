<?php
/**
 * Business values for page builders (Elementor dynamic tags, block bindings).
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Frontend\Shortcodes;
use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;

/**
 * One list of public business values, shared by every builder integration, so an Elementor
 * dynamic tag and a bound Gutenberg paragraph always show the same thing. Staff-only fields are
 * never offered.
 */
final class FieldValues {

	/**
	 * Text values: key => label.
	 *
	 * @return array<string, string>
	 */
	public static function textOptions(): array {
		$options = array(
			'name'       => __( 'Business name', 'favr-directory' ),
			'address'    => __( 'Address (one line)', 'favr-directory' ),
			'locality'   => __( 'City, State', 'favr-directory' ),
			'categories' => __( 'Categories', 'favr-directory' ),
			'level'      => __( 'Membership level', 'favr-directory' ),
		);
		foreach ( FieldRegistry::all() as $id => $field ) {
			if ( ! empty( $field['private'] ) || in_array( $field['type'], array( 'image', 'gallery', 'hours', 'repeater', 'toggle' ), true ) ) {
				continue;
			}
			$options[ (string) $id ] = (string) $field['label'];
		}
		return $options;
	}

	/**
	 * Link values: key => label.
	 *
	 * @return array<string, string>
	 */
	public static function urlOptions(): array {
		$options = array(
			'profile'    => __( 'Business page', 'favr-directory' ),
			'website'    => __( 'Website', 'favr-directory' ),
			'booking'    => __( 'Booking link', 'favr-directory' ),
			'phone'      => __( 'Call (tel: link)', 'favr-directory' ),
			'email'      => __( 'Email (mailto: link, if public)', 'favr-directory' ),
			'directions' => __( 'Directions (Google Maps)', 'favr-directory' ),
		);
		foreach ( FieldRegistry::socialNetworks() as $id => $label ) {
			$options[ (string) $id ] = $label;
		}
		return $options;
	}

	/**
	 * Image values: key => label.
	 *
	 * @return array<string, string>
	 */
	public static function imageOptions(): array {
		return array(
			'logo'  => __( 'Logo', 'favr-directory' ),
			'cover' => __( 'Cover photo', 'favr-directory' ),
		);
	}

	/**
	 * The business for a context: an explicit id, else the current post.
	 *
	 * @param int $post_id Post id (0 = current).
	 */
	public static function business( int $post_id = 0 ): ?Business {
		$business = Business::find( $post_id ?: (int) get_the_ID() );
		return $business && Shortcodes::visible( $business ) ? $business : null;
	}

	/**
	 * Plain text value.
	 *
	 * @param Business $business Business.
	 * @param string   $key      Key from textOptions().
	 */
	public static function text( Business $business, string $key ): string {
		switch ( $key ) {
			case 'name':
				return wp_specialchars_decode( $business->name(), ENT_QUOTES );
			case 'address':
				return $business->addressText();
			case 'locality':
				return $business->locality();
			case 'categories':
				return implode( ', ', wp_list_pluck( $business->categories(), 'name' ) );
			case 'level':
				return $business->level() ? $business->level()->name : '';
			case 'email':
				return $business->publicEmail();
		}
		$field = FieldRegistry::get( $key );
		if ( ! $field || ! empty( $field['private'] ) ) {
			return '';
		}
		$value = $business->field( $key );
		if ( in_array( $field['type'], array( 'select', 'radio' ), true ) ) {
			return (string) ( $field['options'][ (string) $value ] ?? $value );
		}
		if ( 'checkboxes' === $field['type'] ) {
			return implode( ', ', array_map( static fn( $k ): string => (string) ( $field['options'][ $k ] ?? $k ), (array) $value ) );
		}
		if ( 'date' === $field['type'] && '' !== (string) $value ) {
			return (string) wp_date( (string) get_option( 'date_format' ), (int) strtotime( (string) $value ) );
		}
		return is_scalar( $value ) ? (string) $value : '';
	}

	/**
	 * Link value.
	 *
	 * @param Business $business Business.
	 * @param string   $key      Key from urlOptions().
	 */
	public static function url( Business $business, string $key ): string {
		switch ( $key ) {
			case 'profile':
				return $business->url();
			case 'website':
				return $business->text( 'website' );
			case 'booking':
				return $business->text( 'booking_url' );
			case 'phone':
				return '' !== $business->text( 'phone' ) ? Business::telHref( $business->text( 'phone' ) ) : '';
			case 'email':
				return '' !== $business->publicEmail() ? 'mailto:' . antispambot( $business->publicEmail() ) : '';
			case 'directions':
				return $business->directionsUrl();
		}
		$social = $business->social();
		return isset( FieldRegistry::socialNetworks()[ $key ] ) ? (string) ( $social[ $key ] ?? $business->text( $key ) ) : '';
	}

	/**
	 * Image attachment id.
	 *
	 * @param Business $business Business.
	 * @param string   $key      logo | cover.
	 */
	public static function imageId( Business $business, string $key ): int {
		return 'cover' === $key ? $business->coverId() : ( 'logo' === $key ? $business->logoId() : 0 );
	}

	/** Whether the current post is a business (for editors picking "current business"). */
	public static function inBusinessContext(): bool {
		return ID::POST_TYPE === get_post_type();
	}
}
