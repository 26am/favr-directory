<?php
/**
 * The Membership levels list: in the levels' own order, each with its colour before its name.
 * Favr Members draws the same list when it runs without the Directory (its
 * tests/Unit/LevelScreenTest.php): the two must read alike.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Admin\LevelScreen;
use FavrDirectory\Schema\Identifiers as ID;

final class LevelScreenTest extends TestCase {

	/** @var array<int, array<string, string>> Term meta by term id. */
	private array $meta = array();

	/** @var array<string, mixed> The request's query string. */
	private array $get_before = array();

	protected function setUp(): void {
		parent::setUp();
		$this->get_before = $_GET;
		Functions\stubs(
			array(
				'esc_attr'           => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
				'sanitize_hex_color' => static fn( $color ) => '' === $color ? '' : ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color ) ? $color : null ),
			)
		);
		Functions\when( 'get_term_meta' )->alias( fn( $term_id, $key, $single = false ) => $this->meta[ (int) $term_id ][ $key ] ?? '' );
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => 'edit-' . ID::TAX_LEVEL ) );
		$this->meta = array(
			25 => array(
				ID::TERM_META_ORDER => '3',
				ID::TERM_META_COLOR => '#64748b',
			),
			23 => array(
				ID::TERM_META_ORDER => '1',
				ID::TERM_META_COLOR => '#12355b',
			),
			24 => array(
				ID::TERM_META_ORDER => '2',
				ID::TERM_META_COLOR => '#0b7a7a',
			),
		);
	}

	protected function tearDown(): void {
		$_GET = $this->get_before;
		parent::tearDown();
	}

	/**
	 * A level as the list holds it.
	 *
	 * @param int    $id   Term id.
	 * @param string $name Name.
	 */
	private function level( int $id, string $name ): object {
		return (object) array(
			'term_id'  => $id,
			'name'     => $name,
			'taxonomy' => ID::TAX_LEVEL,
		);
	}

	/**
	 * The demo's levels, in the order WordPress fetches them: by name.
	 *
	 * @return list<object>
	 */
	private function byName(): array {
		return array( $this->level( 25, 'Associate' ), $this->level( 23, 'Fellow' ), $this->level( 24, 'Professional' ) );
	}

	/**
	 * The list showed an order number per level but was sorted by name (Associate 3, Fellow 1,
	 * Professional 2), so the screen contradicted itself.
	 */
	public function test_the_list_is_in_the_order_its_order_column_shows(): void {
		$listed = ( new LevelScreen() )->order( $this->byName(), array( ID::TAX_LEVEL ), array( 'fields' => 'all' ) );

		$this->assertSame( array( 'Fellow', 'Professional', 'Associate' ), array_column( $listed, 'name' ) );
	}

	public function test_levels_with_the_same_order_go_by_name_and_one_without_counts_as_zero(): void {
		$sorted = LevelScreen::byOrder(
			array( $this->level( 1, 'silver' ), $this->level( 2, 'Gold' ), $this->level( 3, 'Trial' ), $this->level( 4, 'Bronze' ) ),
			array(
				1 => 10,
				2 => 10,
				4 => 20,
			)
		);

		$this->assertSame( array( 'Trial', 'Gold', 'silver', 'Bronze' ), array_column( $sorted, 'name' ) );
	}

	public function test_a_clicked_column_heading_still_sorts_the_list_its_own_way(): void {
		$_GET['orderby'] = 'name';
		$listed          = ( new LevelScreen() )->order( $this->byName(), array( ID::TAX_LEVEL ), array( 'fields' => 'all' ) );

		$this->assertSame( array( 'Associate', 'Fellow', 'Professional' ), array_column( $listed, 'name' ) );
	}

	/**
	 * @dataProvider otherLists
	 *
	 * @param string               $screen     Screen id.
	 * @param list<string>         $taxonomies Taxonomies asked for.
	 * @param array<string, mixed> $args       The query.
	 */
	public function test_nothing_else_is_reordered( string $screen, array $taxonomies, array $args ): void {
		Functions\when( 'get_current_screen' )->justReturn( (object) array( 'id' => $screen ) );
		$terms = $this->byName();

		$this->assertSame( $terms, ( new LevelScreen() )->order( $terms, $taxonomies, $args ) );
	}

	/** @return array<string, array{0: string, 1: list<string>, 2: array<string, mixed>}> */
	public static function otherLists(): array {
		return array(
			'the levels in the Directory list filter' => array( 'edit-' . ID::POST_TYPE, array( ID::TAX_LEVEL ), array( 'fields' => 'all' ) ),
			'the levels box on a listing'             => array( ID::POST_TYPE, array( ID::TAX_LEVEL ), array( 'fields' => 'all' ) ),
			'the Directory categories list'           => array( 'edit-' . ID::TAX_CATEGORY, array( ID::TAX_CATEGORY ), array( 'fields' => 'all' ) ),
			'a count of the levels'                   => array( 'edit-' . ID::TAX_LEVEL, array( ID::TAX_LEVEL ), array( 'fields' => 'count' ) ),
			'level ids only'                          => array( 'edit-' . ID::TAX_LEVEL, array( ID::TAX_LEVEL ), array( 'fields' => 'ids' ) ),
			'levels and categories together'          => array( 'edit-' . ID::TAX_LEVEL, array( ID::TAX_LEVEL, ID::TAX_CATEGORY ), array( 'fields' => 'all' ) ),
		);
	}

	public function test_a_count_or_an_error_passes_through(): void {
		$screen = new LevelScreen();
		$this->assertSame( '3', $screen->order( '3', array( ID::TAX_LEVEL ), array( 'fields' => 'all' ) ) );
		$this->assertSame( array( 23, 24 ), $screen->order( array( 23, 24 ), array( ID::TAX_LEVEL ), array( 'fields' => 'all' ) ) );
	}

	public function test_the_order_column_follows_the_name_and_holds_only_the_number(): void {
		$screen = new LevelScreen();

		$this->assertSame(
			array( 'cb', 'name', 'favr_order', 'posts' ),
			array_keys(
				$screen->columns(
					array(
						'cb'          => '',
						'name'        => 'Name',
						'description' => 'Description',
						'posts'       => 'Count',
					)
				)
			)
		);
		$this->assertSame( '2', $screen->column( '', 'favr_order', 24 ) );
		$this->assertSame( 'kept', $screen->column( 'kept', 'posts', 24 ) );
	}

	/**
	 * The level's colour sits in front of its name, as in the Membership level box and the
	 * Directory list, not in front of the order number.
	 */
	public function test_the_colour_dot_comes_before_the_levels_name(): void {
		$screen = new LevelScreen();

		$this->assertSame( '<span class="favr-level__swatch" style="background:#12355b"></span> Fellow', $screen->name( 'Fellow', $this->level( 23, 'Fellow' ) ) );

		$this->meta[25][ ID::TERM_META_COLOR ] = '';
		$this->assertSame( 'Associate', $screen->name( 'Associate', $this->level( 25, 'Associate' ) ), 'No colour, no dot.' );
		$this->meta[25][ ID::TERM_META_COLOR ] = 'red; x:y';
		$this->assertSame( 'Associate', $screen->name( 'Associate', $this->level( 25, 'Associate' ) ), 'Only a hex colour reaches the style attribute.' );
	}

	public function test_only_a_levels_name_gets_a_dot(): void {
		$category = (object) array(
			'term_id'  => 23,
			'name'     => 'Legal Services',
			'taxonomy' => ID::TAX_CATEGORY,
		);
		$screen   = new LevelScreen();

		$this->assertSame( 'Legal Services', $screen->name( 'Legal Services', $category ) );
		$this->assertSame( 'Legal Services', $screen->name( 'Legal Services' ) );
	}

	/**
	 * WordPress escapes a term's name for the list at priority 30 (`_wp_specialchars`): a dot
	 * added before that is printed as the text "<span…".
	 */
	public function test_the_dot_is_added_after_wordpress_has_escaped_the_name(): void {
		$screen = new LevelScreen();
		$screen->hook();

		$this->assertGreaterThan( 30, has_filter( 'term_name', array( $screen, 'name' ) ) );
		$this->assertNotFalse( has_filter( 'get_terms', array( $screen, 'order' ) ) );
	}
}
