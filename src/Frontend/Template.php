<?php
/**
 * Template loading with theme overrides.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Vendor\FavrCore\Support\Template as CoreTemplate;

/**
 * Renders plugin templates from /templates. A theme overrides any of them by shipping
 * `favr-directory/{name}.php` (child theme first, then parent). The resolved path passes
 * through the `favr_directory_template` filter.
 */
final class Template {

	/**
	 * Shared loader.
	 *
	 * @var CoreTemplate|null
	 */
	private static ?CoreTemplate $loader = null;

	/** The loader. */
	private static function loader(): CoreTemplate {
		if ( null === self::$loader ) {
			self::$loader = new CoreTemplate( FAVR_DIRECTORY_PATH . 'templates', ID::TEMPLATE_NAMESPACE, 'favr_directory_template' );
		}
		return self::$loader;
	}

	/**
	 * Resolve a template path.
	 *
	 * @param string $name Template name without extension, e.g. "parts/card".
	 */
	public static function locate( string $name ): string {
		return self::loader()->locate( $name );
	}

	/**
	 * Render a template to a string.
	 *
	 * @param string               $name Template name.
	 * @param array<string, mixed> $vars Variables available to the template.
	 */
	public static function render( string $name, array $vars = array() ): string {
		return self::loader()->render( $name, $vars );
	}
}
