<?php
/**
 * CSV encoding tests.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use FavrDirectory\Vendor\FavrCore\Support\CsvFormat;

final class CsvFormatTest extends TestCase {

	public function test_hours_round_trip(): void {
		$cell  = 'mon=9:00-17:00;tue=closed;sun=24h;fri=18:00–02:00';
		$hours = CsvFormat::decodeHours( $cell );
		$this->assertSame( array( 'status' => 'open', 'open' => '09:00', 'close' => '17:00' ), $hours['mon'] );
		$this->assertSame( '24h', $hours['sun']['status'] );
		$this->assertSame( '02:00', $hours['fri']['close'], 'Spreadsheet en dashes are accepted.' );
		$this->assertSame( 'mon=09:00-17:00;tue=closed;fri=18:00-02:00;sun=24h', CsvFormat::encodeHours( $hours ) );
	}

	public function test_hours_accept_friendly_times(): void {
		$hours = CsvFormat::decodeHours( 'mon=9am-5:30pm;tue=18:00-24:00;wed=9-17;thu=off;fri=nonsense' );
		$this->assertSame( array( 'status' => 'open', 'open' => '09:00', 'close' => '17:30' ), $hours['mon'] );
		$this->assertSame( '00:00', $hours['tue']['close'], '24:00 means "until midnight".' );
		$this->assertSame( '17:00', $hours['wed']['close'] );
		$this->assertSame( 'closed', $hours['thu']['status'] );
		$this->assertArrayNotHasKey( 'fri', $hours, 'Unreadable specs are not silently turned into "closed".' );
		$this->assertSame( array( 'fri=nonsense' ), CsvFormat::invalidHourSpecs( 'mon=9am-5pm;fri=nonsense' ) );
	}

	public function test_time_parser(): void {
		$this->assertSame( '00:00', CsvFormat::time( '12am' ) );
		$this->assertSame( '12:00', CsvFormat::time( '12 pm' ) );
		$this->assertNull( CsvFormat::time( '13pm' ) );
		$this->assertNull( CsvFormat::time( '25:00' ) );
	}

	public function test_repeater_round_trip(): void {
		$subs = array( array( 'id' => 'label' ), array( 'id' => 'url' ) );
		$rows = array(
			array( 'label' => 'Menu', 'url' => 'https://a.example/menu' ),
			array( 'label' => 'Odd;label', 'url' => 'https://b.example/?a=1;b=2' ),
		);
		$cell = CsvFormat::encodeRepeater( $rows, $subs );
		$back = CsvFormat::decodeRepeater( $cell, $subs );
		$this->assertSame( 'https://b.example/?a=1;b=2', $back[1]['url'] );
		$this->assertSame( 'Menu', $back[0]['label'] );
	}

	public function test_toggle_blank_means_default(): void {
		$on_by_default = array( 'type' => 'toggle', 'default' => '1' );
		$this->assertSame( '1', CsvFormat::decode( $on_by_default, '' ) );
		$this->assertSame( 'no', CsvFormat::decode( $on_by_default, 'no' ) );
		$this->assertSame( 'no', CsvFormat::encode( $on_by_default, '0' ) );
	}

	public function test_formula_injection_is_neutralized_and_reversible(): void {
		foreach ( array( '=HYPERLINK("x")', '+1 555 0100', '-122.41', '@SUM(A1)' ) as $value ) {
			$safe = CsvFormat::safeCell( $value );
			$this->assertSame( "'", $safe[0] );
			$this->assertSame( $value, CsvFormat::unsafeCell( $safe ) );
		}
		$this->assertSame( "'quoted", CsvFormat::unsafeCell( "'quoted" ), 'Ordinary apostrophes survive.' );
	}

	public function test_split_list(): void {
		$this->assertSame( array( 'Food > Bakeries', 'Retail' ), CsvFormat::splitList( ' Food > Bakeries | Retail | ' ) );
	}
}
