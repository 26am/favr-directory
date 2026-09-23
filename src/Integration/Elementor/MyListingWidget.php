<?php
/**
 * Elementor: My Listing (edit form).
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration\Elementor;

use FavrDirectory\Editing\FrontEditor;
use FavrDirectory\Vendor\FavrCore\Integrations\Elementor\Widget;

/**
 * The representative's listing editor (same as the My Listing block and [favr_my_listing]).
 */
final class MyListingWidget extends Widget {

	/** Name. */
	public function get_name(): string {
		return 'favr-my-listing';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'My Listing (edit form)', 'favr-directory' );
	}

	/** Icon. */
	public function get_icon(): string {
		return 'eicon-edit';
	}

	/** Keywords. */
	public function get_keywords(): array {
		return array( 'listing', 'edit', 'member', 'business', 'favr' );
	}

	/** Styles. */
	public function get_style_depends(): array {
		return array( 'favr-directory' );
	}

	/** Settings. */
	protected function settings(): array {
		return array();
	}

	/**
	 * Render.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	protected function output( array $settings ): string {
		return FrontEditor::render();
	}
}
