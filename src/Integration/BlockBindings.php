<?php
/**
 * Block bindings: connect core blocks to business data.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration;

/**
 * Source `favr-directory/business` (WordPress 6.5+). In a single-business template, bind a
 * Paragraph, Heading, Button or Image to a business value, e.g.:
 *
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"favr-directory/business","args":{"key":"phone"}}}}} -->
 *
 * Text attributes use FieldValues::text(); link attributes (url, href) use FieldValues::url();
 * an Image's url/id/alt use the logo or cover. Same values as the Elementor dynamic tags.
 */
final class BlockBindings {

	/** Hook. */
	public function hook(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	/** Register the source. */
	public function register(): void {
		if ( ! function_exists( 'register_block_bindings_source' ) ) {
			return;
		}
		register_block_bindings_source(
			'favr-directory/business',
			array(
				'label'              => __( 'Business (Favr Directory)', 'favr-directory' ),
				'get_value_callback' => array( $this, 'value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);
	}

	/**
	 * Value for a bound attribute.
	 *
	 * @param array<string, mixed> $args      { key }.
	 * @param \WP_Block            $block     Block.
	 * @param string               $attribute Bound attribute.
	 * @return string|int|null
	 */
	public function value( array $args, $block, string $attribute ) {
		$post_id  = isset( $block->context['postId'] ) ? (int) $block->context['postId'] : 0;
		$business = FieldValues::business( $post_id );
		$key      = sanitize_key( (string) ( $args['key'] ?? '' ) );
		if ( ! $business || '' === $key ) {
			return null;
		}
		if ( isset( FieldValues::imageOptions()[ $key ] ) ) {
			$id = FieldValues::imageId( $business, $key );
			if ( ! $id ) {
				return null;
			}
			if ( 'id' === $attribute ) {
				return $id;
			}
			if ( 'alt' === $attribute ) {
				return wp_specialchars_decode( $business->name(), ENT_QUOTES );
			}
			return (string) wp_get_attachment_image_url( $id, 'large' );
		}
		if ( in_array( $attribute, array( 'url', 'href' ), true ) ) {
			return FieldValues::url( $business, $key ) ?: null;
		}
		$text = FieldValues::text( $business, $key );
		return '' === $text ? null : esc_html( $text );
	}
}
