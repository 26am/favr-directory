<?php
/**
 * Ranking and completeness tests.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use FavrDirectory\Model\Ranking;
use FavrDirectory\Support\Completeness;

final class RankingAndCompletenessTest extends TestCase {

	public function test_featured_always_outranks_non_featured(): void {
		$this->assertLessThan( Ranking::rank( false, 1 ), Ranking::rank( true, null ) );
	}

	public function test_higher_tier_outranks_lower_and_no_level_is_last(): void {
		$this->assertLessThan( Ranking::rank( false, 2 ), Ranking::rank( false, 1 ) );
		$this->assertLessThan( Ranking::rank( false, null ), Ranking::rank( false, 998 ) );
		$this->assertSame( Ranking::rank( false, 998 ), Ranking::rank( false, 5000 ), 'Orders are clamped.' );
	}

	public function test_completeness_scores_weighted_fields(): void {
		$fields = array(
			'phone' => array( 'weight' => 10 ),
			'logo'  => array( 'weight' => 10 ),
			'notes' => array( 'weight' => 0 ),
		);
		$core   = array( 'title' => true, 'description' => true, 'category' => true, 'cover' => true );
		$this->assertSame( 100, Completeness::score( array( 'phone' => '555', 'logo' => 12 ), $fields, $core ) );

		$score = Completeness::score( array( 'phone' => '555', 'logo' => 0 ), $fields, $core );
		$this->assertSame( (int) round( 46 / 56 * 100 ), $score );
		$this->assertSame( array( 'logo' ), Completeness::missing( array( 'phone' => '555', 'logo' => 0 ), $fields, $core ) );
	}

	public function test_missing_is_sorted_by_weight(): void {
		$fields = array(
			'a' => array( 'weight' => 2 ),
			'b' => array( 'weight' => 8 ),
		);
		$core   = array( 'title' => true, 'description' => true, 'category' => true, 'cover' => true );
		$this->assertSame( array( 'b', 'a' ), Completeness::missing( array(), $fields, $core ) );
	}
}
