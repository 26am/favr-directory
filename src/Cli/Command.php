<?php
/**
 * WP-CLI commands.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Cli;

use FavrDirectory\ImportExport\Exporter;
use FavrDirectory\ImportExport\Importer;
use FavrDirectory\Model\Ranking;
use FavrDirectory\Schema\Identifiers as ID;

/**
 * Manage the Favr business directory.
 */
final class Command {

	/**
	 * Import businesses from a CSV file.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * [--dry-run]
	 * : Validate without saving.
	 *
	 * [--download-images]
	 * : Sideload the cover_image column.
	 *
	 * ## EXAMPLES
	 *
	 *     wp favr-directory import members.csv --dry-run
	 *
	 * @param list<string>          $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function import( array $args, array $assoc_args ): void {
		$file = $args[0] ?? '';
		if ( ! is_readable( $file ) ) {
			\WP_CLI::error( "Cannot read {$file}" );
		}
		wp_defer_term_counting( true );
		$report = ( new Importer( isset( $assoc_args['dry-run'] ), isset( $assoc_args['download-images'] ) ) )->importFile( $file );
		wp_defer_term_counting( false );
		foreach ( $report['errors'] as $error ) {
			\WP_CLI::warning( $error );
		}
		\WP_CLI::success( sprintf( '%sCreated %d, updated %d, skipped %d.', isset( $assoc_args['dry-run'] ) ? '[dry run] ' : '', $report['created'], $report['updated'], $report['skipped'] ) );
	}

	/**
	 * Export businesses to CSV.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : Output path. Defaults to STDOUT.
	 *
	 * [--public-only]
	 * : Leave out staff-only fields.
	 *
	 * @param list<string>          $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function export( array $args, array $assoc_args ): void {
		$path   = $assoc_args['file'] ?? 'php://stdout';
		$handle = fopen( $path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			\WP_CLI::error( "Cannot write {$path}" );
		}
		$count = ( new Exporter() )->write( $handle, ! isset( $assoc_args['public-only'] ) );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		if ( 'php://stdout' !== $path ) {
			\WP_CLI::success( "Exported {$count} businesses to {$path}." );
		}
	}

	/**
	 * Recompute the directory sort order for every business.
	 */
	public function rerank(): void {
		Ranking::refreshAll();
		\WP_CLI::success( 'Directory ranking rebuilt.' );
	}

	/**
	 * Create sample businesses for demos and development.
	 *
	 * ## OPTIONS
	 *
	 * [--images]
	 * : Download placeholder photos (needs internet access).
	 *
	 * @param list<string>          $args       Positional.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function seed( array $args, array $assoc_args ): void {
		$file = FAVR_DIRECTORY_PATH . 'data/sample-businesses.csv';
		if ( ! is_readable( $file ) ) {
			\WP_CLI::error( 'Sample data file is missing.' );
		}
		$report = ( new Importer() )->importFile( $file );
		\WP_CLI::log( sprintf( 'Created %d, updated %d.', $report['created'], $report['updated'] ) );

		if ( isset( $assoc_args['images'] ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$posts = get_posts(
				array(
					'post_type'      => ID::POST_TYPE,
					'posts_per_page' => -1,
				)
			);
			foreach ( $posts as $index => $post ) {
				if ( ! has_post_thumbnail( $post ) ) {
					$cover = media_sideload_image( 'https://picsum.photos/seed/' . $post->post_name . '/1600/700.jpg', $post->ID, $post->post_title, 'id' );
					if ( ! is_wp_error( $cover ) ) {
						set_post_thumbnail( $post, (int) $cover );
					}
				}
				$gallery = get_post_meta( $post->ID, ID::meta( 'gallery' ), true );
				if ( 0 === $index % 2 && ( ! is_array( $gallery ) || array() === $gallery ) ) {
					$ids = array();
					for ( $i = 1; $i <= 5; $i++ ) {
						$photo = media_sideload_image( 'https://picsum.photos/seed/' . $post->post_name . '-' . $i . '/1200/900.jpg', $post->ID, $post->post_title, 'id' );
						if ( ! is_wp_error( $photo ) ) {
							$ids[] = (int) $photo;
						}
					}
					update_post_meta( $post->ID, ID::meta( 'gallery' ), $ids );
				}
				\WP_CLI::log( 'Images: ' . $post->post_title );
			}
		}
		\WP_CLI::success( 'Sample directory ready.' );
	}
}
