<?php
/**
 * Directory → Import / Export.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Admin;

use FavrDirectory\ImportExport\Exporter;
use FavrDirectory\ImportExport\Importer;
use FavrDirectory\Schema\Identifiers as ID;

/**
 * CSV export (streamed download) and import (upload, optional dry run).
 */
final class ImportExportPage {

	public const SLUG = 'favr-directory-import-export';

	/** Hook. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_favr_export', array( $this, 'export' ) );
		add_action( 'admin_post_favr_import', array( $this, 'import' ) );
	}

	/** Submenu entry. */
	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . ID::POST_TYPE,
			__( 'Import & Export', 'favr-directory' ),
			__( 'Import / Export', 'favr-directory' ),
			ID::CAP_SETTINGS,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/** Stream the CSV download. */
	public function export(): void {
		check_admin_referer( 'favr_export' );
		if ( ! current_user_can( ID::CAP_SETTINGS ) ) {
			wp_die( esc_html__( 'You are not allowed to export the directory.', 'favr-directory' ), 403 );
		}
		$private  = ! empty( $_POST['include_private'] );
		$filename = 'directory-' . gmdate( 'Y-m-d' ) . '.csv';

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		$out = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $out ) {
			exit;
		}
		fwrite( $out, "\xEF\xBB\xBF" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- UTF-8 BOM so Excel reads accents.
		( new Exporter() )->write( $out, $private );
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/** Handle an upload. */
	public function import(): void {
		check_admin_referer( 'favr_import' );
		if ( ! current_user_can( ID::CAP_SETTINGS ) ) {
			wp_die( esc_html__( 'You are not allowed to import businesses.', 'favr-directory' ), 403 );
		}

		$file = $_FILES['favr_csv'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated below.
		if ( ! is_array( $file ) || UPLOAD_ERR_OK !== (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) || ! is_uploaded_file( (string) $file['tmp_name'] ) ) {
			$this->redirect( array( 'errors' => array( __( 'Please choose a CSV file to upload.', 'favr-directory' ) ) ) );
		}
		$check = wp_check_filetype( sanitize_file_name( (string) $file['name'] ), array( 'csv' => 'text/csv' ) );
		if ( 'csv' !== $check['ext'] ) {
			$this->redirect( array( 'errors' => array( __( 'The file must be a .csv file.', 'favr-directory' ) ) ) );
		}

		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- large imports.
		}
		wp_defer_term_counting( true );
		$importer = new Importer( ! empty( $_POST['dry_run'] ), ! empty( $_POST['download_images'] ) );
		$report   = $importer->importFile( (string) $file['tmp_name'] );
		wp_defer_term_counting( false );

		$report['dry_run'] = ! empty( $_POST['dry_run'] );
		$this->redirect( $report );
	}

	/**
	 * Store the report and go back to the page.
	 *
	 * @param array<string, mixed> $report Report.
	 * @return never
	 */
	private function redirect( array $report ): void {
		set_transient( 'favr_import_report_' . get_current_user_id(), $report, 300 );
		wp_safe_redirect( admin_url( 'edit.php?post_type=' . ID::POST_TYPE . '&page=' . self::SLUG . '&imported=1' ) );
		exit;
	}

	/** Render. */
	public function render(): void {
		if ( ! current_user_can( ID::CAP_SETTINGS ) ) {
			return;
		}
		$report = get_transient( 'favr_import_report_' . get_current_user_id() );
		if ( is_array( $report ) ) {
			delete_transient( 'favr_import_report_' . get_current_user_id() );
		}
		$count = wp_count_posts( ID::POST_TYPE );
		$total = (int) ( $count->publish ?? 0 ) + (int) ( $count->draft ?? 0 ) + (int) ( $count->pending ?? 0 ) + (int) ( $count->private ?? 0 );
		?>
		<div class="wrap favr-settings">
			<h1><?php esc_html_e( 'Import & Export', 'favr-directory' ); ?></h1>

			<?php if ( is_array( $report ) ) : ?>
				<?php $this->renderReport( $report ); ?>
			<?php endif; ?>

			<div class="favr-settings__layout">
				<div class="favr-settings__form">
					<div class="favr-card">
						<h2><span class="dashicons dashicons-upload"></span> <?php esc_html_e( 'Import businesses from a spreadsheet', 'favr-directory' ); ?></h2>
						<p><?php esc_html_e( 'Upload a CSV file. Existing businesses are matched by id, slug or exact name and updated; everything else is created. Only the columns in your file are changed.', 'favr-directory' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<input type="hidden" name="action" value="favr_import">
							<?php wp_nonce_field( 'favr_import' ); ?>
							<p class="favr-upload"><input type="file" name="favr_csv" accept=".csv,text/csv" required></p>
							<p><label><input type="checkbox" name="dry_run" value="1" checked> <?php esc_html_e( 'Test run first (check the file without saving anything)', 'favr-directory' ); ?></label></p>
							<p><label><input type="checkbox" name="download_images" value="1"> <?php esc_html_e( 'Download cover images from the cover_image column', 'favr-directory' ); ?></label></p>
							<?php submit_button( __( 'Import CSV', 'favr-directory' ), 'primary', 'submit', false ); ?>
						</form>
					</div>

					<div class="favr-card">
						<h2><span class="dashicons dashicons-download"></span> <?php esc_html_e( 'Export the directory', 'favr-directory' ); ?></h2>
						<p>
							<?php
							/* translators: %d: number of businesses. */
							echo esc_html( sprintf( _n( 'Download all %d business as a CSV file you can open in Excel or Google Sheets.', 'Download all %d businesses as a CSV file you can open in Excel or Google Sheets.', $total, 'favr-directory' ), $total ) );
							?>
						</p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="favr_export">
							<?php wp_nonce_field( 'favr_export' ); ?>
							<p><label><input type="checkbox" name="include_private" value="1" checked> <?php esc_html_e( 'Include staff-only fields (member ID, renewal date, notes)', 'favr-directory' ); ?></label></p>
							<?php submit_button( __( 'Download CSV', 'favr-directory' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
				</div>

				<aside class="favr-settings__aside">
					<div class="favr-card">
						<h2><?php esc_html_e( 'Spreadsheet format', 'favr-directory' ); ?></h2>
						<p><?php esc_html_e( 'Tip: export first and use the file as your template.', 'favr-directory' ); ?></p>
						<ul class="favr-help">
							<li><code>name</code> — <?php esc_html_e( 'required for new businesses', 'favr-directory' ); ?></li>
							<li><code>categories</code> — <code>Food &gt; Bakeries|Retail</code></li>
							<li><code>level</code> — <code>Gold</code></li>
							<li><code>hours</code> — <code>mon=09:00-17:00;sat=closed;sun=24h</code></li>
							<li><code>highlights</code> — <code>woman_owned|free_parking</code></li>
							<li><code>links</code> — <code>Menu=https://…;Donate=https://…</code></li>
							<li><code>featured</code>, <code>show_email</code> — <code>yes</code> / <code>no</code></li>
						</ul>
					</div>
				</aside>
			</div>
		</div>
		<?php
	}

	/**
	 * Import result notice.
	 *
	 * @param array<string, mixed> $report Report.
	 */
	private function renderReport( array $report ): void {
		$errors  = (array) ( $report['errors'] ?? array() );
		$created = (int) ( $report['created'] ?? 0 );
		$updated = (int) ( $report['updated'] ?? 0 );
		$skipped = (int) ( $report['skipped'] ?? 0 );
		$class   = ( $created + $updated ) > 0 ? 'notice-success' : 'notice-warning';

		echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>';
		if ( ! empty( $report['dry_run'] ) ) {
			esc_html_e( 'Test run complete — nothing was saved.', 'favr-directory' );
			echo '</strong> ';
			/* translators: 1: would-create count, 2: would-update count, 3: skipped count. */
			echo esc_html( sprintf( __( 'Would create %1$d, update %2$d, skip %3$d. Untick “Test run” and import again to save.', 'favr-directory' ), $created, $updated, $skipped ) );
		} elseif ( isset( $report['created'] ) ) {
			esc_html_e( 'Import complete.', 'favr-directory' );
			echo '</strong> ';
			/* translators: 1: created count, 2: updated count, 3: skipped count. */
			echo esc_html( sprintf( __( 'Created %1$d, updated %2$d, skipped %3$d.', 'favr-directory' ), $created, $updated, $skipped ) );
		} else {
			esc_html_e( 'Import failed.', 'favr-directory' );
			echo '</strong>';
		}
		echo '</p>';
		if ( $errors ) {
			echo '<ul class="favr-errors">';
			foreach ( array_slice( $errors, 0, 50 ) as $error ) {
				echo '<li>' . esc_html( (string) $error ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}
}
