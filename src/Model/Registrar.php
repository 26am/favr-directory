<?php
/**
 * Registers the post type, taxonomies and meta.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Model;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Vendor\FavrCore\Fields\Sanitizer;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;

/**
 * Data model registration. Runs in every context (front end, admin, REST, CLI).
 */
final class Registrar {

	/** Hook everything. */
	public function hook(): void {
		add_action( 'init', array( $this, 'registerTaxonomies' ), 5 );
		add_action( 'init', array( $this, 'registerPostType' ), 6 );
		add_action( 'init', array( $this, 'registerMeta' ), 7 );
		add_action( 'init', array( $this, 'maybeFlushRewrites' ), 99 );
	}

	/**
	 * Taxonomies are registered BEFORE the post type so their rewrite rules
	 * (directory/category/…) are matched before the post type's single rules.
	 */
	public function registerTaxonomies(): void {
		$base = self::directorySlug();

		register_taxonomy(
			ID::TAX_CATEGORY,
			ID::POST_TYPE,
			array(
				// Staff words (sentence case). The public pages print the category's own name, never these.
				'labels'            => array(
					'name'                  => __( 'Directory categories', 'favr-directory' ),
					'singular_name'         => __( 'Category', 'favr-directory' ),
					'menu_name'             => __( 'Categories', 'favr-directory' ),
					'search_items'          => __( 'Search categories', 'favr-directory' ),
					'all_items'             => __( 'All categories', 'favr-directory' ),
					'parent_item'           => __( 'Parent category', 'favr-directory' ),
					'parent_item_colon'     => __( 'Parent category:', 'favr-directory' ),
					'edit_item'             => __( 'Edit category', 'favr-directory' ),
					'view_item'             => __( 'View category', 'favr-directory' ),
					'update_item'           => __( 'Update category', 'favr-directory' ),
					'add_new_item'          => __( 'Add category', 'favr-directory' ),
					'new_item_name'         => __( 'New category name', 'favr-directory' ),
					'not_found'             => __( 'No categories found.', 'favr-directory' ),
					'no_terms'              => __( 'No categories', 'favr-directory' ),
					'filter_by_item'        => __( 'Filter by category', 'favr-directory' ),
					'items_list_navigation' => __( 'Categories list navigation', 'favr-directory' ),
					'items_list'            => __( 'Categories list', 'favr-directory' ),
					'most_used'             => __( 'Most used', 'favr-directory' ),
					'back_to_items'         => __( '← All categories', 'favr-directory' ),
					'item_link'             => __( 'Category link', 'favr-directory' ),
					'item_link_description' => __( 'A link to a category.', 'favr-directory' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rest_base'         => 'business-categories',
				'rewrite'           => array(
					'slug'         => $base . '/' . sanitize_title( (string) Settings::get( 'category_slug' ) ),
					'with_front'   => false,
					'hierarchical' => true,
				),
				'capabilities'      => array(
					'manage_terms' => ID::CAP_MANAGE_TERMS,
					'edit_terms'   => ID::CAP_MANAGE_TERMS,
					'delete_terms' => ID::CAP_MANAGE_TERMS,
					'assign_terms' => 'edit_' . ID::CAP_TYPE_PLURAL,
				),
			)
		);

		register_taxonomy(
			ID::TAX_LEVEL,
			ID::POST_TYPE,
			array(
				'labels'             => self::levelLabels(),
				'hierarchical'       => true,
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_admin_column'  => true,
				'show_in_rest'       => true,
				'rest_base'          => 'membership-levels',
				'rewrite'            => false,
				'meta_box_cb'        => false, // Replaced by a single-choice box in the editor.
				'capabilities'       => array(
					'manage_terms' => ID::CAP_MANAGE_TERMS,
					'edit_terms'   => ID::CAP_MANAGE_TERMS,
					'delete_terms' => ID::CAP_MANAGE_TERMS,
					'assign_terms' => 'edit_' . ID::CAP_TYPE_PLURAL,
				),
			)
		);
	}

	/**
	 * The words for membership levels: label for label the ones Favr Members registers for this
	 * shared taxonomy when it runs without the Directory (its Registrar::levelLabels()), so the
	 * screens read alike whichever plugin registers it. All of them are spelled out: levels are
	 * hierarchical, and what is left out WordPress fills in with its category wording ("Search
	 * Categories", "Parent Category", "Most Used", "Category Link"). tests/Unit/AdminLabelsTest.php
	 * holds the list; Favr Members' tests hold the same one.
	 *
	 * @return array<string, string>
	 */
	public static function levelLabels(): array {
		return array(
			'name'                  => __( 'Membership levels', 'favr-directory' ),
			'singular_name'         => __( 'Membership level', 'favr-directory' ),
			'menu_name'             => __( 'Membership levels', 'favr-directory' ),
			'search_items'          => __( 'Search levels', 'favr-directory' ),
			'all_items'             => __( 'All levels', 'favr-directory' ),
			'parent_item'           => __( 'Parent level', 'favr-directory' ),
			'parent_item_colon'     => __( 'Parent level:', 'favr-directory' ),
			'edit_item'             => __( 'Edit level', 'favr-directory' ),
			'view_item'             => __( 'View level', 'favr-directory' ),
			'update_item'           => __( 'Update level', 'favr-directory' ),
			'add_new_item'          => __( 'Add level', 'favr-directory' ),
			'new_item_name'         => __( 'New level name', 'favr-directory' ),
			'not_found'             => __( 'No levels found.', 'favr-directory' ),
			'no_terms'              => __( 'No levels', 'favr-directory' ),
			'filter_by_item'        => __( 'Filter by level', 'favr-directory' ),
			'items_list_navigation' => __( 'Levels list navigation', 'favr-directory' ),
			'items_list'            => __( 'Levels list', 'favr-directory' ),
			'most_used'             => __( 'Most used', 'favr-directory' ),
			'back_to_items'         => __( '← All levels', 'favr-directory' ),
			'item_link'             => __( 'Level link', 'favr-directory' ),
			'item_link_description' => __( 'A link to a level.', 'favr-directory' ),
		);
	}

	/**
	 * Capitalized noun for the public labels.
	 *
	 * @param bool $plural Plural.
	 */
	private static function ucNoun( bool $plural ): string {
		$noun = \FavrDirectory\Support\Settings::noun( $plural );
		return mb_strtoupper( mb_substr( $noun, 0, 1 ) ) . mb_substr( $noun, 1 );
	}

	/** Register the listing post type. */
	public function registerPostType(): void {
		register_post_type(
			ID::POST_TYPE,
			array(
				'labels'          => array(
					// Public-facing names follow the directory's nouns ("Members" for a bar association):
					// REST, nav menus, SEO plugins and page builders read them.
					'name'                     => self::ucNoun( true ),
					'singular_name'            => self::ucNoun( false ),
					'archives'                 => __( 'Business Directory', 'favr-directory' ),
					// Staff words: always "listing", whatever the directory lists, in sentence case.
					// The list screen's heading is set in Admin\ListScreen::titleScreen().
					'menu_name'                => __( 'Directory', 'favr-directory' ),
					// Not "Listing" alone: the toolbar's New menu has no "Directory" above it.
					'name_admin_bar'           => __( 'Directory listing', 'favr-directory' ),
					'all_items'                => __( 'All listings', 'favr-directory' ),
					'add_new'                  => __( 'Add listing', 'favr-directory' ),
					'add_new_item'             => __( 'Add listing', 'favr-directory' ),
					'edit_item'                => __( 'Edit listing', 'favr-directory' ),
					'new_item'                 => __( 'New listing', 'favr-directory' ),
					'view_item'                => __( 'View listing', 'favr-directory' ),
					'view_items'               => __( 'View directory', 'favr-directory' ),
					'search_items'             => __( 'Search listings', 'favr-directory' ),
					'not_found'                => __( 'No listings found.', 'favr-directory' ),
					'not_found_in_trash'       => __( 'No listings found in Trash.', 'favr-directory' ),
					'featured_image'           => __( 'Cover photo', 'favr-directory' ),
					'set_featured_image'       => __( 'Set cover photo', 'favr-directory' ),
					'remove_featured_image'    => __( 'Remove cover photo', 'favr-directory' ),
					'use_featured_image'       => __( 'Use as cover photo', 'favr-directory' ),
					'attributes'               => __( 'Listing attributes', 'favr-directory' ),
					'insert_into_item'         => __( 'Insert into listing', 'favr-directory' ),
					'uploaded_to_this_item'    => __( 'Uploaded to this listing', 'favr-directory' ),
					'filter_items_list'        => __( 'Filter listings list', 'favr-directory' ),
					'items_list_navigation'    => __( 'Listings list navigation', 'favr-directory' ),
					'items_list'               => __( 'Listings list', 'favr-directory' ),
					'item_published'           => __( 'Listing published.', 'favr-directory' ),
					'item_published_privately' => __( 'Listing published privately.', 'favr-directory' ),
					'item_reverted_to_draft'   => __( 'Listing reverted to draft.', 'favr-directory' ),
					'item_trashed'             => __( 'Listing trashed.', 'favr-directory' ),
					'item_scheduled'           => __( 'Listing scheduled.', 'favr-directory' ),
					'item_updated'             => __( 'Listing updated.', 'favr-directory' ),
					'item_link'                => __( 'Listing link', 'favr-directory' ),
					'item_link_description'    => __( 'A link to a listing.', 'favr-directory' ),
				),
				'public'          => true,
				'show_in_rest'    => true,
				'rest_base'       => 'businesses',
				'menu_icon'       => 'dashicons-store',
				'menu_position'   => 25,
				'has_archive'     => self::directorySlug(),
				'rewrite'         => array(
					'slug'       => self::directorySlug(),
					'with_front' => false,
				),
				'supports'        => array( 'title', 'editor', 'thumbnail', 'excerpt', 'custom-fields', 'revisions', 'author' ),
				'capability_type' => array( ID::CAP_TYPE, ID::CAP_TYPE_PLURAL ),
				'map_meta_cap'    => true,
				'taxonomies'      => array( ID::TAX_CATEGORY, ID::TAX_LEVEL ),
				'template'        => array(),
			)
		);
	}

	/**
	 * Register every field as post meta so it is typed, sanitized and (unless private)
	 * available in the REST API and to page builders' dynamic-data features.
	 */
	public function registerMeta(): void {
		foreach ( FieldRegistry::all() as $field ) {
			register_post_meta(
				ID::POST_TYPE,
				ID::meta( $field['id'] ),
				array(
					'type'              => self::metaType( $field ),
					'single'            => true,
					'default'           => self::metaDefault( $field ),
					'show_in_rest'      => $field['private'] ? false : self::restSchema( $field ),
					'sanitize_callback' => static fn( $value ) => Sanitizer::sanitize( $field, $value ),
					'auth_callback'     => static fn( $allowed, $meta_key, $post_id ): bool => current_user_can( 'edit_post', (int) $post_id ),
				)
			);
		}
	}

	/** Flush rewrite rules once after activation or a slug change. */
	public function maybeFlushRewrites(): void {
		if ( get_option( ID::OPTION_FLUSH ) ) {
			delete_option( ID::OPTION_FLUSH );
			flush_rewrite_rules( false );
		}
	}

	/** The directory base slug. */
	public static function directorySlug(): string {
		$slug = sanitize_title( (string) Settings::get( 'directory_slug' ) );
		return '' === $slug ? 'directory' : $slug;
	}

	/**
	 * Meta type for a field.
	 *
	 * @param array<string, mixed> $field Field.
	 */
	private static function metaType( array $field ): string {
		switch ( $field['type'] ) {
			case 'number':
			case 'image':
				return 'integer';
			case 'gallery':
			case 'checkboxes':
			case 'repeater':
				return 'array';
			case 'hours':
				return 'object';
			default:
				return 'string';
		}
	}

	/**
	 * Default returned by get_post_meta when unset.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return mixed
	 */
	private static function metaDefault( array $field ) {
		switch ( self::metaType( $field ) ) {
			case 'integer':
				return 0;
			case 'array':
			case 'object':
				return array();
			default:
				return (string) $field['default'];
		}
	}

	/**
	 * REST schema for a field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return array<string, mixed>
	 */
	private static function restSchema( array $field ): array {
		$type = self::metaType( $field );
		if ( 'array' === $type ) {
			$items = 'gallery' === $field['type']
				? array( 'type' => 'integer' )
				: ( 'repeater' === $field['type'] ? array(
					'type'                 => 'object',
					'additionalProperties' => true,
				) : array( 'type' => 'string' ) );
			return array( 'schema' => array( 'items' => $items ) );
		}
		if ( 'object' === $type ) {
			return array(
				'schema' => array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'       => 'object',
						'properties' => array(
							'status' => array( 'type' => 'string' ),
							'open'   => array( 'type' => 'string' ),
							'close'  => array( 'type' => 'string' ),
						),
					),
				),
			);
		}
		return array( 'schema' => array( 'description' => (string) $field['label'] ) );
	}
}
