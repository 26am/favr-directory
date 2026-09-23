<?php
/**
 * Elementor integration (optional).
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration;

use FavrDirectory\Vendor\FavrCore\Integrations\Elementor\Widget;

/**
 * Native Elementor widgets in a shared "Favr" panel category, plus dynamic tags for Elementor
 * Pro's Theme Builder. Everything hangs off Elementor's own hooks, so nothing loads (and no
 * Elementor class is referenced) unless Elementor is active.
 */
final class Elementor {

	/** Hook. */
	public function hook(): void {
		add_action( 'elementor/elements/categories_registered', array( Widget::class, 'registerCategory' ) );
		add_action( 'elementor/widgets/register', array( $this, 'widgets' ) );
		add_action( 'elementor/dynamic_tags/register', array( $this, 'tags' ) );
	}

	/**
	 * Widgets.
	 *
	 * @param object $manager Widgets manager.
	 */
	public function widgets( $manager ): void {
		foreach ( array( Elementor\DirectoryWidget::class, Elementor\ProfileWidget::class, Elementor\FieldWidget::class, Elementor\MyListingWidget::class ) as $class ) {
			$manager->register( new $class() );
		}
	}

	/**
	 * Dynamic tags (used by Elementor Pro).
	 *
	 * @param object $tags Dynamic tags manager.
	 */
	public function tags( $tags ): void {
		$tags->register_group( 'favr-directory', array( 'title' => __( 'Favr Directory', 'favr-directory' ) ) );
		foreach ( array( Elementor\Tags\TextTag::class, Elementor\Tags\UrlTag::class, Elementor\Tags\ImageTag::class ) as $class ) {
			$tags->register( new $class() );
		}
	}
}
