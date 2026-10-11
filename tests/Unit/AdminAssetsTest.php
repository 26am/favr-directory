<?php
/**
 * What the admin field script is loaded with.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use FavrDirectory\Admin\Assets;
use FavrDirectory\Schema\Identifiers as ID;

final class AdminAssetsTest extends TestCase {

	/**
	 * The shared field script calls sortable() while it starts, on every screen it is loaded on.
	 *
	 * @dataProvider directoryScreens
	 *
	 * @param string $base        Screen base.
	 * @param string $hook_suffix Admin page.
	 */
	public function test_the_field_script_always_gets_sortable( string $base, string $hook_suffix ): void {
		$this->assertContains( 'jquery-ui-sortable', Assets::fieldScriptDependencies( $base, $hook_suffix ) );
		$this->assertContains( 'jquery', Assets::fieldScriptDependencies( $base, $hook_suffix ) );
	}

	/** @return array<string, array{0: string, 1: string}> */
	public static function directoryScreens(): array {
		return array(
			'list'       => array( 'edit', 'edit.php' ),
			'edit'       => array( 'post', 'post.php' ),
			'new'        => array( 'post', 'post-new.php' ),
			'categories' => array( 'edit-tags', 'edit-tags.php' ),
			'one term'   => array( 'term', 'term.php' ),
			'settings'   => array( ID::POST_TYPE . '_page_favr-directory-settings', ID::POST_TYPE . '_page_favr-directory-settings' ),
			'import'     => array( ID::POST_TYPE . '_page_favr-directory-import-export', ID::POST_TYPE . '_page_favr-directory-import-export' ),
		);
	}

	public function test_the_color_picker_only_loads_where_a_color_is_picked(): void {
		$this->assertContains( 'wp-color-picker', Assets::fieldScriptDependencies( 'edit-tags', 'edit-tags.php' ) );
		$this->assertContains( 'wp-color-picker', Assets::fieldScriptDependencies( 'term', 'term.php' ) );
		$this->assertContains( 'wp-color-picker', Assets::fieldScriptDependencies( ID::POST_TYPE . '_page_favr-directory-settings', ID::POST_TYPE . '_page_favr-directory-settings' ) );
		$this->assertNotContains( 'wp-color-picker', Assets::fieldScriptDependencies( 'edit', 'edit.php' ) );
		$this->assertNotContains( 'wp-color-picker', Assets::fieldScriptDependencies( 'post', 'post.php' ) );
	}
}
