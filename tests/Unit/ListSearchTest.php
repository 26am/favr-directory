<?php
/**
 * The search box above the Directory list: what it reads besides the name and description.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Admin\ListScreen;
use FavrDirectory\Schema\Identifiers as ID;

final class ListSearchTest extends TestCase {

	/** What WordPress builds for one word: the name, the short text and the description. */
	private const STOCK = " AND (((wp_posts.post_title LIKE '%okafor%') OR (wp_posts.post_excerpt LIKE '%okafor%') OR (wp_posts.post_content LIKE '%okafor%'))) ";

	/** @var mixed The global the test replaces. */
	private $wpdb_before;

	protected function setUp(): void {
		parent::setUp();
		$this->wpdb_before = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb']   = new \wpdb();
		Functions\when( 'is_admin' )->justReturn( true );
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->wpdb_before;
		parent::tearDown();
	}

	/**
	 * The Directory list's own query, searching for these words.
	 *
	 * @param list<string> $terms The words, as WordPress split them.
	 */
	private function listQuery( array $terms ): \WP_Query {
		return new \WP_Query(
			array(
				'post_type'    => ID::POST_TYPE,
				's'            => implode( ' ', $terms ),
				'search_terms' => $terms,
			)
		);
	}

	/**
	 * One group of the WHERE clause per searched word.
	 *
	 * @param string $sql The search clause.
	 * @return list<string>
	 */
	private function groups( string $sql ): array {
		$this->assertStringStartsWith( ' AND (', $sql );
		$this->assertStringEndsWith( ') ', $sql );
		return explode( ') AND (', substr( $sql, 6, -2 ) );
	}

	public function test_a_word_is_also_looked_for_in_company_email_phone_and_city(): void {
		$sql = ( new ListScreen() )->search( self::STOCK, $this->listQuery( array( 'okafor' ) ) );

		$this->assertStringContainsString( "wp_posts.post_title LIKE '%okafor%'", $sql, 'The name still matches.' );
		$this->assertStringContainsString( "wp_posts.post_excerpt LIKE '%okafor%'", $sql );
		$this->assertStringContainsString( "wp_posts.post_content LIKE '%okafor%'", $sql, 'The description still matches.' );
		$this->assertStringContainsString(
			"OR EXISTS ( SELECT 1 FROM wp_postmeta favr_ls WHERE favr_ls.post_id = wp_posts.ID AND favr_ls.meta_key IN ( 'favr_organization','favr_email','favr_phone','favr_phone_alt','favr_city' ) AND favr_ls.meta_value LIKE '%okafor%' )",
			$sql
		);
	}

	public function test_every_word_has_to_be_found_somewhere_in_the_listing(): void {
		// "Rachel Tampa": the name holds one word and the city the other.
		$groups = $this->groups( ( new ListScreen() )->search( self::STOCK, $this->listQuery( array( 'Rachel', 'Tampa' ) ) ) );

		$this->assertCount( 2, $groups );
		$this->assertStringContainsString( "wp_posts.post_title LIKE '%Rachel%'", $groups[0] );
		$this->assertStringContainsString( "favr_ls.meta_value LIKE '%Rachel%'", $groups[0] );
		$this->assertStringNotContainsString( 'Tampa', $groups[0] );
		$this->assertStringContainsString( "wp_posts.post_title LIKE '%Tampa%'", $groups[1] );
		$this->assertStringContainsString( "favr_ls.meta_value LIKE '%Tampa%'", $groups[1] );
	}

	public function test_it_stays_one_query_without_a_join_so_no_listing_comes_twice(): void {
		$list = new ListScreen();
		$sql  = $list->search( self::STOCK, $this->listQuery( array( 'example' ) ) );
		$list->hook();

		// A listing whose e-mail and company both match is still one row: the fields are read in a
		// subquery of the list's own query, and nothing is joined, grouped or made DISTINCT.
		$this->assertStringNotContainsString( 'JOIN', $sql );
		$this->assertSame( 1, substr_count( $sql, 'EXISTS' ) );
		$this->assertNotFalse( has_filter( 'posts_search', array( $list, 'search' ) ) );
		foreach ( array( 'posts_join', 'posts_groupby', 'posts_distinct', 'posts_where', 'posts_request', 'posts_clauses' ) as $filter ) {
			$this->assertFalse( has_filter( $filter ), $filter );
		}
	}

	public function test_a_minus_word_leaves_out_listings_that_have_it_anywhere(): void {
		// WordPress's own rule for "-word", carried over to the four fields.
		$sql = ( new ListScreen() )->search( self::STOCK, $this->listQuery( array( '-tampa' ) ) );

		$this->assertStringContainsString( "(wp_posts.post_title NOT LIKE '%tampa%') AND (wp_posts.post_excerpt NOT LIKE '%tampa%') AND (wp_posts.post_content NOT LIKE '%tampa%')", $sql );
		$this->assertStringContainsString( "AND NOT EXISTS ( SELECT 1 FROM wp_postmeta favr_ls", $sql );
		$this->assertStringContainsString( "favr_ls.meta_value LIKE '%tampa%'", $sql );
	}

	public function test_what_is_typed_is_matched_as_text_never_as_a_pattern_or_as_sql(): void {
		$sql = ( new ListScreen() )->search( self::STOCK, $this->listQuery( array( "o'neal_50%" ) ) );

		$this->assertStringContainsString( "LIKE '%o\\'neal\\\\_50\\\\%%'", $sql );
	}

	/**
	 * @dataProvider otherQueries
	 *
	 * @param string    $search   The clause WordPress built.
	 * @param \WP_Query $query    The query.
	 * @param bool      $is_admin Whether this is an admin request.
	 */
	public function test_every_other_query_keeps_wordpress_s_search( string $search, \WP_Query $query, bool $is_admin ): void {
		Functions\when( 'is_admin' )->justReturn( $is_admin );
		$this->assertSame( $search, ( new ListScreen() )->search( $search, $query ) );
	}

	/** @return array<string, array{0: string, 1: \WP_Query, 2: bool}> */
	public static function otherQueries(): array {
		$query = static fn( string $post_type, bool $main = true, array $terms = array( 'okafor' ) ): \WP_Query => new \WP_Query(
			array(
				'post_type'    => $post_type,
				'search_terms' => $terms,
			),
			$main
		);
		return array(
			'the public directory'          => array( self::STOCK, $query( ID::POST_TYPE ), false ),
			'the Members list'              => array( self::STOCK, $query( 'favr_member' ), true ),
			'the News list'                 => array( self::STOCK, $query( 'post' ), true ),
			'a second query on the screen'  => array( self::STOCK, $query( ID::POST_TYPE, false ), true ),
			'the list when nothing is typed' => array( '', $query( ID::POST_TYPE, true, array() ), true ),
			'a search without words'        => array( self::STOCK, $query( ID::POST_TYPE, true, array() ), true ),
		);
	}
}
