<?php
/**
 * Elementor: Business Profile.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration\Elementor;

use FavrDirectory\Frontend\Shortcodes;
use FavrDirectory\Vendor\FavrCore\Integrations\Elementor\Widget;

/**
 * A whole business profile: the current business (in a Theme Builder single template) or one
 * chosen by ID.
 */
final class ProfileWidget extends Widget {

	/** Name. */
	public function get_name(): string {
		return 'favr-business-profile';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Business Profile', 'favr-directory' );
	}

	/** Icon. */
	public function get_icon(): string {
		return 'eicon-single-page';
	}

	/** Keywords. */
	public function get_keywords(): array {
		return array( 'business', 'profile', 'listing', 'favr' );
	}

	/** Styles. */
	public function get_style_depends(): array {
		return array( 'favr-directory' );
	}

	/** Scripts. */
	public function get_script_depends(): array {
		return array( 'favr-directory' );
	}

	/** Settings. */
	protected function settings(): array {
		return array(
			'business_id' => array(
				'label'       => __( 'Business ID', 'favr-directory' ),
				'type'        => 'number',
				'default'     => 0,
				'min'         => 0,
				'description' => __( '0 shows the current business (use this in a single-business template).', 'favr-directory' ),
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	protected function output( array $settings ): string {
		$html = ( new Shortcodes() )->profile( array( 'id' => (int) $settings['business_id'] ) );
		if ( '' === $html && $this->isEditing() ) {
			return '<p class="favr-builder-note">' . esc_html__( 'Business Profile: shows the current business on business pages, or set a Business ID.', 'favr-directory' ) . '</p>';
		}
		return $html;
	}

	/** In the Elementor editor or preview. */
	private function isEditing(): bool {
		return class_exists( '\Elementor\Plugin' ) && ( \Elementor\Plugin::$instance->editor->is_edit_mode() || \Elementor\Plugin::$instance->preview->is_preview_mode() );
	}
}
