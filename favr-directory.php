<?php
/**
 * Plugin Name:       Favr Directory
 * Plugin URI:        https://github.com/26am/favr-directory
 * Description:       A friendly business directory for Chambers of Commerce and associations. Part of Favr Sites.
 * Version:           1.0.0
 * Requires at least: 6.7
 * Requires PHP:      8.1
 * Author:            Favr Sites
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       favr-directory
 * Domain Path:       /languages
 *
 * @package FavrDirectory
 */

defined( 'ABSPATH' ) || exit;

define( 'FAVR_DIRECTORY_VERSION', '1.0.0' );
define( 'FAVR_DIRECTORY_FILE', __FILE__ );
define( 'FAVR_DIRECTORY_PATH', plugin_dir_path( __FILE__ ) );
define( 'FAVR_DIRECTORY_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader when present (dev), otherwise the bundled PSR-4 loader so the plugin
// runs from a plain zip with no build step.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			$prefix = 'FavrDirectory\\';
			if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
				return;
			}
			$file = __DIR__ . '/src/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( \FavrDirectory\Model\Activation::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \FavrDirectory\Model\Activation::class, 'deactivate' ) );

\FavrDirectory\Plugin::boot();
