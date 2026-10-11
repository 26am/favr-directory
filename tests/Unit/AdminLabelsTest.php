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

	/**
	 * The words for membership levels, all of them. Favr Members registers the same taxonomy when
	 * it runs without the Directory, and its tests/Unit/AdminWordsTest.php holds this same list:
	 * change one and the other must follow, or the Membership levels screen reads differently
	 * depending on which plugins a site runs.
	 */
	private const LEVEL_WORDS = array(
		'name'                  => 'Membership levels',
		'singular_name'         => 'Membership level',
		'menu_name'             => 'Membership levels',
		'search_items'          => 'Search levels',
		'all_items'             => 'All levels',
		'parent_item'           => 'Parent level',
		'parent_item_colon'     => 'Parent level:',
		'edit_item'             => 'Edit level',
		'view_item'             => 'View level',
		'update_item'           => 'Update level',
		'add_new_item'          => 'Add level',
		'new_item_name'         => 'New level name',
		'not_found'             => 'No levels found.',
		'no_terms'              => 'No levels',
		'filter_by_item'        => 'Filter by level',
		'items_list_navigation' => 'Levels list navigation',
		'items_list'            => 'Levels list',
		'most_used'             => 'Most used',
		'back_to_items'         => '← All levels',
		'item_link'             => 'Level link',
		'item_link_description' => 'A link to a level.',
	);

	/**
	 * Every label WordPress gives a hierarchical taxonomy in its category wording when the plugin
	 * leaves it out ("Search Categories", "Parent Category", "Most Used", "← Go to Categories",
	 * "Category Link"): WP_Taxonomy::get_default_labels().
	 */
	private const WORDPRESS_CATEGORY_WORDING = array(
		'name',
		'singular_name',
		'search_items',
		'all_items',
		'parent_item',
		'parent_item_colon',
		'edit_item',
		'view_item',
		'update_item',
		'add_new_item',
		'new_item_name',
		'not_found',
		'no_terms',
		'filter_by_item',
		'items_list_navigation',
		'items_list',
		'most_used',
		'back_to_items',
		'item_link',
		'item_link_description',
	);

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

	public function test_levels_use_the_words_shared_with_favr_members(): void {
		$this->assertSame( self::LEVEL_WORDS, Registrar::levelLabels() );

		$this->register();
		$this->assertSame( self::LEVEL_WORDS, $this->taxonomies[ ID::TAX_LEVEL ]['labels'], 'The taxonomy is registered with those words.' );
	}

	/**
	 * Levels are a hierarchical taxonomy, so every label left out is filled in by WordPress with
	 * its category wording.
	 */
	public function test_no_level_label_is_left_to_say_category_or_tag(): void {
		$labels = Registrar::levelLabels();
		foreach ( self::WORDPRESS_CATEGORY_WORDING as $key ) {
			$this->assertArrayHasKey( $key, $labels, "WordPress would fill in “{$key}” with its category wording." );
			$this->assertDoesNotMatchRegularExpression( '/categor|\\btags?\\b/i', $labels[ $key ], $key );
		}
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
