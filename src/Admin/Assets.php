<?php
/**
 * Admin scripts and styles.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Admin;

use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\AssetVersion;

/**
 * Loads the (build-free) admin assets only on directory screens.
 */
final class Assets {

	/** Hook. */
	public function hook(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue for directory screens.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue( string $hook_suffix ): void {
		$screen = get_current_screen();
		if ( ! $screen || ID::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style( 'favr-core-fields', FAVR_DIRECTORY_URL . 'assets/core/fields.css', array(), AssetVersion::of( 'assets/core/fields.css' ) );
		wp_enqueue_style( 'favr-directory-admin', FAVR_DIRECTORY_URL . 'assets/admin/admin.css', array( 'favr-core-fields' ), AssetVersion::of( 'assets/admin/admin.css' ) );

		$deps = array( 'jquery' );
		if ( 'post' === $screen->base ) {
			wp_enqueue_media();
			$deps[] = 'jquery-ui-sortable';
		}
		if ( in_array( $screen->base, array( 'edit-tags', 'term', ID::POST_TYPE . '_page_' . SettingsPage::SLUG ), true ) || str_contains( $hook_suffix, SettingsPage::SLUG ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			$deps[] = 'wp-color-picker';
		}

		// Handle shared with other Favr plugins: whichever registers first wins, and all copies are
		// the same favr-core release family.
		wp_enqueue_script( 'favr-core-fields', FAVR_DIRECTORY_URL . 'assets/core/fields.js', $deps, AssetVersion::of( 'assets/core/fields.js' ), true );
		wp_localize_script(
			'favr-core-fields',
			'favrCoreFields',
			array(
				'i18n' => array(
					'chooseLogo'   => __( 'Choose an image', 'favr-directory' ),
					'useImage'     => __( 'Use this image', 'favr-directory' ),
					'addPhotos'    => __( 'Add photos to the gallery', 'favr-directory' ),
					'addToGallery' => __( 'Add to gallery', 'favr-directory' ),
					'confirmClear' => __( 'Remove all opening hours for this business?', 'favr-directory' ),
					/* translators: %d: number of characters. */
					'charsLeft'    => __( '%d characters left', 'favr-directory' ),
				),
			)
		);
		wp_enqueue_script( 'favr-directory-admin', FAVR_DIRECTORY_URL . 'assets/admin/admin.js', array( 'jquery', 'favr-core-fields' ), AssetVersion::of( 'assets/admin/admin.js' ), true );
		wp_localize_script(
			'favr-directory-admin',
			'favrDirectory',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( ID::NONCE_AJAX ),
				'weights' => 'post' === $screen->base ? EditScreen::weights() : array(),
				'i18n'    => array(
					'great'     => __( 'Looking great!', 'favr-directory' ),
					'almost'    => __( 'Almost there', 'favr-directory' ),
					'needsMore' => __( 'Needs more details', 'favr-directory' ),
				),
			)
		);
	}
}
