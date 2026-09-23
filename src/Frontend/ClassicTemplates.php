<?php
/**
 * Classic theme templates.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * Classic themes get plugin PHP templates via the template hierarchy filters. A theme
 * template named `favr-directory/…` (or the standard single-favr_business.php etc.) wins.
 * Block themes are skipped: BlockTemplates handles them.
 */
final class ClassicTemplates {

	/** Hook. */
	public function hook(): void {
		add_filter( 'single_template', array( $this, 'single' ) );
		add_filter( 'archive_template', array( $this, 'archive' ) );
		add_filter( 'taxonomy_template', array( $this, 'taxonomy' ) );
	}

	/**
	 * Single business.
	 *
	 * @param string $template Resolved template.
	 */
	public function single( string $template ): string {
		if ( wp_is_block_theme() || ! is_singular( ID::POST_TYPE ) || $this->themeHasOwn( 'single-' . ID::POST_TYPE . '.php', $template ) ) {
			return $template;
		}
		return $this->resolve( 'single-business', $template );
	}

	/**
	 * Directory archive.
	 *
	 * @param string $template Resolved template.
	 */
	public function archive( string $template ): string {
		if ( wp_is_block_theme() || ! is_post_type_archive( ID::POST_TYPE ) || $this->themeHasOwn( 'archive-' . ID::POST_TYPE . '.php', $template ) ) {
			return $template;
		}
		return $this->resolve( 'archive-business', $template );
	}

	/**
	 * Category archive.
	 *
	 * @param string $template Resolved template.
	 */
	public function taxonomy( string $template ): string {
		if ( wp_is_block_theme() || ! is_tax( ID::TAX_CATEGORY ) || $this->themeHasOwn( 'taxonomy-' . ID::TAX_CATEGORY . '.php', $template ) ) {
			return $template;
		}
		return $this->resolve( 'archive-business', $template );
	}

	/**
	 * Whether the theme already resolved a dedicated template of its own.
	 *
	 * @param string $file     Standard hierarchy file name.
	 * @param string $template Resolved template.
	 */
	private function themeHasOwn( string $file, string $template ): bool {
		return '' !== $template && basename( $template ) === $file;
	}

	/**
	 * Theme override first, then the plugin's classic template.
	 *
	 * @param string $name     Template name in templates/classic.
	 * @param string $fallback Fallback.
	 */
	private function resolve( string $name, string $fallback ): string {
		$path = Template::locate( 'classic/' . $name );
		return is_readable( $path ) ? $path : $fallback;
	}
}
