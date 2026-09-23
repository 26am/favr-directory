<?php
/**
 * Asset cache-busting.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Support;

use FavrDirectory\Vendor\FavrCore\Support\AssetVersion as CoreVersion;

/**
 * Plugin version, plus file mtime under WP_DEBUG so edits show up without a version bump.
 */
final class AssetVersion {

	/**
	 * Version string for a plugin-relative asset path.
	 *
	 * @param string $relative Path relative to the plugin root.
	 */
	public static function of( string $relative ): string {
		return CoreVersion::of( FAVR_DIRECTORY_PATH . $relative, FAVR_DIRECTORY_VERSION );
	}
}
