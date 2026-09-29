<?php
/**
 * Favr dashboard contribution.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Integration\FavrSites;
use FavrDirectory\Support\Settings;

final class FavrSitesTest extends TestCase {

	protected function tearDown(): void {
		Settings::flush();
		parent::tearDown();
	}

	public function test_people_directory_action_does_not_collide_with_favr_members(): void {
		Functions\when( 'get_option' )->alias( static fn( $key, $default = false ) => 'favr_directory_settings' === $key ? array( 'listing_kind' => 'person' ) : $default );
		Functions\when( 'admin_url' )->returnArg();
		Settings::flush();
		$action = ( new FavrSites() )->actions( array() )[0];
		$this->assertNotSame( 'Add member', $action['label'] ); // Favr Members adds its own "Add member".
		$this->assertSame( 'Add directory listing', $action['label'] );
	}
}
