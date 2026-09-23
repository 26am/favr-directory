<?php
/**
 * Cache-busting asset versions.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Support;

/**
 * The plugin version in production; the file's mtime under WP_DEBUG so edits show up
 * immediately during development.
 */
final class AssetVersion {

	/**
	 * Version string for a plugin-relative asset path.
	 *
	 * @param string $relative Path relative to the plugin root.
	 */
	public static function of( string $relative ): string {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			$mtime = @filemtime( FAVR_DIRECTORY_PATH . $relative ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- missing file just falls back.
			if ( $mtime ) {
				return FAVR_DIRECTORY_VERSION . '.' . $mtime;
			}
		}
		return FAVR_DIRECTORY_VERSION;
	}
}
