<?php
/**
 * CSV import.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\ImportExport;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Fields\Sanitizer;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\CsvFormat;

/**
 * Creates or updates businesses from CSV rows. Matching order: `id` column (when it is a
 * business), then `slug`, then exact name. Only columns PRESENT in the file are written, so a
 * sheet with just "name,phone" updates phones without wiping anything else. Every value goes
 * through the same Sanitizer as the edit screen.
 */
final class Importer {

	/** @var array{created: int, updated: int, skipped: int, errors: list<string>} */
	private array $report = array(
		'created' => 0,
		'updated' => 0,
		'skipped' => 0,
		'errors'  => array(),
	);

	/**
	 * Constructor.
	 *
	 * @param bool $dry_run         Validate only; write nothing.
	 * @param bool $download_images Sideload cover_image URLs into the media library.
	 */
	public function __construct(
		private bool $dry_run = false,
		private bool $download_images = false
	) {}

	/**
	 * Import a CSV file.
	 *
	 * @param string $path File path.
	 * @return array{created: int, updated: int, skipped: int, errors: list<string>}
	 */
	public function importFile( string $path ): array {
		$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming read of an uploaded file.
		if ( false === $handle ) {
			$this->report['errors'][] = __( 'Could not read the file.', 'favr-directory' );
			return $this->report;
		}

		$header = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->report['errors'][] = __( 'The file is empty.', 'favr-directory' );
			return $this->report;
		}
		// Strip a UTF-8 BOM (Excel) and normalize header names.
		$header = array_map(
			static fn( $h ): string => sanitize_key( str_replace( ' ', '_', (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $h ) ) ),
			$header
		);

		if ( ! in_array( 'name', $header, true ) && ! in_array( 'id', $header, true ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->report['errors'][] = __( 'The file needs a "name" (or "id") column.', 'favr-directory' );
			return $this->report;
		}

		$line = 1;
		while ( ( $cells = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) { // phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			++$line;
			if ( array( null ) === $cells ) {
				continue; // Blank line.
			}
			$row = array();
			foreach ( $header as $i => $key ) {
				$row[ $key ] = CsvFormat::unsafeCell( (string) ( $cells[ $i ] ?? '' ) );
			}
			$this->importRow( $row, $line );
		}
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		return $this->report;
	}

	/**
	 * Import one associative row.
	 *
	 * @param array<string, string> $row  Column => cell.
	 * @param int                   $line Line number for messages.
	 */
	public function importRow( array $row, int $line = 0 ): void {
		$existing = $this->match( $row );
		$name     = trim( $row['name'] ?? '' );

		if ( ! $existing && '' === $name ) {
			++$this->report['skipped'];
			/* translators: %d: line number. */
			$this->report['errors'][] = sprintf( __( 'Line %d: skipped — no business name.', 'favr-directory' ), $line );
			return;
		}

		$postarr = array( 'post_type' => ID::POST_TYPE );
		if ( $existing ) {
			$postarr['ID'] = $existing;
		}
		if ( '' !== $name ) {
			$postarr['post_title'] = sanitize_text_field( $name );
		}
		if ( array_key_exists( 'description', $row ) ) {
			// wp_insert_post applies kses for users without unfiltered_html; admins keep their embeds.
			$postarr['post_content'] = current_user_can( 'unfiltered_html' ) || ( defined( 'WP_CLI' ) && WP_CLI ) ? $row['description'] : wp_kses_post( $row['description'] );
		}
		if ( isset( $row['slug'] ) && '' !== trim( $row['slug'] ) ) {
			$postarr['post_name'] = sanitize_title( $row['slug'] );
		}
		$status = sanitize_key( $row['status'] ?? '' );
		if ( in_array( $status, array( 'publish', 'draft', 'pending', 'private' ), true ) ) {
			$postarr['post_status'] = $status;
		} elseif ( ! $existing ) {
			$postarr['post_status'] = 'publish';
		}

		if ( isset( $row['hours'] ) ) {
			foreach ( CsvFormat::invalidHourSpecs( $row['hours'] ) as $spec ) {
				$this->report['errors'][] = sprintf(
					/* translators: 1: line number, 2: the hours text that could not be read. */
					__( 'Line %1$d: could not read hours "%2$s" (use e.g. mon=09:00-17:00, mon=9am-5pm, mon=closed or mon=24h).', 'favr-directory' ),
					$line,
					$spec
				);
			}
		}

		if ( $this->dry_run ) {
			++$this->report[ $existing ? 'updated' : 'created' ];
			return;
		}

		$post_id = $existing ? wp_update_post( wp_slash( $postarr ), true ) : wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			++$this->report['skipped'];
			/* translators: 1: line number, 2: error message. */
			$this->report['errors'][] = sprintf( __( 'Line %1$d: %2$s', 'favr-directory' ), $line, $post_id->get_error_message() );
			return;
		}
		$post_id = (int) $post_id;

		foreach ( FieldRegistry::all() as $id => $field ) {
			if ( ! array_key_exists( (string) $id, $row ) ) {
				continue;
			}
			$value = Sanitizer::sanitize( $field, CsvFormat::decode( $field, $row[ $id ] ) );
			if ( Sanitizer::isEmpty( $field, $value ) ) {
				delete_post_meta( $post_id, ID::meta( (string) $id ) );
			} else {
				update_post_meta( $post_id, ID::meta( (string) $id ), $value );
			}
		}

		if ( array_key_exists( 'categories', $row ) ) {
			wp_set_object_terms( $post_id, $this->categoryIds( $row['categories'] ), ID::TAX_CATEGORY );
		}
		if ( array_key_exists( 'level', $row ) ) {
			$level = $this->levelId( $row['level'] );
			wp_set_object_terms( $post_id, $level ? array( $level ) : array(), ID::TAX_LEVEL );
		}
		if ( $this->download_images && ! empty( $row['cover_image'] ) && ! has_post_thumbnail( $post_id ) ) {
			$this->sideloadCover( $post_id, $row['cover_image'] );
		}

		++$this->report[ $existing ? 'updated' : 'created' ];
	}

	/**
	 * Result counters.
	 *
	 * @return array{created: int, updated: int, skipped: int, errors: list<string>}
	 */
	public function report(): array {
		return $this->report;
	}

	/**
	 * Find an existing business for a row.
	 *
	 * @param array<string, string> $row Row.
	 */
	private function match( array $row ): int {
		$id = absint( $row['id'] ?? 0 );
		if ( $id && ID::POST_TYPE === get_post_type( $id ) ) {
			// Trust the id only when slug/name (if given) agree — protects against files from
			// another site and duplicated template rows overwriting the wrong business.
			$post  = get_post( $id );
			$slug  = sanitize_title( $row['slug'] ?? '' );
			$name  = trim( $row['name'] ?? '' );
			$agree = ( '' === $slug || $slug === $post->post_name ) && ( '' === $name || 0 === strcasecmp( $name, html_entity_decode( $post->post_title, ENT_QUOTES, 'UTF-8' ) ) );
			if ( $agree || ( '' === $slug && '' === $name ) ) {
				return $id;
			}
			if ( '' !== $slug && $slug === $post->post_name ) {
				return $id; // Renamed via the sheet: slug still identifies it.
			}
		}
		$slug = sanitize_title( $row['slug'] ?? '' );
		if ( '' !== $slug ) {
			$found = get_posts(
				array(
					'post_type'   => ID::POST_TYPE,
					'name'        => $slug,
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			if ( $found ) {
				return (int) $found[0];
			}
		}
		$name = trim( $row['name'] ?? '' );
		if ( '' !== $name ) {
			$found = get_posts(
				array(
					'post_type'   => ID::POST_TYPE,
					'title'       => $name,
					'post_status' => 'any',
					'numberposts' => 1,
					'fields'      => 'ids',
				)
			);
			if ( $found ) {
				return (int) $found[0];
			}
		}
		return 0;
	}

	/**
	 * Resolve "Food > Bakeries|Retail" into term ids, creating missing terms.
	 *
	 * @param string $cell Cell.
	 * @return list<int>
	 */
	private function categoryIds( string $cell ): array {
		$ids = array();
		foreach ( CsvFormat::splitList( $cell ) as $path ) {
			$parent = 0;
			foreach ( array_map( 'trim', explode( '>', $path ) ) as $name ) {
				if ( '' === $name ) {
					continue;
				}
				$term = term_exists( $name, ID::TAX_CATEGORY, $parent );
				if ( ! $term ) {
					$term = wp_insert_term( $name, ID::TAX_CATEGORY, array( 'parent' => $parent ) );
				}
				if ( is_wp_error( $term ) ) {
					continue 2;
				}
				$parent = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
			if ( $parent ) {
				$ids[] = $parent;
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Level term id by name/slug, creating it when new.
	 *
	 * @param string $cell Cell.
	 */
	private function levelId( string $cell ): int {
		$cell = trim( $cell );
		if ( '' === $cell ) {
			return 0;
		}
		$term = term_exists( $cell, ID::TAX_LEVEL );
		if ( ! $term ) {
			$term = wp_insert_term( $cell, ID::TAX_LEVEL );
		}
		return is_wp_error( $term ) ? 0 : (int) ( is_array( $term ) ? $term['term_id'] : $term );
	}

	/**
	 * Download a cover image.
	 *
	 * @param int    $post_id Post id.
	 * @param string $url     Image URL.
	 */
	private function sideloadCover( int $post_id, string $url ): void {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$attachment = media_sideload_image( esc_url_raw( $url ), $post_id, null, 'id' );
		if ( is_wp_error( $attachment ) ) {
			$this->report['errors'][] = sprintf(
				/* translators: 1: image URL, 2: error. */
				__( 'Could not download %1$s: %2$s', 'favr-directory' ),
				$url,
				$attachment->get_error_message()
			);
			return;
		}
		set_post_thumbnail( $post_id, (int) $attachment );
	}
}
