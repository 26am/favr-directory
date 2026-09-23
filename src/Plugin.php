<?php
/**
 * Composition root.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory;

use FavrDirectory\Admin;
use FavrDirectory\Frontend;
use FavrDirectory\Model;

/**
 * Wires every service. Layers are guarded by context: the model and front end always load
 * (REST, the block editor and cron need them), admin screens only in wp-admin, CLI only
 * under WP-CLI.
 */
final class Plugin {

	/**
	 * Whether boot() already ran.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/** Boot once. */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		add_action( 'init', array( self::class, 'loadTextDomain' ), 1 );

		( new Model\Registrar() )->hook();
		( new Model\Ranking() )->hook();
		( new Model\RestPrivacy() )->hook();
		add_action( 'init', array( Model\Activation::class, 'maybeUpgrade' ), 20 );

		Frontend\DirectoryQuery::hook();
		( new Frontend\Assets() )->hook();
		( new Frontend\Blocks() )->hook();
		( new Frontend\BlockTemplates() )->hook();
		( new Frontend\ClassicTemplates() )->hook();
		( new Frontend\Profile() )->hook();
		( new Frontend\Shortcodes() )->hook();
		( new Frontend\Seo() )->hook();

		( new Editing\FrontEditor() )->hook();
		( new Editing\UploadRoute() )->hook();
		( new Editing\Claims() )->hook();
		( new Editing\ChangeQueue() )->hook();

		if ( is_admin() ) {
			( new Admin\Assets() )->hook();
			( new Admin\EditScreen() )->hook();
			( new Admin\ManagersBox() )->hook();
			\FavrDirectory\Vendor\FavrCore\Approvals\Inbox::boot();
			( new Admin\ListScreen() )->hook();
			( new Admin\LevelScreen() )->hook();
			( new Admin\SettingsPage() )->hook();
			( new Admin\ImportExportPage() )->hook();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'favr-directory', Cli\Command::class );
		}

		/**
		 * Fires after Favr Directory has wired its services. Extensions hook here.
		 */
		do_action( 'favr_directory_loaded' );
	}

	/** Translations. */
	public static function loadTextDomain(): void {
		load_plugin_textdomain( 'favr-directory', false, dirname( plugin_basename( FAVR_DIRECTORY_FILE ) ) . '/languages' );
	}
}
