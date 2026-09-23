<?php
/**
 * Denormalized directory sort key.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Model;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * "Featured first, then higher membership tiers, then A–Z" cannot be expressed in one
 * WP_Query (the tier order lives in term meta), so each business carries a numeric rank:
 *   rank = (featured ? 0 : 1000) + level display order (999 when no level).
 * It is recomputed whenever any input changes, from every write path.
 */
final class Ranking {

	public const META_KEY = '_favr_rank';

	/** Hook recomputation triggers. */
	public function hook(): void {
		add_action( 'save_post_' . ID::POST_TYPE, array( $this, 'onSave' ), 20 );
		add_action( 'set_object_terms', array( $this, 'onTerms' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'onMeta' ), 10, 3 );
		add_action( 'added_post_meta', array( $this, 'onMeta' ), 10, 3 );
		add_action( 'deleted_post_meta', array( $this, 'onMeta' ), 10, 3 );
		add_action( 'updated_term_meta', array( $this, 'onTermMeta' ), 10, 3 );
		add_action( 'added_term_meta', array( $this, 'onTermMeta' ), 10, 3 );
		add_action( 'transition_post_status', array( $this, 'flushLetters' ), 10, 3 );
		add_action( 'created_' . ID::TAX_LEVEL, array( $this, 'defaultLevelOrder' ), 20 );
	}

	/**
	 * Give levels created outside the level form (CSV import, REST) a display order.
	 *
	 * @param int $term_id Term id.
	 */
	public function defaultLevelOrder( int $term_id ): void {
		if ( ! metadata_exists( 'term', $term_id, ID::TERM_META_ORDER ) ) {
			update_term_meta( $term_id, ID::TERM_META_ORDER, 10 );
		}
	}

	/**
	 * Pure rank formula.
	 *
	 * @param bool     $featured    Featured flag.
	 * @param int|null $level_order Level display order, null when no level.
	 */
	public static function rank( bool $featured, ?int $level_order ): int {
		$order = null === $level_order ? 999 : max( 0, min( 998, $level_order ) );
		return ( $featured ? 0 : 1000 ) + $order;
	}

	/**
	 * Recompute and store one business's rank.
	 *
	 * @param int $post_id Post id.
	 */
	public static function refresh( int $post_id ): void {
		if ( ID::POST_TYPE !== get_post_type( $post_id ) ) {
			return;
		}
		$featured = '1' === get_post_meta( $post_id, ID::meta( 'featured' ), true );
		$levels   = wp_get_object_terms( $post_id, ID::TAX_LEVEL, array( 'fields' => 'ids' ) );
		$order    = null;
		if ( is_array( $levels ) && isset( $levels[0] ) ) {
			$raw = get_term_meta( (int) $levels[0], ID::TERM_META_ORDER, true );
			// A level nobody ordered yet sorts after ordered tiers, never above Platinum.
			$order = is_numeric( $raw ) ? (int) $raw : 998;
		}
		update_post_meta( $post_id, self::META_KEY, self::rank( $featured, $order ) );
	}

	/** Recompute every business (activation / upgrade / level reorder). */
	public static function refreshAll(): void {
		$ids = get_posts(
			array(
				'post_type'      => ID::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			self::refresh( (int) $id );
		}
	}

	/**
	 * After save.
	 *
	 * @param int $post_id Post id.
	 */
	public function onSave( int $post_id ): void {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		self::refresh( $post_id );
		$this->flushLetters();
	}

	/**
	 * Level assignment changed.
	 *
	 * @param int    $object_id Object id.
	 * @param array  $terms     Terms.
	 * @param array  $tt_ids    Term taxonomy ids.
	 * @param string $taxonomy  Taxonomy.
	 */
	public function onTerms( int $object_id, $terms, $tt_ids, string $taxonomy ): void {
		if ( ID::TAX_LEVEL === $taxonomy ) {
			self::refresh( $object_id );
		}
	}

	/**
	 * Featured flag changed through any path (list-table toggle, REST, import).
	 *
	 * @param int|array $meta_ids  Meta id(s).
	 * @param int       $object_id Post id.
	 * @param string    $meta_key  Meta key.
	 */
	public function onMeta( $meta_ids, int $object_id, string $meta_key ): void {
		if ( ID::meta( 'featured' ) === $meta_key ) {
			self::refresh( $object_id );
		}
	}

	/**
	 * A level's display order changed: re-rank its businesses.
	 *
	 * @param int    $meta_id  Meta id.
	 * @param int    $term_id  Term id.
	 * @param string $meta_key Meta key.
	 */
	public function onTermMeta( int $meta_id, int $term_id, string $meta_key ): void {
		if ( ID::TERM_META_ORDER !== $meta_key ) {
			return;
		}
		$ids = get_objects_in_term( $term_id, ID::TAX_LEVEL );
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				self::refresh( (int) $id );
			}
		}
	}

	/** Invalidate the cached A–Z letters. */
	public function flushLetters(): void {
		wp_cache_delete( 'letters', 'favr_directory' );
	}
}
