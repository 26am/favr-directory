<?php
/**
 * Structured data.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;

/**
 * Schema.org LocalBusiness JSON-LD on business pages, so listings can show rich results
 * (address, hours, phone) in search engines.
 */
final class Seo {

	/** Hook. */
	public function hook(): void {
		add_action( 'wp_head', array( $this, 'jsonLd' ), 30 );
		add_filter( 'post_type_archive_title', array( $this, 'archiveTitle' ), 10, 2 );
	}

	/**
	 * Use the configured directory title for the archive (document title, headings).
	 *
	 * @param string $title     Title.
	 * @param string $post_type Post type.
	 */
	public function archiveTitle( string $title, string $post_type ): string {
		return ID::POST_TYPE === $post_type ? (string) \FavrDirectory\Support\Settings::get( 'directory_title' ) : $title;
	}

	/** Print JSON-LD. */
	public function jsonLd(): void {
		if ( ! is_singular( ID::POST_TYPE ) ) {
			return;
		}
		$business = Business::find( (int) get_queried_object_id() );
		if ( ! $business ) {
			return;
		}
		/**
		 * Filter the LocalBusiness structured data (return an empty array to disable).
		 *
		 * @param array    $data     Schema data.
		 * @param Business $business Business.
		 */
		$data = (array) apply_filters( 'favr_directory_schema', self::schema( $business ), $business );
		if ( array() === $data ) {
			return;
		}
		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP )
		);
	}

	/**
	 * Build the schema array.
	 *
	 * @param Business $business Business.
	 * @return array<string, mixed>
	 */
	public static function schema( Business $business ): array {
		$data = array(
			'@context' => 'https://schema.org',
			'@type'    => 'LocalBusiness',
			'@id'      => $business->url() . '#business',
			'name'     => wp_strip_all_tags( $business->name() ),
			'url'      => '' !== $business->text( 'website' ) ? $business->text( 'website' ) : $business->url(),
		);

		$summary = $business->summary();
		if ( '' !== $summary ) {
			$data['description'] = wp_strip_all_tags( $summary );
		}
		if ( '' !== $business->text( 'phone' ) ) {
			$data['telephone'] = $business->text( 'phone' );
		}
		if ( '' !== $business->publicEmail() ) {
			$data['email'] = $business->publicEmail();
		}
		if ( $business->logoId() ) {
			$data['logo'] = (string) wp_get_attachment_image_url( $business->logoId(), 'full' );
		}
		$image = $business->coverId() ?: $business->logoId();
		if ( $image ) {
			$data['image'] = (string) wp_get_attachment_image_url( $image, 'full' );
		}
		if ( $business->hasAddress() ) {
			$data['address'] = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => trim( $business->text( 'address_1' ) . ' ' . $business->text( 'address_2' ) ),
					'addressLocality' => $business->text( 'city' ),
					'addressRegion'   => $business->text( 'state' ),
					'postalCode'      => $business->text( 'postal_code' ),
					'addressCountry'  => $business->text( 'country' ),
				)
			);
			$lat             = $business->text( 'latitude' );
			$lng             = $business->text( 'longitude' );
			if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
				$data['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $lat,
					'longitude' => (float) $lng,
				);
			}
		}
		if ( '' !== $business->text( 'service_area' ) ) {
			$data['areaServed'] = $business->text( 'service_area' );
		}
		$same_as = array_values( $business->social() );
		if ( $same_as ) {
			$data['sameAs'] = $same_as;
		}
		$hours = self::openingHours( $business->hours() );
		if ( $hours ) {
			$data['openingHoursSpecification'] = $hours;
		}
		$year = (int) $business->field( 'year_established' );
		if ( $year > 0 ) {
			$data['foundingDate'] = (string) $year;
		}
		return $data;
	}

	/**
	 * OpeningHoursSpecification entries.
	 *
	 * @param array<string, array<string, string>> $hours Schedule.
	 * @return list<array<string, mixed>>
	 */
	private static function openingHours( array $hours ): array {
		$names = array(
			'mon' => 'Monday',
			'tue' => 'Tuesday',
			'wed' => 'Wednesday',
			'thu' => 'Thursday',
			'fri' => 'Friday',
			'sat' => 'Saturday',
			'sun' => 'Sunday',
		);
		$out   = array();
		foreach ( $names as $day => $name ) {
			$row = $hours[ $day ] ?? null;
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( 'open' === ( $row['status'] ?? '' ) ) {
				$out[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $name,
					'opens'     => $row['open'],
					'closes'    => $row['close'],
				);
			} elseif ( '24h' === ( $row['status'] ?? '' ) ) {
				$out[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $name,
					'opens'     => '00:00',
					'closes'    => '23:59',
				);
			}
		}
		return $out;
	}
}
