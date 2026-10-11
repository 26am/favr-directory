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

	/**
	 * @dataProvider counts
	 *
	 * @param int    $published Published listings.
	 * @param string $label     Expected word.
	 */
	public function test_people_directory_card_counts_listings_not_members( int $published, string $label ): void {
		// "38 members" under Directory sat next to the Members card's own count of members.
		Functions\when( 'get_option' )->alias( static fn( $key, $default = false ) => 'favr_directory_settings' === $key ? array( 'listing_kind' => 'person' ) : $default );
		Functions\when( 'admin_url' )->returnArg();
		Functions\when( 'wp_count_posts' )->justReturn( (object) array( 'publish' => $published ) );
		Functions\when( 'get_posts' )->justReturn( array() );
		Functions\when( 'number_format_i18n' )->alias( static fn( $number ) => (string) $number );
		Functions\when( '_n' )->alias( static fn( $single, $plural, $number ) => 1 === (int) $number ? $single : $plural );
		Settings::flush();

		$stat = ( new FavrSites() )->card()['stats'][0];
		$this->assertSame( (string) $published, $stat['value'] );
		$this->assertSame( $label, $stat['label'] );
	}

	/** @return array<string, array{0: int, 1: string}> */
	public static function counts(): array {
		return array(
			'one'  => array( 1, 'listing' ),
			'many' => array( 38, 'listings' ),
		);
	}
}
