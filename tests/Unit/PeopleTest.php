<?php
/**
 * People directories: nouns and language lists.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Model\Business;
use FavrDirectory\Model\Ranking;
use FavrDirectory\Support\Settings;

final class PeopleTest extends TestCase {

	/** @var array<string, mixed> */
	private array $option = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias( fn( $key, $default = false ) => 'favr_directory_settings' === $key ? $this->option : $default );
		Settings::flush();
	}

	private function settings( array $values ): void {
		$this->option = $values;
		Settings::flush();
	}

	public function test_default_nouns_follow_listing_kind(): void {
		$this->assertSame( 'businesses', Settings::noun() );
		$this->assertSame( 'business', Settings::noun( false ) );
		$this->settings( array( 'listing_kind' => 'person' ) );
		$this->assertTrue( Settings::listsPeople() );
		$this->assertSame( 'members', Settings::noun() );
		$this->assertSame( 'member', Settings::noun( false ) );
	}

	public function test_custom_nouns_win(): void {
		$this->settings(
			array(
				'listing_kind'  => 'person',
				'noun_singular' => 'attorney',
				'noun_plural'   => 'attorneys',
			)
		);
		$this->assertSame( 'attorneys', Settings::noun() );
		$this->assertSame( 'attorney', Settings::noun( false ) );
	}

	public function test_language_lists_split_cleanly(): void {
		$this->assertSame( array( 'Korean', 'Spanish' ), Business::splitList( 'Korean, Spanish' ) );
		$this->assertSame( array( 'Cantonese', 'Mandarin', 'Hakka' ), Business::splitList( ' Cantonese / Mandarin;Hakka,, Mandarin ' ) );
		$this->assertSame( array(), Business::splitList( '' ) );
	}

	public function test_sort_keys(): void {
		Functions\when( 'wp_strip_all_tags' )->alias( static fn( $v ) => strip_tags( (string) $v ) );
		$this->assertSame( 'alejo lemar', Ranking::sortKey( 'Lemar Alejo', '', true ) );
		$this->assertSame( 'maaswinkel gregory c', Ranking::sortKey( 'Gregory C. Maaswinkel', '', true ) );
		$this->assertSame( 'kim miller anna', Ranking::sortKey( 'Anna Kim-Miller', '', true ) );
		$this->assertSame( 'nguyen rogers camlinh', Ranking::sortKey( 'Camlinh Nguyen Rogers', 'Nguyen Rogers, Camlinh', true ) );
		$this->assertSame( 'main street bakery', Ranking::sortKey( 'Main Street Bakery', '', false ) );
		$this->assertSame( 'madonna', Ranking::sortKey( 'Madonna', '', true ) );
	}
}
