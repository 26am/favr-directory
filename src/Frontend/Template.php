<?php
/**
 * Template loading with theme overrides.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * Renders plugin templates from /templates. A theme overrides any of them by shipping
 * `favr-directory/{name}.php` (child theme first, then parent).
 */
final class Template {

	/**
	 * Resolve a template path.
	 *
	 * @param string $name Template name without extension, e.g. "parts/card".
	 */
	public static function locate( string $name ): string {
		$name  = ltrim( str_replace( '..', '', $name ), '/' );
		$theme = locate_template( array( ID::TEMPLATE_NAMESPACE . '/' . $name . '.php' ) );
		$path  = '' !== $theme ? $theme : FAVR_DIRECTORY_PATH . 'templates/' . $name . '.php';

		/**
		 * Filter the resolved template path.
		 *
		 * @param string $path Absolute path.
		 * @param string $name Template name.
		 */
		return (string) apply_filters( 'favr_directory_template', $path, $name );
	}

	/**
	 * Render a template to a string.
	 *
	 * @param string               $name Template name.
	 * @param array<string, mixed> $vars Variables available to the template.
	 */
	public static function render( string $name, array $vars = array() ): string {
		$path = self::locate( $name );
		if ( ! is_readable( $path ) ) {
			return '';
		}
		ob_start();
		( static function ( string $__path, array $__vars ): void {
			extract( $__vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- template scope.
			include $__path;
		} )( $path, $vars );
		return (string) ob_get_clean();
	}
}
