<?php
/**
 * Structured data, robots and sitemap integration.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;

/**
 * Search-engine layer:
 *  - Business pages: LocalBusiness (memberOf the site's organization) + BreadcrumbList.
 *  - Directory / category pages: ItemList of the listed businesses + BreadcrumbList.
 *  - Yoast SEO or Rank Math active: our entities are merged into THEIR graph (one JSON-LD
 *    block, shared #organization id, their breadcrumbs), otherwise we print our own graph.
 *  - Filtered directory URLs (?fd_q, ?fd_letter, ...) are noindex,follow to avoid crawl traps.
 *  - The directory page itself is added to the core sitemap (core never lists archives).
 */
final class Seo {

	/**
	 * Whether an SEO plugin merged our entities into its graph on this request.
	 *
	 * @var bool
	 */
	private static bool $merged = false;

	/** Hook. */
	public function hook(): void {
		add_filter( 'post_type_archive_title', array( $this, 'archiveTitle' ), 10, 2 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_action( 'init', array( $this, 'registerSitemap' ), 20 );

		// Output: integrate with an SEO plugin when present, else print our own graph.
		add_filter( 'wpseo_schema_graph', array( $this, 'yoastGraph' ), 20 );
		add_filter( 'rank_math/json_ld', array( $this, 'rankMathGraph' ), 99 );
		add_filter( 'wpseo_breadcrumb_links', array( $this, 'yoastBreadcrumbs' ) );
		// Late enough that Yoast / Rank Math (wp_head priority 1) have already printed.
		add_action( 'wp_head', array( $this, 'printGraph' ), 30 );
	}

	/**
	 * Use the configured directory title for the archive (document title, headings).
	 *
	 * @param string $title     Title.
	 * @param string $post_type Post type.
	 */
	public function archiveTitle( string $title, string $post_type ): string {
		return ID::POST_TYPE === $post_type ? (string) Settings::get( 'directory_title' ) : $title;
	}

	/* ------------------------------------------------------------ Output */

	/**
	 * Standalone JSON-LD, unless an SEO plugin already merged our entities into its own graph
	 * on this request. Checking what actually happened (rather than whether a plugin is merely
	 * active) keeps the markup when e.g. Rank Math's schema module is switched off.
	 */
	public function printGraph(): void {
		if ( self::$merged ) {
			return;
		}
		$graph = self::graph( true );
		if ( array() === $graph ) {
			return;
		}
		printf(
			'<script type="application/ld+json" class="favr-directory-schema">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP
			)
		);
	}

	/**
	 * Yoast SEO: append our pieces to its graph (Yoast supplies the breadcrumbs).
	 *
	 * @param mixed $graph Graph pieces.
	 * @return array<int, array<string, mixed>>
	 */
	public function yoastGraph( $graph ): array {
		$graph = is_array( $graph ) ? $graph : array();
		foreach ( self::graph( false ) as $piece ) {
			$graph[] = $piece;
		}
		self::$merged = true;
		return $graph;
	}

	/**
	 * Yoast breadcrumbs (visible and schema) follow our trail on directory pages:
	 * Home › Directory › Category › Business, instead of the post type's generic label.
	 *
	 * @param mixed $links Yoast links: list of { url, text }.
	 * @return array<int, array<string, mixed>>
	 */
	public function yoastBreadcrumbs( $links ): array {
		$links = is_array( $links ) ? $links : array();
		if ( ! ( is_singular( ID::POST_TYPE ) || is_post_type_archive( ID::POST_TYPE ) || is_tax( ID::TAX_CATEGORY ) ) ) {
			return $links;
		}
		$out = array();
		foreach ( self::trail() as $index => $crumb ) {
			// Keep Yoast's own home crumb (its configured label).
			$out[] = 0 === $index && isset( $links[0] ) ? $links[0] : array(
				'url'  => $crumb[1],
				'text' => $crumb[0],
			);
		}
		return $out;
	}

	/**
	 * Rank Math: add our entities; add our breadcrumbs only if Rank Math has none.
	 *
	 * @param mixed $data JSON-LD entities keyed by name.
	 * @return array<string, mixed>
	 */
	public function rankMathGraph( $data ): array {
		$data = is_array( $data ) ? $data : array();
		// Our trail replaces Rank Math's on directory pages (same reasoning as Yoast).
		foreach ( self::graph( true ) as $index => $piece ) {
			$key          = 'BreadcrumbList' === $piece['@type'] ? 'BreadcrumbList' : 'favr_' . $index;
			$data[ $key ] = $piece;
		}
		self::$merged = true;
		return $data;
	}

	/* ------------------------------------------------------------- Graph */

	/**
	 * Entities for the current request (no @context; ready for any graph).
	 *
	 * @param bool $with_breadcrumbs Include our BreadcrumbList.
	 * @return list<array<string, mixed>>
	 */
	public static function graph( bool $with_breadcrumbs ): array {
		$graph = array();

		if ( is_singular( ID::POST_TYPE ) ) {
			$business = Business::find( (int) get_queried_object_id() );
			if ( $business && 'publish' === $business->post()->post_status ) {
				$graph[] = self::schema( $business );
			}
		} elseif ( ( is_post_type_archive( ID::POST_TYPE ) || is_tax( ID::TAX_CATEGORY ) ) && ! Directory::isFilteredRequest() ) {
			$list = self::itemList();
			if ( $list ) {
				$graph[] = $list;
			}
		}

		if ( $with_breadcrumbs && $graph ) {
			$graph[] = self::breadcrumbs();
		}

		/**
		 * Filter the directory's structured data (return an empty array to disable).
		 *
		 * @param array $graph Schema.org entities.
		 */
		return array_values( (array) apply_filters( 'favr_directory_schema_graph', $graph ) );
	}

	/**
	 * The organization that runs the directory (the chamber / association). Its @id matches
	 * the one Yoast and Rank Math use, so in their graphs both describe the same node.
	 *
	 * @return array<string, string>
	 */
	public static function organization(): array {
		$name = (string) Settings::get( 'organization_name' );
		return array(
			'@type' => 'Organization',
			'@id'   => home_url( '/#organization' ),
			'name'  => '' !== $name ? $name : wp_strip_all_tags( (string) get_bloginfo( 'name' ) ),
			'url'   => home_url( '/' ),
		);
	}

	/**
	 * LocalBusiness for one business.
	 *
	 * @param Business $business Business.
	 * @return array<string, mixed>
	 */
	public static function schema( Business $business ): array {
		$data = array(
			'@type'            => 'LocalBusiness',
			'@id'              => $business->url() . '#business',
			'name'             => wp_strip_all_tags( $business->name() ),
			'url'              => '' !== $business->text( 'website' ) ? $business->text( 'website' ) : $business->url(),
			'mainEntityOfPage' => $business->url(),
			'memberOf'         => self::organization(),
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
		$image_ids = array_values( array_unique( array_filter( array_merge( array( $business->coverId(), $business->logoId() ), array_slice( $business->gallery(), 0, 5 ) ) ) ) );
		$images    = array_values( array_filter( array_map( static fn( int $id ): string => (string) wp_get_attachment_image_url( $id, 'full' ), $image_ids ) ) );
		if ( $images ) {
			$data['image'] = $images;
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

			$lat = $business->text( 'latitude' );
			$lng = $business->text( 'longitude' );
			if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
				$data['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => (float) $lat,
					'longitude' => (float) $lng,
				);
			}
			$data['hasMap'] = $business->directionsUrl();
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
		$categories = array_map(
			static fn( \WP_Term $term ): string => html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ),
			$business->categories()
		);
		if ( $categories ) {
			$data['knowsAbout'] = array_values( $categories );
		}

		/**
		 * Filter one business's LocalBusiness entity (e.g. switch @type to "Restaurant").
		 *
		 * @param array    $data     Schema data.
		 * @param Business $business Business.
		 */
		return (array) apply_filters( 'favr_directory_schema', $data, $business );
	}

	/**
	 * ItemList of the businesses on the current directory / category page (Google's
	 * "summary page" pattern: each item is the URL of its own detail page).
	 *
	 * @return array<string, mixed>|null
	 */
	public static function itemList(): ?array {
		$args  = Directory::queryArgs( Directory::normalize( array() ), Directory::request() );
		$query = DirectoryQuery::run( $args );
		if ( ! $query->posts ) {
			return null;
		}
		$offset = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
		$items  = array();
		foreach ( $query->posts as $index => $post ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $offset + $index + 1,
				'url'      => (string) get_permalink( $post ),
				'name'     => wp_strip_all_tags( get_the_title( $post ) ),
			);
		}
		$term = is_tax( ID::TAX_CATEGORY ) ? get_queried_object() : null;
		return array(
			'@type'           => 'ItemList',
			'@id'             => self::currentUrl() . '#directory',
			'name'            => $term instanceof \WP_Term ? html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) : (string) Settings::get( 'directory_title' ),
			'numberOfItems'   => (int) $query->found_posts,
			'itemListElement' => $items,
		);
	}

	/**
	 * BreadcrumbList for the current page.
	 *
	 * @return array<string, mixed>
	 */
	public static function breadcrumbs(): array {
		$items = array();
		foreach ( self::trail() as $index => $crumb ) {
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb[0],
				'item'     => $crumb[1],
			);
		}
		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => self::currentUrl() . '#breadcrumb',
			'itemListElement' => $items,
		);
	}

	/**
	 * Home › Directory › Category (› parents) › Business, as [ name, url ] pairs.
	 *
	 * @return list<array{0: string, 1: string}>
	 */
	public static function trail(): array {
		$trail = array(
			array( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ), home_url( '/' ) ),
			array( (string) Settings::get( 'directory_title' ), (string) get_post_type_archive_link( ID::POST_TYPE ) ),
		);

		$term = null;
		if ( is_tax( ID::TAX_CATEGORY ) ) {
			$term = get_queried_object();
		} elseif ( is_singular( ID::POST_TYPE ) ) {
			$business   = Business::find( (int) get_queried_object_id() );
			$categories = $business ? $business->categories() : array();
			$term       = $categories[0] ?? null;
		}
		if ( $term instanceof \WP_Term ) {
			$chain = array_reverse( get_ancestors( $term->term_id, ID::TAX_CATEGORY, 'taxonomy' ) );
			foreach ( array_merge( $chain, array( $term->term_id ) ) as $term_id ) {
				$node = get_term( (int) $term_id, ID::TAX_CATEGORY );
				$link = $node instanceof \WP_Term ? get_term_link( $node ) : '';
				if ( $node instanceof \WP_Term && is_string( $link ) ) {
					$trail[] = array( html_entity_decode( $node->name, ENT_QUOTES, 'UTF-8' ), $link );
				}
			}
		}
		if ( is_singular( ID::POST_TYPE ) ) {
			$trail[] = array( wp_strip_all_tags( get_the_title( get_queried_object_id() ) ), (string) get_permalink( get_queried_object_id() ) );
		}
		return $trail;
	}

	/**
	 * OpeningHoursSpecification entries.
	 *
	 * @param array<string, array<string, string>> $hours Schedule.
	 * @return list<array<string, mixed>>
	 */
	public static function openingHours( array $hours ): array {
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
			$row    = $hours[ $day ] ?? null;
			$status = is_array( $row ) ? ( $row['status'] ?? '' ) : '';
			if ( 'open' === $status ) {
				$out[] = array(
					'@type'     => 'OpeningHoursSpecification',
					'dayOfWeek' => 'https://schema.org/' . $name,
					'opens'     => $row['open'],
					'closes'    => $row['close'],
				);
			} elseif ( '24h' === $status ) {
				// Google's documented form for "open 24 hours".
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

	/** Current page URL without directory params (stable @id base). */
	private static function currentUrl(): string {
		if ( is_tax( ID::TAX_CATEGORY ) ) {
			$link = get_term_link( get_queried_object() );
			return is_string( $link ) ? $link : home_url( '/' );
		}
		if ( is_singular() ) {
			return (string) get_permalink( get_queried_object_id() );
		}
		return (string) get_post_type_archive_link( ID::POST_TYPE );
	}

	/* ------------------------------------------------------- Robots / map */

	/**
	 * Keep filtered/search result URLs out of the index (they are near-infinite duplicates
	 * of the directory and category pages), while letting crawlers follow their links.
	 *
	 * @param array<string, bool|string> $robots Robots directives.
	 * @return array<string, bool|string>
	 */
	public function robots( array $robots ): array {
		if ( Directory::isFilteredRequest() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/** Add the directory page to the core sitemap (/wp-sitemap.xml). */
	public function registerSitemap(): void {
		if ( function_exists( 'wp_register_sitemap_provider' ) && class_exists( '\WP_Sitemaps_Provider' ) ) {
			wp_register_sitemap_provider( 'favrdirectory', new SitemapProvider() );
		}
	}
}
