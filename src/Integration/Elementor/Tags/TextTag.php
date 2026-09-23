<?php
/**
 * Elementor Pro dynamic tag: a business field as text.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Tag;
use Elementor\Modules\DynamicTags\Module;
use FavrDirectory\Integration\FieldValues;

/**
 * "Favr Directory → Business Field" for headings, text and buttons in Theme Builder templates.
 */
final class TextTag extends Tag {

	/** Name. */
	public function get_name(): string {
		return 'favr-business-text';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Business Field', 'favr-directory' );
	}

	/** Group. */
	public function get_group(): string {
		return 'favr-directory';
	}

	/** Categories. */
	public function get_categories(): array {
		return array( Module::TEXT_CATEGORY );
	}

	/** Controls. */
	protected function register_controls(): void {
		$this->add_control(
			'key',
			array(
				'label'   => __( 'Field', 'favr-directory' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'name',
				'options' => FieldValues::textOptions(),
			)
		);
	}

	/** Render. */
	public function render(): void {
		$business = FieldValues::business();
		if ( $business ) {
			echo nl2br( esc_html( FieldValues::text( $business, (string) $this->get_settings( 'key' ) ) ) );
		}
	}
}
