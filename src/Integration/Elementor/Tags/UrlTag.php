<?php
/**
 * Elementor Pro dynamic tag: a business link.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration\Elementor\Tags;

use Elementor\Controls_Manager;
use Elementor\Core\DynamicTags\Data_Tag;
use Elementor\Modules\DynamicTags\Module;
use FavrDirectory\Integration\FieldValues;

/**
 * "Favr Directory → Business Link": website, booking, call, email, directions, social profiles.
 */
final class UrlTag extends Data_Tag {

	/** Name. */
	public function get_name(): string {
		return 'favr-business-url';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Business Link', 'favr-directory' );
	}

	/** Group. */
	public function get_group(): string {
		return 'favr-directory';
	}

	/** Categories. */
	public function get_categories(): array {
		return array( Module::URL_CATEGORY );
	}

	/** Controls. */
	protected function register_controls(): void {
		$this->add_control(
			'key',
			array(
				'label'   => __( 'Link', 'favr-directory' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'website',
				'options' => FieldValues::urlOptions(),
			)
		);
	}

	/**
	 * Value.
	 *
	 * @param array<string, mixed> $options Options.
	 */
	public function get_value( array $options = array() ): string {
		$business = FieldValues::business();
		return $business ? FieldValues::url( $business, (string) $this->get_settings( 'key' ) ) : '';
	}
}
