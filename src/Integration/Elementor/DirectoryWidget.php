<?php
/**
 * Elementor: Business Directory.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration\Elementor;

use FavrDirectory\Frontend\Directory;
use FavrDirectory\Vendor\FavrCore\Integrations\Elementor\Widget;

/**
 * The searchable directory (same renderer as the block and [favr_directory]).
 */
final class DirectoryWidget extends Widget {

	/** Name. */
	public function get_name(): string {
		return 'favr-directory';
	}

	/** Title. */
	public function get_title(): string {
		return __( 'Business Directory', 'favr-directory' );
	}

	/** Icon. */
	public function get_icon(): string {
		return 'eicon-posts-grid';
	}

	/** Keywords. */
	public function get_keywords(): array {
		return array( 'directory', 'business', 'members', 'chamber', 'favr' );
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
		$categories = array( '' => __( 'All categories', 'favr-directory' ) );
		foreach ( Directory::categoryOptions() as $cat ) {
			$categories[ $cat['slug'] ] = str_repeat( '— ', $cat['depth'] ) . $cat['name'];
		}
		$levels = array( '' => __( 'All levels', 'favr-directory' ) );
		foreach ( Directory::levelOptions() as $level ) {
			$levels[ (string) $level->slug ] = (string) $level->name;
		}
		return array(
			'title'        => array(
				'label' => __( 'Heading', 'favr-directory' ),
			),
			'category'     => array(
				'label'   => __( 'Category', 'favr-directory' ),
				'type'    => 'select',
				'options' => $categories,
			),
			'level'        => array(
				'label'   => __( 'Membership level', 'favr-directory' ),
				'type'    => 'select',
				'options' => $levels,
			),
			'featured'     => array(
				'label' => __( 'Featured businesses only', 'favr-directory' ),
				'type'  => 'switcher',
			),
			'layout'       => array(
				'label'   => __( 'Layout', 'favr-directory' ),
				'type'    => 'select',
				'options' => array(
					''     => __( 'Default (settings)', 'favr-directory' ),
					'grid' => __( 'Grid of cards', 'favr-directory' ),
					'list' => __( 'Compact list', 'favr-directory' ),
				),
			),
			'order'        => array(
				'label'   => __( 'Order', 'favr-directory' ),
				'type'    => 'select',
				'default' => 'rank',
				'options' => array(
					'rank'   => __( 'Featured & level first', 'favr-directory' ),
					'name'   => __( 'A–Z', 'favr-directory' ),
					'newest' => __( 'Newest', 'favr-directory' ),
					'random' => __( 'Random', 'favr-directory' ),
				),
			),
			'per_page'     => array(
				'label'       => __( 'Businesses per page', 'favr-directory' ),
				'type'        => 'number',
				'min'         => 0,
				'max'         => 100,
				'default'     => 0,
				'description' => __( '0 uses the directory setting.', 'favr-directory' ),
			),
			'search'       => array(
				'label'   => __( 'Search and category filter', 'favr-directory' ),
				'type'    => 'switcher',
				'default' => true,
			),
			'letters'      => array(
				'label'   => __( 'A–Z letters', 'favr-directory' ),
				'type'    => 'switcher',
				'default' => true,
			),
			'level_filter' => array(
				'label' => __( 'Level filter', 'favr-directory' ),
				'type'  => 'switcher',
			),
			'pagination'   => array(
				'label'   => __( 'Pagination', 'favr-directory' ),
				'type'    => 'switcher',
				'default' => true,
			),
		);
	}

	/**
	 * Render.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	protected function output( array $settings ): string {
		$atts = array(
			'title'        => (string) $settings['title'],
			'category'     => (string) $settings['category'],
			'level'        => (string) $settings['level'],
			'featured'     => '1' === (string) $settings['featured'],
			'order'        => (string) $settings['order'],
			'search'       => '1' === (string) $settings['search'],
			'letters'      => '1' === (string) $settings['letters'],
			'level_filter' => '1' === (string) $settings['level_filter'],
			'pagination'   => '1' === (string) $settings['pagination'],
		);
		if ( '' !== (string) $settings['layout'] ) {
			$atts['layout'] = (string) $settings['layout'];
		}
		if ( (int) $settings['per_page'] > 0 ) {
			$atts['per_page'] = (int) $settings['per_page'];
		}
		return Directory::render( $atts );
	}
}
