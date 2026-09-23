<?php
/**
 * The searchable directory listing.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;

/**
 * One renderer behind the block, the shortcode and the archive templates. Filtering uses
 * plain GET parameters, so it works without JavaScript, is shareable/bookmarkable and is
 * crawlable; JS only enhances it (instant results).
 */
final class Directory {

	/**
	 * Default attributes.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'per_page'     => (int) Settings::get( 'per_page' ),
			'category'     => '',
			'level'        => '',
			'featured'     => false,
			'search'       => true,
			'letters'      => '1' === Settings::get( 'show_letters' ),
			'level_filter' => false,
			'layout'       => '',
			'order'        => 'rank',
			'title'        => '',
			'intro'        => '',
			'pagination'   => true,
		);
	}

	/**
	 * Normalize shortcode/block attributes.
	 *
	 * @param array<string, mixed> $atts Raw attributes.
	 * @return array<string, mixed>
	 */
	public static function normalize( array $atts ): array {
		$a    = array_merge( self::defaults(), array_intersect_key( $atts, self::defaults() ) );
		$bool = static fn( $v ): bool => is_bool( $v ) ? $v : in_array( strtolower( (string) $v ), array( '1', 'true', 'yes', 'on' ), true );

		$a['per_page']     = min( 100, max( 1, (int) $a['per_page'] ) );
		$a['category']     = sanitize_title( (string) $a['category'] );
		$a['level']        = sanitize_title( (string) $a['level'] );
		$a['featured']     = $bool( $a['featured'] );
		$a['search']       = $bool( $a['search'] );
		$a['letters']      = $bool( $a['letters'] );
		$a['level_filter'] = $bool( $a['level_filter'] );
		$a['pagination']   = $bool( $a['pagination'] );
		$a['layout']       = in_array( $a['layout'], array( 'grid', 'list' ), true ) ? $a['layout'] : (string) Settings::get( 'layout' );
		$a['order']        = in_array( $a['order'], array( 'rank', 'name', 'newest', 'random' ), true ) ? $a['order'] : 'rank';
		$a['title']        = sanitize_text_field( (string) $a['title'] );
		$a['intro']        = wp_kses_post( (string) $a['intro'] );
		return $a;
	}

	/**
	 * Render the directory.
	 *
	 * @param array<string, mixed> $atts Attributes (see defaults()).
	 */
	public static function render( array $atts = array() ): string {
		Assets::enqueue();

		$a           = self::normalize( $atts );
		$interactive = $a['search'] || $a['letters'] || $a['level_filter'];
		$request     = $interactive ? self::request() : array();

		// On a category archive, default to that category.
		$term_category = '';
		if ( is_tax( ID::TAX_CATEGORY ) ) {
			$term = get_queried_object();
			if ( $term instanceof \WP_Term ) {
				$term_category = $term->slug;
			}
		}

		$args = array(
			'search'   => $request['search'] ?? '',
			'category' => ( $request['category'] ?? '' ) ?: ( $a['category'] ?: $term_category ),
			'level'    => ( $request['level'] ?? '' ) ?: $a['level'],
			'letter'   => $request['letter'] ?? '',
			'featured' => $a['featured'],
			'page'     => $a['pagination'] ? ( $request['page'] ?? 1 ) : 1,
			'per_page' => $a['per_page'],
			'order'    => $a['order'],
		);

		$query      = DirectoryQuery::run( $args );
		$businesses = array_map( static fn( \WP_Post $p ): Business => new Business( $p ), $query->posts );
		$filtered   = '' !== $args['search'] || '' !== $args['letter'] || ( $request['category'] ?? '' ) !== '' || ( $request['level'] ?? '' ) !== '';

		return Template::render(
			'directory',
			array(
				'atts'        => $a,
				'args'        => $args,
				'query'       => $query,
				'businesses'  => $businesses,
				'interactive' => $interactive,
				'filtered'    => $filtered,
				'categories'  => $a['search'] ? self::categoryOptions() : array(),
				'levels'      => $a['level_filter'] ? self::levelOptions() : array(),
				'letters'     => $a['letters'] ? DirectoryQuery::availableLetters() : array(),
				'action'      => self::baseUrl(),
				'pagination'  => $a['pagination'] ? self::pagination( $query, (int) $args['page'] ) : '',
				'show_open'   => '1' === Settings::get( 'show_open_now' ),
			)
		);
	}

	/**
	 * Read the directory's GET parameters.
	 *
	 * @return array{search: string, category: string, level: string, letter: string, page: int}
	 */
	public static function request(): array {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- public, read-only filters.
		$letter = isset( $_GET[ ID::QV_LETTER ] ) ? sanitize_text_field( wp_unslash( $_GET[ ID::QV_LETTER ] ) ) : '';
		$out    = array(
			'search'   => isset( $_GET[ ID::QV_SEARCH ] ) ? sanitize_text_field( wp_unslash( $_GET[ ID::QV_SEARCH ] ) ) : '',
			'category' => isset( $_GET[ ID::QV_CATEGORY ] ) ? sanitize_title( wp_unslash( $_GET[ ID::QV_CATEGORY ] ) ) : '',
			'level'    => isset( $_GET[ ID::QV_LEVEL ] ) ? sanitize_title( wp_unslash( $_GET[ ID::QV_LEVEL ] ) ) : '',
			'letter'   => preg_match( '/^([A-Za-z]|#)$/', $letter ) ? strtoupper( $letter ) : '',
			'page'     => isset( $_GET[ ID::QV_PAGE ] ) ? max( 1, absint( $_GET[ ID::QV_PAGE ] ) ) : 1,
		);
		// phpcs:enable
		return $out;
	}

	/** Current URL without directory parameters (form action / reset link). */
	public static function baseUrl(): string {
		$url = remove_query_arg( array( ID::QV_SEARCH, ID::QV_CATEGORY, ID::QV_LEVEL, ID::QV_LETTER, ID::QV_PAGE ) );
		// Drop /page/N/ so a new search always starts on page 1.
		return (string) preg_replace( '#/page/\d+/?#', '/', (string) $url );
	}

	/**
	 * URL with a changed set of directory parameters.
	 *
	 * @param array<string, string|int> $params Params to set ('' removes).
	 */
	public static function url( array $params ): string {
		$current = array_filter(
			array(
				ID::QV_SEARCH   => self::request()['search'],
				ID::QV_CATEGORY => self::request()['category'],
				ID::QV_LEVEL    => self::request()['level'],
				ID::QV_LETTER   => self::request()['letter'],
			)
		);
		$merged  = array_filter( array_merge( $current, $params ), static fn( $v ): bool => '' !== (string) $v && 0 !== $v );
		return add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $merged ) ), self::baseUrl() );
	}

	/**
	 * Hierarchical category options with counts.
	 *
	 * @return list<array{slug: string, name: string, depth: int, count: int}>
	 */
	public static function categoryOptions(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => ID::TAX_CATEGORY,
				'hide_empty' => true,
				'orderby'    => 'name',
			)
		);
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$by_parent = array();
		foreach ( $terms as $term ) {
			$by_parent[ (int) $term->parent ][] = $term;
		}
		$out  = array();
		$walk = static function ( int $parent_id, int $depth ) use ( &$walk, &$out, $by_parent ): void {
			foreach ( $by_parent[ $parent_id ] ?? array() as $term ) {
				$out[] = array(
					'slug'  => $term->slug,
					'name'  => $term->name,
					'depth' => $depth,
					'count' => (int) $term->count,
				);
				$walk( (int) $term->term_id, $depth + 1 );
			}
		};
		$walk( 0, 0 );
		// Orphans whose parent is empty (hidden) still need to be selectable.
		if ( count( $out ) < count( $terms ) ) {
			$seen = array_column( $out, 'slug' );
			foreach ( $terms as $term ) {
				if ( ! in_array( $term->slug, $seen, true ) ) {
					$out[] = array(
						'slug'  => $term->slug,
						'name'  => $term->name,
						'depth' => 0,
						'count' => (int) $term->count,
					);
				}
			}
		}
		return $out;
	}

	/**
	 * Membership levels in tier order.
	 *
	 * @return list<\WP_Term>
	 */
	public static function levelOptions(): array {
		$terms = get_terms(
			array(
				'taxonomy'   => ID::TAX_LEVEL,
				'hide_empty' => true,
				// EXISTS OR NOT EXISTS: levels without an order still appear (sorted last).
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- tiny taxonomy.
					'relation'   => 'OR',
					'favr_order' => array(
						'key'     => ID::TERM_META_ORDER,
						'compare' => 'EXISTS',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => ID::TERM_META_ORDER,
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'    => 'favr_order',

			)
		);
		return is_array( $terms ) ? array_values( $terms ) : array();
	}

	/**
	 * Pagination links.
	 *
	 * @param \WP_Query $query Query.
	 * @param int       $page  Current page.
	 */
	private static function pagination( \WP_Query $query, int $page ): string {
		if ( $query->max_num_pages < 2 ) {
			return '';
		}
		$links = paginate_links(
			array(
				// Raw URL: paginate_links() parses the base and escapes each link itself.
				'base'      => str_replace( '999999999', '%#%', self::url( array( ID::QV_PAGE => 999999999 ) ) ),
				'format'    => '',
				'current'   => $page,
				'total'     => (int) $query->max_num_pages,
				'prev_text' => '<span aria-hidden="true">&larr;</span> ' . __( 'Previous', 'favr-directory' ),
				'next_text' => __( 'Next', 'favr-directory' ) . ' <span aria-hidden="true">&rarr;</span>',
				'type'      => 'list',
				'end_size'  => 1,
				'mid_size'  => 1,
			)
		);
		return is_string( $links ) ? $links : '';
	}
}
