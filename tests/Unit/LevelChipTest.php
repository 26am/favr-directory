<?php
/**
 * The Level column of the Directory list: WordPress's link, drawn as the level chip.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Admin\LevelChip;
use FavrDirectory\Admin\ListScreen;
use FavrDirectory\Schema\Identifiers as ID;

final class LevelChipTest extends TestCase {

	/** What WordPress prints in a taxonomy column: a link to the list filtered by that term. */
	private const LINK = '<a href="edit.php?post_type=favr_business&#038;favr_member_level=fellow">Fellow</a>';

	/** @var array<int, string> Level colours by term id. */
	private array $colors = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'esc_attr'           => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
				// WordPress's own rule: "#" and three or six hex digits, anything else is not a colour.
				'sanitize_hex_color' => static fn( $color ) => '' === $color ? '' : ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', (string) $color ) ? $color : null ),
			)
		);
		Functions\when( 'get_term_meta' )->alias(
			fn( $term_id, $key, $single = false ) => ID::TERM_META_COLOR === $key ? ( $this->colors[ (int) $term_id ] ?? '' ) : ''
		);
	}

	/**
	 * A stand-in for WP_Term.
	 *
	 * @param int    $id   Term id.
	 * @param string $name Name.
	 */
	private function level( int $id, string $name ): object {
		return (object) array(
			'term_id' => $id,
			'name'    => $name,
			'slug'    => strtolower( $name ),
		);
	}

	public function test_the_link_becomes_the_chip_with_the_levels_colour(): void {
		// The same chip Favr Members prints (span.favr-level-chip), here on the link itself.
		$this->assertSame(
			'<a class="favr-level-chip" href="edit.php?post_type=favr_business&#038;favr_member_level=fellow"><span class="favr-level__swatch" style="background:#12355b"></span>Fellow</a>',
			LevelChip::link( self::LINK, '#12355b' )
		);
	}

	public function test_it_still_links_to_the_list_filtered_by_that_level(): void {
		$chip = LevelChip::link( self::LINK, '#12355b' );
		$this->assertStringContainsString( ' href="edit.php?post_type=favr_business&#038;favr_member_level=fellow"', $chip );
		$this->assertStringEndsWith( 'Fellow</a>', $chip );
	}

	public function test_a_level_without_a_colour_has_no_swatch_element(): void {
		$this->assertSame(
			'<a class="favr-level-chip" href="edit.php?post_type=favr_business&#038;favr_member_level=fellow">Fellow</a>',
			LevelChip::link( self::LINK, '' )
		);
	}

	public function test_what_is_not_a_hex_colour_never_reaches_the_style_attribute(): void {
		$chip = LevelChip::link( self::LINK, '#12355b;background-image:url(//example.org/x.png)' );
		$this->assertStringNotContainsString( 'style=', $chip );
		$this->assertStringNotContainsString( 'favr-level__swatch', $chip );
	}

	/**
	 * @dataProvider notPlainLinks
	 *
	 * @param string $html What another plugin left in the column.
	 */
	public function test_anything_that_is_not_wordpresss_plain_link_is_left_alone( string $html ): void {
		$this->assertSame( $html, LevelChip::link( $html, '#12355b' ) );
	}

	/** @return array<string, array{0: string}> */
	public static function notPlainLinks(): array {
		return array(
			'plain text'               => array( 'Fellow' ),
			'a link with its own class' => array( '<a class="tag" href="edit.php?favr_member_level=fellow">Fellow</a>' ),
			'a link without an address' => array( '<a>Fellow</a>' ),
			'two links'                 => array( '<a href="a.php">A</a>, <a href="b.php">B</a>' ),
			'a wrapped link'            => array( '<span><a href="edit.php?favr_member_level=fellow">Fellow</a></span>' ),
		);
	}

	public function test_the_level_column_prints_one_chip_per_level(): void {
		$this->colors = array(
			23 => '#12355b',
			25 => '',
		);
		$links        = array(
			self::LINK,
			'<a href="edit.php?post_type=favr_business&#038;favr_member_level=associate">Associate</a>',
		);

		$chips = ( new ListScreen() )->levelChips( $links, ID::TAX_LEVEL, array( $this->level( 23, 'Fellow' ), $this->level( 25, 'Associate' ) ) );

		$this->assertSame(
			array(
				'<a class="favr-level-chip" href="edit.php?post_type=favr_business&#038;favr_member_level=fellow"><span class="favr-level__swatch" style="background:#12355b"></span>Fellow</a>',
				'<a class="favr-level-chip" href="edit.php?post_type=favr_business&#038;favr_member_level=associate">Associate</a>',
			),
			$chips
		);
	}

	public function test_other_taxonomy_columns_keep_their_links(): void {
		$links = array( '<a href="edit.php?post_type=favr_business&#038;favr_business_cat=legal">Legal Services</a>' );
		$this->assertSame( $links, ( new ListScreen() )->levelChips( $links, ID::TAX_CATEGORY, array( $this->level( 7, 'Legal Services' ) ) ) );
	}

	public function test_links_that_no_longer_match_their_terms_are_left_alone(): void {
		// Another plugin dropped or added a link: which colour belongs to which is no longer known.
		$links = array( self::LINK );
		$this->assertSame( $links, ( new ListScreen() )->levelChips( $links, ID::TAX_LEVEL, array( $this->level( 23, 'Fellow' ), $this->level( 25, 'Associate' ) ) ) );
		$this->assertSame( 'not a list', ( new ListScreen() )->levelChips( 'not a list', ID::TAX_LEVEL, array() ) );
	}

	public function test_the_chips_are_hooked_on_wordpresss_own_column(): void {
		$list = new ListScreen();
		$list->hook();
		$this->assertNotFalse( has_filter( 'post_column_taxonomy_links', array( $list, 'levelChips' ) ) );
	}
}
