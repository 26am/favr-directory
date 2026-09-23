<?php
/**
 * Shortcodes for classic content and page builders.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;

/**
 * [favr_directory …]            The directory (same options as the block).
 * [favr_business id="…"]        One full profile (defaults to the current business).
 * [favr_business_field field=…] One value, for page-builder templates (Elementor etc.).
 */
final class Shortcodes {

	/** Register. */
	public function hook(): void {
		add_shortcode( ID::SHORTCODE_DIR, array( $this, 'directory' ) );
		add_shortcode( ID::SHORTCODE_PROFILE, array( $this, 'profile' ) );
		add_shortcode( ID::SHORTCODE_FIELD, array( $this, 'field' ) );
	}

	/**
	 * [favr_directory].
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function directory( $atts ): string {
		$atts = is_array( $atts ) ? $atts : array();
		return Directory::render( shortcode_atts( Directory::defaults(), $atts, ID::SHORTCODE_DIR ) );
	}

	/**
	 * [favr_business].
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function profile( $atts ): string {
		$atts     = shortcode_atts( array( 'id' => 0 ), is_array( $atts ) ? $atts : array(), ID::SHORTCODE_PROFILE );
		$id       = (int) $atts['id'] ?: (int) get_the_ID();
		$business = Business::find( $id );
		if ( ! $business || ! self::visible( $business ) || Profile::isRendering() ) {
			return '';
		}
		return Profile::render( $business, 'page' );
	}

	/**
	 * Whether the current visitor may see a business (published, or readable by them).
	 *
	 * @param Business $business Business.
	 */
	public static function visible( Business $business ): bool {
		return 'publish' === $business->post()->post_status || current_user_can( 'read_post', $business->id() );
	}

	/**
	 * [favr_business_field field="phone" id="" link="1"].
	 *
	 * @param array<string, string>|string $atts Attributes.
	 */
	public function field( $atts ): string {
		$atts     = shortcode_atts(
			array(
				'field' => '',
				'id'    => 0,
				'link'  => '1',
			),
			is_array( $atts ) ? $atts : array(),
			ID::SHORTCODE_FIELD
		);
		$business = Business::find( (int) $atts['id'] ?: (int) get_the_ID() );
		$key      = sanitize_key( $atts['field'] );
		if ( ! $business || '' === $key || ! self::visible( $business ) ) {
			return '';
		}
		$link = '0' !== $atts['link'];

		switch ( $key ) {
			case 'address':
				return esc_html( $business->addressText() );
			case 'categories':
				return esc_html( implode( ', ', wp_list_pluck( $business->categories(), 'name' ) ) );
			case 'level':
				return $business->level() ? esc_html( $business->level()->name ) : '';
			case 'phone':
			case 'phone_alt':
				$phone = $business->text( $key );
				return '' === $phone ? '' : ( $link ? sprintf( '<a href="%s">%s</a>', esc_attr( Business::telHref( $phone ) ), esc_html( $phone ) ) : esc_html( $phone ) );
			case 'email':
				$email = $business->publicEmail();
				return '' === $email ? '' : ( $link ? sprintf( '<a href="mailto:%1$s">%2$s</a>', esc_attr( antispambot( $email ) ), esc_html( antispambot( $email ) ) ) : esc_html( antispambot( $email ) ) );
			case 'logo':
				return $business->logoId() ? wp_get_attachment_image( $business->logoId(), 'medium', false, array( 'class' => 'favr-logo' ) ) : '';
			case 'hours':
				return Template::render( 'parts/hours', array( 'business' => $business ) );
			case 'map':
				return Template::render( 'parts/map', array( 'business' => $business ) );
			case 'social':
				return Template::render( 'parts/social', array( 'business' => $business ) );
		}

		$field = FieldRegistry::get( $key );
		if ( ! $field || $field['private'] ) {
			return '';
		}
		$value = $business->field( $key );
		if ( 'url' === $field['type'] ) {
			$url = (string) $value;
			return '' === $url ? '' : ( $link ? sprintf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $url ), esc_html( (string) preg_replace( '#^https?://(www\.)?#', '', rtrim( $url, '/' ) ) ) ) : esc_url( $url ) );
		}
		if ( is_array( $value ) ) {
			return esc_html( implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) ) );
		}
		return nl2br( esc_html( (string) $value ) );
	}
}
