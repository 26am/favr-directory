<?php
/**
 * The words staff read in the block editor: the three blocks' names and help, and the labels of
 * their settings. "Listing", in sentence case; the names saved pages hold never change.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

final class BlockWordsTest extends TestCase {

	/**
	 * A block's block.json.
	 *
	 * @param string $folder Folder under blocks/.
	 * @return array<string, mixed>
	 */
	private function block( string $folder ): array {
		return json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/blocks/' . $folder . '/block.json' ), true, 16, JSON_THROW_ON_ERROR );
	}

	public function test_the_blocks_are_named_in_listing_words(): void {
		$this->assertSame( 'Directory', $this->block( 'directory' )['title'] );
		$this->assertSame( 'Listing profile', $this->block( 'business-profile' )['title'] );
		$this->assertSame( 'My listing (edit form)', $this->block( 'my-listing' )['title'] );
	}

	public function test_the_names_pages_were_built_with_stay(): void {
		// WordPress stores a block in a page by this name; a renamed block becomes "unsupported" everywhere.
		$this->assertSame( 'favr-directory/directory', $this->block( 'directory' )['name'] );
		$this->assertSame( 'favr-directory/business-profile', $this->block( 'business-profile' )['name'] );
		$this->assertSame( 'favr-directory/my-listing', $this->block( 'my-listing' )['name'] );
	}

	public function test_nobody_reads_business_in_a_block_s_name_or_help(): void {
		foreach ( array( 'directory', 'business-profile', 'my-listing' ) as $folder ) {
			$block = $this->block( $folder );
			$this->assertDoesNotMatchRegularExpression( '/business/i', $block['title'] . ' ' . $block['description'], $folder );
			// Still found by people who type the old word into the block search.
			$this->assertContains( 'business', $block['keywords'], $folder );
			$this->assertContains( 'listing', $block['keywords'], $folder );
		}
	}

	public function test_nobody_reads_business_in_a_block_s_settings(): void {
		$script = (string) file_get_contents( dirname( __DIR__, 2 ) . '/assets/blocks/editor.js' );
		$this->assertSame( 1, preg_match_all( "/__\\(\\s*'((?:[^'\\\\]|\\\\.)*)'/", $script, $strings ) > 0 ? 1 : 0 );
		foreach ( $strings[1] as $text ) {
			$this->assertDoesNotMatchRegularExpression( '/business/i', $text );
		}
		$this->assertContains( 'Featured listings only', $strings[1] );
		$this->assertContains( 'Listings per page', $strings[1] );
		$this->assertContains( 'Listing profile', $strings[1] );
	}
}
