<?php
/**
 * The words staff read in wp-admin: "listing/listings", sentence case, whatever the directory lists.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Model\Registrar;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;

final class AdminLabelsTest extends TestCase {

	/** Labels visitors, REST clients and page builders read: they follow the directory's own noun. */
	private const PUBLIC_LABELS = array( 'name', 'singular_name', 'archives' );

	/** Words that keep their capital inside a sentence-case label. */
	private const PROPER_NOUNS = array( 'Trash' );

	/** @var array<string, mixed> */
	private array $option = array();

	/** @var array<string, array<string, mixed>> */
	private array $types = array();

	/** @var array<string, array<string, mixed>> */
	private array $taxonomies = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->alias( fn( $key, $default = false ) => ID::OPTION_SETTINGS === $key ? $this->option : $default );
		Functions\when( 'sanitize_title' )->alias( static fn( $value ) => strtolower( trim( (string) $value ) ) );
		Functions\when( 'register_post_type' )->alias(
			function ( $type, $args ): void {
				$this->types[ $type ] = $args;
			}
		);
		Functions\when( 'register_taxonomy' )->alias(
			function ( $taxonomy, $object_type, $args ): void {
				$this->taxonomies[ $taxonomy ] = $args;
			}
		);
		Settings::flush();
	}

	protected function tearDown(): void {
		Settings::flush();
		parent::tearDown();
	}

	/**
	 * Register everything for a directory with these settings.
	 *
	 * @param array<string, mixed> $settings Settings.
	 */
	private function register( array $settings = array() ): void {
		$this->option = $settings;
		Settings::flush();
		$registrar = new Registrar();
		$registrar->registerTaxonomies();
		$registrar->registerPostType();
	}

	/** @return array<string, string> */
	private function listingLabels(): array {
		return $this->types[ ID::POST_TYPE ]['labels'];
	}

	public function test_the_directory_menu_and_screens_say_listing(): void {
		$this->register( array( 'listing_kind' => 'person' ) );
		$labels = $this->listingLabels();

		$this->assertSame( 'Directory', $labels['menu_name'] );
		$this->assertSame( 'All listings', $labels['all_items'] );
		$this->assertSame( 'Add listing', $labels['add_new'] );
		$this->assertSame( 'Add listing', $labels['add_new_item'] );
		$this->assertSame( 'Edit listing', $labels['edit_item'] );
		$this->assertSame( 'New listing', $labels['new_item'] );
		$this->assertSame( 'View listing', $labels['view_item'] );
		$this->assertSame( 'View directory', $labels['view_items'] );
		$this->assertSame( 'Search listings', $labels['search_items'] );
		$this->assertSame( 'No listings found.', $labels['not_found'] );
		$this->assertSame( 'No listings found in Trash.', $labels['not_found_in_trash'] );
		$this->assertSame( 'Listing published.', $labels['item_published'] );
		$this->assertSame( 'Listing updated.', $labels['item_updated'] );
	}

	public function test_the_toolbar_new_menu_never_says_member(): void {
		// Without this WordPress falls back to the public noun, and "Member" is Favr Members' entry.
		$this->register( array( 'listing_kind' => 'person' ) );
		$this->assertSame( 'Directory listing', $this->listingLabels()['name_admin_bar'] );
	}

	public function test_the_public_name_still_follows_the_directory_noun(): void {
		$this->register();
		$this->assertSame( 'Businesses', $this->listingLabels()['name'] );
		$this->assertSame( 'Business', $this->listingLabels()['singular_name'] );

		$this->register( array( 'listing_kind' => 'person' ) );
		$this->assertSame( 'Members', $this->listingLabels()['name'] );
		$this->assertSame( 'Member', $this->listingLabels()['singular_name'] );

		$this->register(
			array(
				'listing_kind'  => 'person',
				'noun_singular' => 'attorney',
				'noun_plural'   => 'attorneys',
			)
		);
		$this->assertSame( 'Attorneys', $this->listingLabels()['name'] );
		$this->assertSame( 'Attorney', $this->listingLabels()['singular_name'] );
	}

	public function test_addresses_do_not_move(): void {
		$this->register( array( 'listing_kind' => 'person' ) );
		$this->assertSame( 'businesses', $this->types[ ID::POST_TYPE ]['rest_base'] );
		$this->assertSame( 'directory', $this->types[ ID::POST_TYPE ]['has_archive'] );
		$this->assertSame( 'business-categories', $this->taxonomies[ ID::TAX_CATEGORY ]['rest_base'] );
		$this->assertSame( 'directory/category', $this->taxonomies[ ID::TAX_CATEGORY ]['rewrite']['slug'] );
		$this->assertSame( 'membership-levels', $this->taxonomies[ ID::TAX_LEVEL ]['rest_base'] );
	}

	public function test_categories_are_directory_categories(): void {
		$this->register();
		$labels = $this->taxonomies[ ID::TAX_CATEGORY ]['labels'];

		$this->assertSame( 'Directory categories', $labels['name'] );
		$this->assertSame( 'Category', $labels['singular_name'] );
		$this->assertSame( 'Categories', $labels['menu_name'] );
		$this->assertSame( 'All categories', $labels['all_items'] );
		$this->assertSame( 'Add category', $labels['add_new_item'] );
		$this->assertSame( 'Edit category', $labels['edit_item'] );
		$this->assertSame( 'Search categories', $labels['search_items'] );
		$this->assertSame( 'No categories found.', $labels['not_found'] );
	}

	public function test_levels_follow_the_same_style(): void {
		$this->register();
		$labels = $this->taxonomies[ ID::TAX_LEVEL ]['labels'];

		$this->assertSame( 'Membership levels', $labels['name'] );
		$this->assertSame( 'Membership level', $labels['singular_name'] );
		$this->assertSame( 'All levels', $labels['all_items'] );
		$this->assertSame( 'Add level', $labels['add_new_item'] );
		$this->assertSame( 'Edit level', $labels['edit_item'] );
		$this->assertSame( 'Search levels', $labels['search_items'] );
		$this->assertSame( 'No levels found.', $labels['not_found'] );
		// A hierarchical taxonomy without these falls back to WordPress's "Parent Category" and "No categories".
		$this->assertSame( 'Parent level', $labels['parent_item'] );
		$this->assertSame( 'No levels', $labels['no_terms'] );
	}

	public function test_no_admin_label_says_business_or_uses_title_case(): void {
		$this->register(); // A business directory: the public noun is "business", the admin word is still "listing".

		$sets = array(
			'listing'  => array_diff_key( $this->listingLabels(), array_flip( self::PUBLIC_LABELS ) ),
			'category' => $this->taxonomies[ ID::TAX_CATEGORY ]['labels'],
			'level'    => $this->taxonomies[ ID::TAX_LEVEL ]['labels'],
		);
		foreach ( $sets as $set => $labels ) {
			foreach ( $labels as $key => $label ) {
				$this->assertDoesNotMatchRegularExpression( '/business/i', $label, "$set.$key" );
				$this->assertStringNotContainsString( 'Add New', $label, "$set.$key" );

				$words = preg_split( '/\s+/', ltrim( $label, '← ' ) );
				foreach ( array_slice( $words, 1 ) as $word ) {
					if ( in_array( rtrim( $word, '.:' ), self::PROPER_NOUNS, true ) ) {
						continue;
					}
					$this->assertDoesNotMatchRegularExpression( '/^\p{Lu}/u', $word, "$set.$key is not sentence case: “{$label}”" );
				}
			}
		}
	}
}
