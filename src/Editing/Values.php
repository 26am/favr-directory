<?php
/**
 * Reading, sanitizing, applying and displaying listing items (fields and core items).
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Editing;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Vendor\FavrCore\Fields\Sanitizer;
use FavrDirectory\Vendor\FavrCore\Moderation\Uploads;
use FavrDirectory\Vendor\FavrCore\Support\Hours;

/**
 * One place that knows where each item is stored, so the front-end form, the approval queue and
 * the change log all treat them the same way.
 */
final class Values {

	/**
	 * Definition for an item.
	 *
	 * @param string $id Item id.
	 * @return array<string, mixed>|null
	 */
	public static function item( string $id ): ?array {
		$core = Policy::coreItems();
		if ( isset( $core[ $id ] ) ) {
			return $core[ $id ];
		}
		return FieldRegistry::get( $id );
	}

	/**
	 * Current stored value.
	 *
	 * @param int    $post_id Business.
	 * @param string $id      Item id.
	 * @return mixed
	 */
	public static function current( int $post_id, string $id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		switch ( $id ) {
			case 'business_name':
				return $post->post_title;
			case 'description':
				return $post->post_content;
			case 'categories':
				$ids = wp_get_object_terms( $post_id, ID::TAX_CATEGORY, array( 'fields' => 'ids' ) );
				$ids = is_wp_error( $ids ) ? array() : array_map( 'intval', $ids );
				sort( $ids );
				return $ids;
			case 'cover':
				return (int) get_post_thumbnail_id( $post_id );
		}
		// metadata_exists() first: registered meta defaults (e.g. 0 for numbers) are not real values.
		if ( ! metadata_exists( 'post', $post_id, ID::meta( $id ) ) ) {
			return '';
		}
		$value = get_post_meta( $post_id, ID::meta( $id ), true );
		return '' === $value || null === $value ? '' : $value;
	}

	/**
	 * Sanitize a submitted value (attachment ownership is checked separately by the form).
	 *
	 * @param string $id  Item id.
	 * @param mixed  $raw Raw input.
	 * @return mixed
	 */
	public static function sanitize( string $id, $raw ) {
		switch ( $id ) {
			case 'business_name':
				return mb_substr( sanitize_text_field( is_string( $raw ) ? $raw : '' ), 0, 120 );
			case 'description':
				return self::cleanDescription( is_string( $raw ) ? $raw : '' );
			case 'categories':
				$valid = get_terms(
					array(
						'taxonomy'   => ID::TAX_CATEGORY,
						'hide_empty' => false,
						'fields'     => 'ids',
					)
				);
				$valid = is_wp_error( $valid ) ? array() : array_map( 'intval', $valid );
				$ids   = array_values( array_intersect( array_map( 'intval', (array) $raw ), $valid ) );
				sort( $ids );
				return array_slice( array_values( array_unique( $ids ) ), 0, 5 );
			case 'cover':
				return absint( is_scalar( $raw ) ? $raw : 0 );
		}
		$field = FieldRegistry::get( $id );
		return $field ? Sanitizer::sanitize( $field, $raw ) : '';
	}

	/**
	 * Store a value.
	 *
	 * @param int    $post_id Business.
	 * @param string $id      Item id.
	 * @param mixed  $value   Sanitized value.
	 */
	public static function apply( int $post_id, string $id, $value ): void {
		// Representative uploads start private; they become public once used on the live listing.
		Uploads::publish( self::attachments( $id, $value ), $post_id );
		switch ( $id ) {
			case 'business_name':
				if ( '' !== (string) $value ) {
					wp_update_post(
						array(
							'ID'         => $post_id,
							'post_title' => (string) $value,
						)
					);
				}
				return;
			case 'description':
				wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => (string) $value,
					)
				);
				return;
			case 'categories':
				wp_set_object_terms( $post_id, array_map( 'intval', (array) $value ), ID::TAX_CATEGORY );
				return;
			case 'cover':
				if ( (int) $value > 0 ) {
					set_post_thumbnail( $post_id, (int) $value );
				} else {
					delete_post_thumbnail( $post_id );
				}
				return;
		}
		$field = FieldRegistry::get( $id );
		if ( ! $field ) {
			return;
		}
		if ( Sanitizer::isEmpty( $field, $value ) ) {
			delete_post_meta( $post_id, ID::meta( $id ) );
		} else {
			update_post_meta( $post_id, ID::meta( $id ), $value );
		}
	}

	/**
	 * Value as the visitor experiences it, for comparisons: an unset toggle means its default.
	 *
	 * @param string $id    Item id.
	 * @param mixed  $value Stored or sanitized value.
	 * @return mixed
	 */
	public static function effective( string $id, $value ) {
		if ( 'description' === $id ) {
			// Compare as a representative could have written it, so line endings and markup the
			// form can't express never look like a change.
			return self::cleanDescription( is_string( $value ) ? $value : '' );
		}
		$item = self::item( $id );
		if ( $item && 'toggle' === $item['type'] ) {
			if ( '' === $value || null === $value ) {
				return '1' === (string) $item['default'] ? '1' : '0';
			}
			return '1' === (string) $value ? '1' : '0';
		}
		return $value;
	}

	/**
	 * Attachment ids referenced by a value (image, gallery, cover).
	 *
	 * @param string $id    Item id.
	 * @param mixed  $value Value.
	 * @return list<int>
	 */
	public static function attachments( string $id, $value ): array {
		$item = self::item( $id );
		if ( ! $item || ! in_array( $item['type'], array( 'image', 'gallery' ), true ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'intval', is_array( $value ) ? $value : array( $value ) ) ) );
	}

	/**
	 * Short, escaped HTML rendering of a value for review screens and emails.
	 *
	 * @param string $id    Item id.
	 * @param mixed  $value Value.
	 */
	public static function display( string $id, $value ): string {
		$value = self::effective( $id, $value );
		$item  = self::item( $id );
		if ( ! $item ) {
			return '';
		}
		if ( 'categories' === $id ) {
			$names = array();
			foreach ( (array) $value as $term_id ) {
				$term = get_term( (int) $term_id, ID::TAX_CATEGORY );
				if ( $term instanceof \WP_Term ) {
					$names[] = $term->name;
				}
			}
			return esc_html( implode( ', ', $names ) );
		}
		switch ( $item['type'] ) {
			case 'image':
			case 'gallery':
				$html = '';
				foreach ( self::attachments( $id, $value ) as $attachment ) {
					$html .= (string) wp_get_attachment_image( $attachment, 'thumbnail', false, array( 'loading' => 'lazy' ) );
				}
				return $html;
			case 'toggle':
				return '1' === (string) $value ? esc_html__( 'Yes', 'favr-directory' ) : esc_html__( 'No', 'favr-directory' );
			case 'checkboxes':
				$labels = array();
				foreach ( (array) $value as $key ) {
					$labels[] = (string) ( $item['options'][ $key ] ?? $key );
				}
				return esc_html( implode( ', ', $labels ) );
			case 'select':
			case 'radio':
				return esc_html( (string) ( $item['options'][ (string) $value ] ?? $value ) );
			case 'hours':
				return self::hoursText( is_array( $value ) ? $value : array() );
			case 'repeater':
				$lines = array();
				foreach ( (array) $value as $row ) {
					$lines[] = esc_html( implode( ' — ', array_filter( array_map( 'strval', (array) $row ) ) ) );
				}
				return implode( '<br>', $lines );
			case 'textarea':
				$text = wp_strip_all_tags( (string) $value );
				return nl2br( esc_html( mb_strlen( $text ) > 600 ? mb_substr( $text, 0, 600 ) . '…' : $text ) );
		}
		return is_scalar( $value ) ? esc_html( (string) $value ) : '';
	}

	/**
	 * Hours as escaped lines.
	 *
	 * @param array<string, array<string, string>> $hours Schedule.
	 */
	private static function hoursText( array $hours ): string {
		if ( array() === $hours ) {
			return '';
		}
		$labels = Hours::dayLabels();
		$format = (string) get_option( 'time_format', 'g:i a' );
		$lines  = array();
		foreach ( Hours::grouped( $hours ) as $group ) {
			$first = $labels[ $group['days'][0] ] ?? $group['days'][0];
			$last  = $labels[ end( $group['days'] ) ] ?? '';
			$days  = count( $group['days'] ) > 1 ? $first . ' – ' . $last : $first;
			if ( 'open' === $group['status'] ) {
				$time = Hours::formatTime( $group['open'], $format ) . ' – ' . Hours::formatTime( $group['close'], $format );
			} else {
				$time = '24h' === $group['status'] ? __( 'Open 24 hours', 'favr-directory' ) : __( 'Closed', 'favr-directory' );
			}
			$lines[] = esc_html( $days . ': ' . $time );
		}
		return implode( '<br>', $lines );
	}

	/**
	 * Description as stored from the front end: allowed tags only, LF line endings, no block
	 * comments, trimmed.
	 *
	 * @param string $html Raw.
	 */
	public static function cleanDescription( string $html ): string {
		$html = str_replace( array( "\r\n", "\r" ), "\n", $html );
		$html = (string) preg_replace( '/<!--.*?-->/s', '', $html );
		$html = wp_kses( $html, self::descriptionHtml() );
		return trim( (string) preg_replace( "/\n{3,}/", "\n\n", $html ) );
	}

	/**
	 * Tags allowed in a representative-written description.
	 *
	 * @return array<string, array<string, bool>>
	 */
	public static function descriptionHtml(): array {
		return array(
			'p'          => array(),
			'br'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'a'          => array( 'href' => true ),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'blockquote' => array(),
		);
	}
}
