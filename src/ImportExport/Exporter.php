<?php
/**
 * CSV export.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\ImportExport;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Vendor\FavrCore\Support\CsvFormat;

/**
 * Streams every business as CSV. The column set is exactly what the Importer reads, so an
 * export is also the import template.
 */
final class Exporter {

	/**
	 * Header row.
	 *
	 * @param bool $include_private Include staff-only fields.
	 * @return list<string>
	 */
	public static function columns( bool $include_private = true ): array {
		$columns = array( 'id', 'name', 'slug', 'status', 'description', 'categories', 'level', 'cover_image' );
		foreach ( FieldRegistry::all() as $id => $field ) {
			if ( $field['private'] && ! $include_private ) {
				continue;
			}
			$columns[] = (string) $id;
		}
		return $columns;
	}

	/**
	 * Write CSV to a stream.
	 *
	 * @param resource $handle          Output stream.
	 * @param bool     $include_private Include staff-only fields.
	 */
	public function write( $handle, bool $include_private = true ): int {
		$columns = self::columns( $include_private );
		fputcsv( $handle, $columns, ',', '"', '' );

		$count = 0;
		$page  = 1;
		do {
			$query = new \WP_Query(
				array(
					'post_type'      => ID::POST_TYPE,
					'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
					'posts_per_page' => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page -- batched export.
					'paged'          => $page,
					'orderby'        => 'title',
					'order'          => 'ASC',
					'no_found_rows'  => false,
				)
			);
			foreach ( $query->posts as $post ) {
				fputcsv( $handle, $this->row( new Business( $post ), $columns ), ',', '"', '' );
				++$count;
			}
			++$page;
		} while ( $page <= (int) $query->max_num_pages );

		return $count;
	}

	/**
	 * One row.
	 *
	 * @param Business     $business Business.
	 * @param list<string> $columns  Columns.
	 * @return list<string>
	 */
	private function row( Business $business, array $columns ): array {
		$post  = $business->post();
		$level = $business->level();
		$cells = array(
			'id'          => (string) $post->ID,
			'name'        => $post->post_title,
			'slug'        => $post->post_name,
			'status'      => $post->post_status,
			'description' => $post->post_content,
			'categories'  => implode( '|', array_map( array( $this, 'termPath' ), $business->categories() ) ),
			'level'       => $level ? html_entity_decode( $level->name, ENT_QUOTES, 'UTF-8' ) : '',
			'cover_image' => $business->coverId() ? (string) wp_get_attachment_url( $business->coverId() ) : '',
		);

		$row = array();
		foreach ( $columns as $column ) {
			if ( array_key_exists( $column, $cells ) ) {
				$row[] = CsvFormat::safeCell( (string) $cells[ $column ] );
				continue;
			}
			$field = FieldRegistry::get( $column );
			$row[] = $field ? CsvFormat::safeCell( CsvFormat::encode( $field, $business->field( $column ) ) ) : '';
		}
		return $row;
	}

	/**
	 * "Parent > Child" path for a category.
	 *
	 * @param \WP_Term $term Term.
	 */
	private function termPath( \WP_Term $term ): string {
		$names = array( html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) );
		$guard = 0;
		while ( $term->parent && $guard++ < 10 ) {
			$term = get_term( $term->parent, ID::TAX_CATEGORY );
			if ( ! $term instanceof \WP_Term ) {
				break;
			}
			array_unshift( $names, html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) );
		}
		return implode( ' > ', $names );
	}
}
