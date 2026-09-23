<?php
/**
 * Read model for one business listing.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Model;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Completeness;
use FavrDirectory\Vendor\FavrCore\Support\Hours;

/**
 * Presentation-ready accessors over a business post. Values are raw (unescaped); templates
 * escape at output. Empty values come back as '' / array() so templates can skip them.
 */
final class Business {

	/**
	 * The business post.
	 *
	 * @var \WP_Post
	 */
	private \WP_Post $post;

	/**
	 * Meta values read so far, keyed by field id.
	 *
	 * @var array<string, mixed>
	 */
	private array $cache = array();

	/**
	 * Constructor.
	 *
	 * @param \WP_Post $post Business post.
	 */
	public function __construct( \WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Load by id; null when the id is not a business.
	 *
	 * @param int $id Post id.
	 */
	public static function find( int $id ): ?self {
		$post = get_post( $id );
		return ( $post instanceof \WP_Post && ID::POST_TYPE === $post->post_type ) ? new self( $post ) : null;
	}

	/** Post id. */
	public function id(): int {
		return (int) $this->post->ID;
	}

	/** Underlying post. */
	public function post(): \WP_Post {
		return $this->post;
	}

	/** Business name. */
	public function name(): string {
		return get_the_title( $this->post );
	}

	/** Profile URL. */
	public function url(): string {
		return (string) get_permalink( $this->post );
	}

	/**
	 * Raw stored value of a registry field.
	 *
	 * @param string $id Field id.
	 * @return mixed
	 */
	public function field( string $id ) {
		if ( ! array_key_exists( $id, $this->cache ) ) {
			$this->cache[ $id ] = get_post_meta( $this->id(), ID::meta( $id ), true );
		}
		return $this->cache[ $id ];
	}

	/**
	 * Field as a trimmed string.
	 *
	 * @param string $id Field id.
	 */
	public function text( string $id ): string {
		$value = $this->field( $id );
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Toggle field state.
	 *
	 * @param string $id Field id.
	 */
	public function flag( string $id ): bool {
		return '1' === $this->text( $id );
	}

	/**
	 * Field as an array.
	 *
	 * @param string $id Field id.
	 * @return array<mixed>
	 */
	public function list( string $id ): array {
		$value = $this->field( $id );
		return is_array( $value ) ? $value : array();
	}

	/** Short description: summary field, then excerpt, then trimmed content. */
	public function summary(): string {
		$summary = $this->text( 'summary' );
		if ( '' !== $summary ) {
			return $summary;
		}
		if ( '' !== trim( $this->post->post_excerpt ) ) {
			return trim( $this->post->post_excerpt );
		}
		return wp_trim_words( wp_strip_all_tags( strip_shortcodes( $this->post->post_content ) ), 26 );
	}

	/** Logo attachment id (0 when none). */
	public function logoId(): int {
		return (int) $this->field( 'logo' );
	}

	/** Cover (featured image) attachment id. */
	public function coverId(): int {
		return (int) get_post_thumbnail_id( $this->post );
	}

	/** Whether the listing is featured. */
	public function isFeatured(): bool {
		return $this->flag( 'featured' );
	}

	/**
	 * Business categories.
	 *
	 * @return list<\WP_Term>
	 */
	public function categories(): array {
		$terms = get_the_terms( $this->post, ID::TAX_CATEGORY );
		return is_array( $terms ) ? array_values( $terms ) : array();
	}

	/** Membership level, if assigned. */
	public function level(): ?\WP_Term {
		$terms = get_the_terms( $this->post, ID::TAX_LEVEL );
		return is_array( $terms ) && isset( $terms[0] ) ? $terms[0] : null;
	}

	/** Public email (respects the "show email" toggle). */
	public function publicEmail(): string {
		return $this->flag( 'show_email' ) ? $this->text( 'email' ) : '';
	}

	/**
	 * The tel: href for a phone number.
	 *
	 * @param string $phone Display phone.
	 */
	public static function telHref( string $phone ): string {
		$digits = (string) preg_replace( '/(?:ext|x|#).*$/i', '', $phone );
		return 'tel:' . preg_replace( '/[^0-9+]/', '', $digits );
	}

	/** Website host for display ("example.com"). */
	public function websiteLabel(): string {
		$host = (string) wp_parse_url( $this->text( 'website' ), PHP_URL_HOST );
		return (string) preg_replace( '/^www\./i', '', $host );
	}

	/** Whether the listing shows a public address. */
	public function hasAddress(): bool {
		return ! $this->flag( 'online_only' ) && ( '' !== $this->text( 'address_1' ) || '' !== $this->text( 'city' ) );
	}

	/**
	 * Address as display lines.
	 *
	 * @return list<string>
	 */
	public function addressLines(): array {
		if ( ! $this->hasAddress() ) {
			return array();
		}
		$street   = trim( $this->text( 'address_1' ) . ( '' !== $this->text( 'address_2' ) ? ', ' . $this->text( 'address_2' ) : '' ), ', ' );
		$locality = trim( $this->text( 'city' ) . ( '' !== $this->text( 'state' ) ? ', ' . $this->text( 'state' ) : '' ) . ' ' . $this->text( 'postal_code' ) );
		$locality = trim( $locality, ', ' );
		return array_values( array_filter( array( $street, $locality, $this->text( 'country' ) ) ) );
	}

	/**
	 * Languages spoken (from the comma-separated field).
	 *
	 * @return list<string>
	 */
	public function languages(): array {
		return self::splitList( $this->text( 'languages' ) );
	}

	/**
	 * Pure: split "Spanish, Vietnamese / Mandarin" into clean unique items.
	 *
	 * @param string $value Raw.
	 * @return list<string>
	 */
	public static function splitList( string $value ): array {
		$items = array_map( 'trim', preg_split( '/\s*[,;\/|]\s*/', $value ) ?: array() );
		return array_values( array_unique( array_filter( $items, static fn( string $item ): bool => '' !== $item ) ) );
	}

	/** One-line address. */
	public function addressText(): string {
		return implode( ', ', $this->addressLines() );
	}

	/** Short "City, ST" locality for cards. */
	public function locality(): string {
		if ( ! $this->hasAddress() ) {
			return $this->text( 'service_area' );
		}
		return trim( $this->text( 'city' ) . ( '' !== $this->text( 'state' ) ? ', ' . $this->text( 'state' ) : '' ), ', ' );
	}

	/** Query string for maps: coordinates when set, otherwise the address. */
	public function mapQuery(): string {
		$lat = $this->text( 'latitude' );
		$lng = $this->text( 'longitude' );
		if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
			return $lat . ',' . $lng;
		}
		return $this->addressText();
	}

	/** Directions link (opens the visitor's maps app). */
	public function directionsUrl(): string {
		$query = $this->mapQuery();
		return '' === $query ? '' : 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode( $query );
	}

	/**
	 * Weekly schedule.
	 *
	 * @return array<string, array<string, string>>
	 */
	public function hours(): array {
		return $this->list( 'hours' );
	}

	/** Open right now (site timezone); null without a schedule. */
	public function isOpenNow(): ?bool {
		return Hours::isOpenAt( $this->hours(), new \DateTimeImmutable( 'now', wp_timezone() ) );
	}

	/**
	 * Social profile URLs keyed by network.
	 *
	 * @return array<string, string>
	 */
	public function social(): array {
		$out = array();
		foreach ( array_keys( FieldRegistry::socialNetworks() ) as $network ) {
			$url = $this->text( $network );
			if ( '' !== $url ) {
				$out[ $network ] = $url;
			}
		}
		return $out;
	}

	/**
	 * Gallery attachment ids that still exist.
	 *
	 * @return list<int>
	 */
	public function gallery(): array {
		return array_values(
			array_filter(
				array_map( 'intval', $this->list( 'gallery' ) ),
				static fn( int $id ): bool => $id > 0 && wp_attachment_is_image( $id )
			)
		);
	}

	/**
	 * Highlight labels keyed by option.
	 *
	 * @return array<string, string>
	 */
	public function highlights(): array {
		$options = FieldRegistry::highlightOptions();
		$out     = array();
		foreach ( $this->list( 'highlights' ) as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$out[ (string) $key ] = $options[ $key ];
			}
		}
		return $out;
	}

	/**
	 * Active member deal, or null when off or expired.
	 *
	 * @return array{title: string, description: string, code: string, expires: string}|null
	 */
	public function deal(): ?array {
		if ( ! $this->flag( 'has_deal' ) || '' === $this->text( 'deal_title' ) ) {
			return null;
		}
		$expires = $this->text( 'deal_expires' );
		if ( '' !== $expires ) {
			$today = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' );
			if ( $expires < $today ) {
				return null;
			}
		}
		return array(
			'title'       => $this->text( 'deal_title' ),
			'description' => $this->text( 'deal_description' ),
			'code'        => $this->text( 'deal_code' ),
			'expires'     => $expires,
		);
	}

	/**
	 * Additional links with both label and URL present.
	 *
	 * @return list<array{label: string, url: string}>
	 */
	public function links(): array {
		$out = array();
		foreach ( $this->list( 'links' ) as $row ) {
			$url = is_array( $row ) ? (string) ( $row['url'] ?? '' ) : '';
			if ( '' === $url ) {
				continue;
			}
			$label = trim( (string) ( $row['label'] ?? '' ) );
			$out[] = array(
				'label' => '' !== $label ? $label : (string) wp_parse_url( $url, PHP_URL_HOST ),
				'url'   => $url,
			);
		}
		return $out;
	}

	/** Profile completeness, 0–100. */
	public function completeness(): int {
		return Completeness::score( $this->values(), FieldRegistry::all(), $this->coreFlags() );
	}

	/**
	 * Missing weighted fields, most valuable first.
	 *
	 * @return list<string>
	 */
	public function missingFields(): array {
		return Completeness::missing( $this->values(), FieldRegistry::all(), $this->coreFlags() );
	}

	/**
	 * All registry field values.
	 *
	 * @return array<string, mixed>
	 */
	public function values(): array {
		$values = array();
		foreach ( array_keys( FieldRegistry::all() ) as $id ) {
			$values[ $id ] = $this->field( $id );
		}
		return $values;
	}

	/**
	 * Presence of the non-field items that count toward completeness.
	 *
	 * @return array<string, bool>
	 */
	private function coreFlags(): array {
		return array(
			'title'       => '' !== trim( $this->post->post_title ),
			'description' => '' !== trim( wp_strip_all_tags( $this->post->post_content ) ),
			'category'    => array() !== $this->categories(),
			'cover'       => $this->coverId() > 0,
		);
	}
}
