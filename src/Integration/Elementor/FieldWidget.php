<?php
/**
 * Elementor: Business Field.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration\Elementor;

use FavrDirectory\Frontend\Shortcodes;
use FavrDirectory\Vendor\FavrCore\Integrations\Elementor\Widget;

/**
 * One piece of a business (phone, hours table, map, social icons, logo…) for building custom
 * single-business layouts in Elementor, with or without Elementor Pro.
 */
final class FieldWidget extends Widget {

	/** Name. */
	public function get_name(): string {
		return 'favr-business-field';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Business Field', 'favr-directory' );
	}

	/** Icon. */
	public function get_icon(): string {
		return 'eicon-database';
	}

	/** Keywords. */
	public function get_keywords(): array {
		return array( 'business', 'field', 'hours', 'map', 'phone', 'favr' );
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
		$fields = array(
			'hours'  => __( 'Opening hours (table)', 'favr-directory' ),
			'map'    => __( 'Map', 'favr-directory' ),
			'social' => __( 'Social icons', 'favr-directory' ),
			'logo'   => __( 'Logo', 'favr-directory' ),
		) + \FavrDirectory\Integration\FieldValues::textOptions();
		return array(
			'field'       => array(
				'label'   => __( 'Show', 'favr-directory' ),
				'type'    => 'select',
				'default' => 'phone',
				'options' => $fields,
			),
			'link'        => array(
				'label'   => __( 'Link phone, email and website', 'favr-directory' ),
				'type'    => 'switcher',
				'default' => true,
			),
			'business_id' => array(
				'label'       => __( 'Business ID', 'favr-directory' ),
				'type'        => 'number',
				'default'     => 0,
				'min'         => 0,
				'description' => __( '0 uses the current business.', 'favr-directory' ),
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	protected function output( array $settings ): string {
		$html = ( new Shortcodes() )->field(
			array(
				'field' => (string) $settings['field'],
				'id'    => (int) $settings['business_id'],
				'link'  => '1' === (string) $settings['link'] ? '1' : '0',
			)
		);
		return '' === $html ? '' : '<div class="favr-field-output">' . $html . '</div>';
	}
}
