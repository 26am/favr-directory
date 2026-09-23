<?php
/**
 * Structured-data helper tests.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use FavrDirectory\Frontend\Seo;

final class SeoTest extends TestCase {

	public function test_opening_hours_follow_googles_format(): void {
		$spec = Seo::openingHours(
			array(
				'mon' => array( 'status' => 'open', 'open' => '09:00', 'close' => '17:00' ),
				'tue' => array( 'status' => 'closed', 'open' => '', 'close' => '' ),
				'sun' => array( 'status' => '24h', 'open' => '', 'close' => '' ),
			)
		);
		$this->assertCount( 2, $spec, 'Closed days are omitted.' );
		$this->assertSame( 'https://schema.org/Monday', $spec[0]['dayOfWeek'] );
		$this->assertSame( array( '09:00', '17:00' ), array( $spec[0]['opens'], $spec[0]['closes'] ) );
		$this->assertSame( array( '00:00', '23:59' ), array( $spec[1]['opens'], $spec[1]['closes'] ), '24h uses the documented 00:00–23:59 form.' );
	}

	public function test_no_hours_no_spec(): void {
		$this->assertSame( array(), Seo::openingHours( array() ) );
	}
}
