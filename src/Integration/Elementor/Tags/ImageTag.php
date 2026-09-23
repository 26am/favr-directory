<?php
/**
 * Elementor Pro dynamic tag: business logo or cover.
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
 * "Favr Directory → Business Image" for Image widgets and backgrounds.
 */
final class ImageTag extends Data_Tag {

	/** Name. */
	public function get_name(): string {
		return 'favr-business-image';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Business Image', 'favr-directory' );
	}

	/** Group. */
	public function get_group(): string {
		return 'favr-directory';
	}

	/** Categories. */
	public function get_categories(): array {
		return array( Module::IMAGE_CATEGORY );
	}

	/** Controls. */
	protected function register_controls(): void {
		$this->add_control(
			'key',
			array(
				'label'   => __( 'Image', 'favr-directory' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'logo',
				'options' => FieldValues::imageOptions(),
			)
		);
	}

	/**
	 * Value.
	 *
	 * @param array<string, mixed> $options Options.
	 * @return array{id: int|string, url: string}
	 */
	public function get_value( array $options = array() ): array {
		$business = FieldValues::business();
		$id       = $business ? FieldValues::imageId( $business, (string) $this->get_settings( 'key' ) ) : 0;
		return array(
			'id'  => $id ?: '',
			'url' => $id ? (string) wp_get_attachment_image_url( $id, 'full' ) : '',
		);
	}
}
