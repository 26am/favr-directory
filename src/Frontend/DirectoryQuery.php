<?php
/**
 * Builds and runs directory listing queries.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Model\Ranking;
use FavrDirectory\Schema\Identifiers as ID;

/**
 * Wraps WP_Query with two directory-specific capabilities WP_Query lacks:
 *  - `favr_search`: matches name, description, tagline, summary, city, service area AND
 *    category names (so "plumber" finds businesses in the Plumbing category).
 *  - `favr_letter`: first letter of the business name (A–Z, or "#" for digits).
 * Both are implemented as prepared SQL fragments, scoped to queries that carry the var.
 */
final class DirectoryQuery {

	/** Meta keys searched alongside title/content. */
	private const SEARCH_META = array( 'tagline', 'summary', 'city', 'service_area' );

	/** Hook the SQL filters. */
	public static function hook(): void {
		add_filter( 'posts_where', array( self::class, 'where' ), 10, 2 );
	}

	/**
	 * Run a listing query.
	 *
	 * @param array{search?: string, category?: string, level?: string, letter?: string, featured?: bool, page?: int, per_page?: int, order?: string} $args Args.
	 */
	public static function run( array $args ): \WP_Query {
		$per_page = max( 1, (int) ( $args['per_page'] ?? 12 ) );
		$query    = array(
			'post_type'           => ID::POST_TYPE,
			'post_status'         => 'publish',
			'posts_per_page'      => $per_page,
			'paged'               => max( 1, (int) ( $args['page'] ?? 1 ) ),
			'ignore_sticky_posts' => true,
			'favr_directory'      => true,
		);

		$tax = array();
		if ( ! empty( $args['category'] ) ) {
			$tax[] = array(
				'taxonomy'         => ID::TAX_CATEGORY,
				'field'            => 'slug',
				'terms'            => array( sanitize_title( (string) $args['category'] ) ),
				'include_children' => true,
			);
		}
		if ( ! empty( $args['level'] ) ) {
			$tax[] = array(
				'taxonomy' => ID::TAX_LEVEL,
				'field'    => 'slug',
				'terms'    => array( sanitize_title( (string) $args['level'] ) ),
			);
		}
		if ( $tax ) {
			$query['tax_query'] = $tax; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- core filtering.
		}

		$meta = array( 'relation' => 'AND' );
		if ( ! empty( $args['featured'] ) ) {
			$meta[] = array(
				'key'   => ID::meta( 'featured' ),
				'value' => '1',
			);
		}

		$search = trim( (string) ( $args['search'] ?? '' ) );
		if ( '' !== $search ) {
			$query['favr_search'] = mb_substr( $search, 0, 100 );
		}
		$letter = (string) ( $args['letter'] ?? '' );
		if ( '' !== $letter ) {
			$query['favr_letter'] = $letter;
		}

		switch ( $args['order'] ?? 'rank' ) {
			case 'name':
				$query['orderby'] = 'title';
				$query['order']   = 'ASC';
				break;
			case 'newest':
				$query['orderby'] = 'date';
				$query['order']   = 'DESC';
				break;
			case 'random':
				$query['orderby'] = 'rand';
				break;
			default:
				// Featured first, then higher membership tiers, then A–Z (see Ranking). The
				// EXISTS / NOT EXISTS pair keeps a business listed even if its rank is missing.
				$meta[]           = array(
					'relation'  => 'OR',
					'favr_rank' => array(
						'key'     => Ranking::META_KEY,
						'compare' => 'EXISTS',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => Ranking::META_KEY,
						'compare' => 'NOT EXISTS',
					),
				);
				$query['orderby'] = array(
					'favr_rank' => 'ASC',
					'title'     => 'ASC',
				);
		}

		if ( count( $meta ) > 1 ) {
			$query['meta_query'] = $meta; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- small, indexed keys.
		}

		/**
		 * Filter directory WP_Query args before the query runs.
		 *
		 * @param array $query WP_Query args.
		 * @param array $args  Directory args.
		 */
		$query = (array) apply_filters( 'favr_directory_query_args', $query, $args );

		return new \WP_Query( $query );
	}

	/**
	 * Add search / letter conditions.
	 *
	 * @param string    $where SQL WHERE.
	 * @param \WP_Query $query Query.
	 */
	public static function where( string $where, \WP_Query $query ): string {
		global $wpdb;

		$search = (string) $query->get( 'favr_search' );
		if ( '' !== $search ) {
			$like      = '%' . $wpdb->esc_like( $search ) . '%';
			$meta_keys = array_map( array( ID::class, 'meta' ), self::SEARCH_META );
			$in        = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

			// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $in is a list of %s placeholders.
			$where .= $wpdb->prepare(
				" AND ( {$wpdb->posts}.post_title LIKE %s
					OR {$wpdb->posts}.post_content LIKE %s
					OR {$wpdb->posts}.post_excerpt LIKE %s
					OR EXISTS ( SELECT 1 FROM {$wpdb->postmeta} fdm WHERE fdm.post_id = {$wpdb->posts}.ID AND fdm.meta_key IN ( {$in} ) AND fdm.meta_value LIKE %s )
					OR EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} fdr
						INNER JOIN {$wpdb->term_taxonomy} fdt ON fdt.term_taxonomy_id = fdr.term_taxonomy_id AND fdt.taxonomy = %s
						INNER JOIN {$wpdb->terms} fdterm ON fdterm.term_id = fdt.term_id
						WHERE fdr.object_id = {$wpdb->posts}.ID AND fdterm.name LIKE %s ) )",
				array_merge( array( $like, $like, $like ), $meta_keys, array( $like, ID::TAX_CATEGORY, $like ) )
			);
			// phpcs:enable
		}

		$letter = (string) $query->get( 'favr_letter' );
		if ( '#' === $letter ) {
			$where .= " AND {$wpdb->posts}.post_title REGEXP '^[0-9]'";
		} elseif ( 1 === preg_match( '/^[A-Za-z]$/', $letter ) ) {
			$where .= $wpdb->prepare( " AND {$wpdb->posts}.post_title LIKE %s", $wpdb->esc_like( strtoupper( $letter ) ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		}

		return $where;
	}

	/**
	 * Letters that have at least one published business (for the A–Z bar).
	 *
	 * @return list<string>
	 */
	public static function availableLetters(): array {
		global $wpdb;
		$cache = wp_cache_get( 'letters', 'favr_directory' );
		if ( is_array( $cache ) ) {
			return $cache;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- cached below.
		$rows    = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT UPPER( LEFT( post_title, 1 ) ) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish'",
				ID::POST_TYPE
			)
		);
		$letters = array();
		foreach ( (array) $rows as $char ) {
			$char      = (string) $char;
			$letters[] = ctype_digit( $char ) ? '#' : $char;
		}
		$letters = array_values( array_unique( $letters ) );
		wp_cache_set( 'letters', $letters, 'favr_directory', HOUR_IN_SECONDS );
		return $letters;
	}
}
