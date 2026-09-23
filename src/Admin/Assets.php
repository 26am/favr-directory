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

		wp_enqueue_style( 'favr-directory-admin', FAVR_DIRECTORY_URL . 'assets/admin/admin.css', array(), AssetVersion::of( 'assets/admin/admin.css' ) );

		$deps = array( 'jquery' );
		if ( 'post' === $screen->base ) {
			wp_enqueue_media();
			$deps[] = 'jquery-ui-sortable';
		}
		if ( in_array( $screen->base, array( 'edit-tags', 'term', ID::POST_TYPE . '_page_' . SettingsPage::SLUG ), true ) || str_contains( $hook_suffix, SettingsPage::SLUG ) ) {
			wp_enqueue_style( 'wp-color-picker' );
			$deps[] = 'wp-color-picker';
		}

		wp_enqueue_script( 'favr-directory-admin', FAVR_DIRECTORY_URL . 'assets/admin/admin.js', $deps, AssetVersion::of( 'assets/admin/admin.js' ), true );
		wp_localize_script(
			'favr-directory-admin',
			'favrDirectory',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( ID::NONCE_AJAX ),
				'weights' => 'post' === $screen->base ? EditScreen::weights() : array(),
				'i18n'    => array(
					'chooseLogo'   => __( 'Choose an image', 'favr-directory' ),
					'useImage'     => __( 'Use this image', 'favr-directory' ),
					'addPhotos'    => __( 'Add photos to the gallery', 'favr-directory' ),
					'addToGallery' => __( 'Add to gallery', 'favr-directory' ),
					'confirmClear' => __( 'Remove all opening hours for this business?', 'favr-directory' ),
					'great'        => __( 'Looking great!', 'favr-directory' ),
					'almost'       => __( 'Almost there', 'favr-directory' ),
					'needsMore'    => __( 'Needs more details', 'favr-directory' ),
					'invalidField' => __( 'Please check this field.', 'favr-directory' ),
					/* translators: %d: number of characters. */
					'charsLeft'    => __( '%d characters left', 'favr-directory' ),
				),
			)
		);
	}
}
