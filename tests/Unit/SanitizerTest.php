<?php
/**
 * Sanitizer tests.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use FavrDirectory\Fields\Sanitizer;

final class SanitizerTest extends TestCase {

	private function field( string $type, array $extra = array() ): array {
		return array_merge(
			array(
				'type'       => $type,
				'maxlength'  => 0,
				'options'    => array(),
				'min'        => null,
				'max'        => null,
				'default'    => '',
				'sub_fields' => array(),
			),
			$extra
		);
	}

	public function test_url_gets_https_when_scheme_missing(): void {
		$this->assertSame( 'https://example.com', Sanitizer::sanitize( $this->field( 'url' ), 'example.com' ) );
		$this->assertSame( 'http://example.com/a', Sanitizer::sanitize( $this->field( 'url' ), 'http://example.com/a' ) );
		$this->assertSame( '', Sanitizer::sanitize( $this->field( 'url' ), '   ' ) );
	}

	public function test_url_rejects_javascript_scheme(): void {
		\Brain\Monkey\Functions\when( 'esc_url_raw' )->alias(
			static fn( $v, $protocols = array() ) => preg_match( '#^(https?)://#', (string) $v ) ? $v : ''
		);
		$this->assertSame( '', Sanitizer::sanitize( $this->field( 'url' ), 'javascript:alert(1)' ) );
	}

	public function test_tel_keeps_only_phone_characters(): void {
		$this->assertSame( '+1 (555) 010-1100 ext 4', Sanitizer::sanitize( $this->field( 'tel' ), '+1 (555)  010-1100 ext 4<script>' ) );
	}

	public function test_tel_extensions_are_normalized(): void {
		$this->assertSame( '555-0100 ext 12', Sanitizer::tel( '555-0100 x12' ) );
		$this->assertSame( '555-0100 ext 7', Sanitizer::tel( '555-0100 Ext. 7' ) );
		$this->assertSame( '', Sanitizer::tel( 'call us!' ) );
	}

	public function test_number_respects_bounds(): void {
		$field = $this->field( 'number', array( 'min' => 1600, 'max' => 2100 ) );
		$this->assertSame( 1982, Sanitizer::sanitize( $field, '1982' ) );
		$this->assertSame( '', Sanitizer::sanitize( $field, '42' ) );
		$this->assertSame( '', Sanitizer::sanitize( $field, 'abc' ) );
	}

	public function test_select_only_accepts_known_options(): void {
		$field = $this->field( 'select', array( 'options' => array( '' => '—', '1-10' => '1–10' ) ) );
		$this->assertSame( '1-10', Sanitizer::sanitize( $field, '1-10' ) );
		$this->assertSame( '', Sanitizer::sanitize( $field, 'bogus' ) );
	}

	public function test_toggle_accepts_human_values(): void {
		foreach ( array( 'yes', 'TRUE', '1', 'on', 'y' ) as $on ) {
			$this->assertSame( '1', Sanitizer::toggle( $on ), $on );
		}
		foreach ( array( 'no', '0', '', 'off' ) as $off ) {
			$this->assertSame( '0', Sanitizer::toggle( $off ), $off );
		}
	}

	public function test_off_toggle_is_empty_only_when_default_is_off(): void {
		$this->assertTrue( Sanitizer::isEmpty( $this->field( 'toggle' ), '0' ) );
		$this->assertFalse( Sanitizer::isEmpty( $this->field( 'toggle', array( 'default' => '1' ) ), '0' ) );
	}

	public function test_date_normalizes_formats(): void {
		$this->assertSame( '2027-12-31', Sanitizer::date( '2027-12-31' ) );
		$this->assertSame( '2027-12-31', Sanitizer::date( 'December 31, 2027' ) );
		$this->assertSame( '', Sanitizer::date( 'not a date' ) );
	}

	public function test_gallery_dedupes_and_keeps_order(): void {
		$this->assertSame( array( 5, 3, 9 ), Sanitizer::gallery( '5,3,5,0,9,-1' ) );
		$this->assertSame( array(), Sanitizer::gallery( 'nope' ) );
	}

	public function test_image_rejects_negative_ids(): void {
		$field = $this->field( 'image' );
		$this->assertSame( 12, Sanitizer::sanitize( $field, '12' ) );
		$this->assertSame( '', Sanitizer::sanitize( $field, '-1' ) );
		$this->assertSame( '', Sanitizer::sanitize( $field, '' ) );
	}

	public function test_hours_normalizes_a_week(): void {
		$hours = Sanitizer::hours(
			array(
				'mon' => array( 'status' => 'open', 'open' => '09:00', 'close' => '17:00' ),
				'sat' => array( 'status' => '24h' ),
				'tue' => array( 'status' => 'open', 'open' => '25:00', 'close' => '17:00' ),
			)
		);
		$this->assertCount( 7, $hours );
		$this->assertSame( array( 'status' => 'open', 'open' => '09:00', 'close' => '17:00' ), $hours['mon'] );
		$this->assertSame( '24h', $hours['sat']['status'] );
		$this->assertSame( 'closed', $hours['tue']['status'], 'Invalid times never produce an "open" day.' );
		$this->assertSame( 'closed', $hours['sun']['status'] );
	}

	public function test_hours_without_any_day_is_empty(): void {
		$this->assertSame( array(), Sanitizer::hours( array() ) );
		$this->assertSame( array(), Sanitizer::hours( 'garbage' ) );
	}

	public function test_checkboxes_keep_known_keys(): void {
		$options = array( 'a' => 'A', 'b' => 'B' );
		$this->assertSame( array( 'a', 'b' ), Sanitizer::checkboxes( 'b|a|zzz', $options ) );
		$this->assertSame( array( 'b' ), Sanitizer::checkboxes( array( 'b', 'x' ), $options ) );
	}

	public function test_repeater_drops_empty_rows(): void {
		$field = $this->field(
			'repeater',
			array(
				'sub_fields' => array(
					array( 'id' => 'label', 'type' => 'text' ),
					array( 'id' => 'url', 'type' => 'url' ),
				),
			)
		);
		$rows = Sanitizer::sanitize(
			$field,
			array(
				array( 'label' => 'Menu', 'url' => 'example.com/menu' ),
				array( 'label' => '', 'url' => '' ),
				'not-a-row',
			)
		);
		$this->assertSame( array( array( 'label' => 'Menu', 'url' => 'https://example.com/menu' ) ), $rows );
	}

	public function test_text_is_trimmed_to_maxlength(): void {
		$this->assertSame( 'abc', Sanitizer::sanitize( $this->field( 'text', array( 'maxlength' => 3 ) ), 'abcdef' ) );
	}
}
