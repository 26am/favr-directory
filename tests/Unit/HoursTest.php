<?php
/**
 * Hours tests.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use FavrDirectory\Vendor\FavrCore\Support\Hours;

final class HoursTest extends TestCase {

	private function moment( string $when ): \DateTimeImmutable {
		return new \DateTimeImmutable( $when, new \DateTimeZone( 'America/Chicago' ) );
	}

	private function week( array $days ): array {
		$out = array();
		foreach ( Hours::DAYS as $day ) {
			$out[ $day ] = $days[ $day ] ?? array( 'status' => 'closed', 'open' => '', 'close' => '' );
		}
		return $out;
	}

	public function test_no_schedule_is_unknown(): void {
		$this->assertNull( Hours::isOpenAt( array(), $this->moment( '2026-09-23 12:00' ) ) );
	}

	public function test_regular_day(): void {
		$hours = $this->week( array( 'wed' => array( 'status' => 'open', 'open' => '09:00', 'close' => '17:00' ) ) );
		$this->assertTrue( Hours::isOpenAt( $hours, $this->moment( '2026-09-23 09:00' ) ) ); // Wednesday.
		$this->assertTrue( Hours::isOpenAt( $hours, $this->moment( '2026-09-23 16:59' ) ) );
		$this->assertFalse( Hours::isOpenAt( $hours, $this->moment( '2026-09-23 17:00' ) ) );
		$this->assertFalse( Hours::isOpenAt( $hours, $this->moment( '2026-09-24 10:00' ) ) ); // Thursday closed.
	}

	public function test_overnight_range_spills_into_next_day(): void {
		$hours = $this->week( array( 'fri' => array( 'status' => 'open', 'open' => '18:00', 'close' => '02:00' ) ) );
		$this->assertTrue( Hours::isOpenAt( $hours, $this->moment( '2026-09-25 23:30' ) ) ); // Friday night.
		$this->assertTrue( Hours::isOpenAt( $hours, $this->moment( '2026-09-26 01:30' ) ) ); // Early Saturday.
		$this->assertFalse( Hours::isOpenAt( $hours, $this->moment( '2026-09-26 02:00' ) ) );
	}

	public function test_open_24_hours(): void {
		$hours = $this->week( array( 'sun' => array( 'status' => '24h', 'open' => '', 'close' => '' ) ) );
		$this->assertTrue( Hours::isOpenAt( $hours, $this->moment( '2026-09-27 03:00' ) ) );
	}

	public function test_grouped_collapses_identical_days(): void {
		$open  = array( 'status' => 'open', 'open' => '09:00', 'close' => '17:00' );
		$hours = $this->week( array( 'mon' => $open, 'tue' => $open, 'wed' => $open ) );
		$groups = Hours::grouped( $hours );
		$this->assertSame( array( 'mon', 'tue', 'wed' ), $groups[0]['days'] );
		$this->assertSame( array( 'thu', 'fri', 'sat', 'sun' ), $groups[1]['days'] );
	}

	public function test_format_time(): void {
		$this->assertSame( '6:30 pm', Hours::formatTime( '18:30', 'g:i a' ) );
	}
}
