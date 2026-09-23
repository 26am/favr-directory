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
				'labels'            => array(
					'name'              => __( 'Business Categories', 'favr-directory' ),
					'singular_name'     => __( 'Business Category', 'favr-directory' ),
					'menu_name'         => __( 'Categories', 'favr-directory' ),
					'search_items'      => __( 'Search Categories', 'favr-directory' ),
					'all_items'         => __( 'All Categories', 'favr-directory' ),
					'parent_item'       => __( 'Parent Category', 'favr-directory' ),
					'parent_item_colon' => __( 'Parent Category:', 'favr-directory' ),
					'edit_item'         => __( 'Edit Category', 'favr-directory' ),
					'view_item'         => __( 'View Category', 'favr-directory' ),
					'update_item'       => __( 'Update Category', 'favr-directory' ),
					'add_new_item'      => __( 'Add New Category', 'favr-directory' ),
					'new_item_name'     => __( 'New Category Name', 'favr-directory' ),
					'not_found'         => __( 'No categories found.', 'favr-directory' ),
					'back_to_items'     => __( '← Back to Categories', 'favr-directory' ),
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
				'labels'             => array(
					'name'          => __( 'Membership Levels', 'favr-directory' ),
					'singular_name' => __( 'Membership Level', 'favr-directory' ),
					'menu_name'     => __( 'Membership Levels', 'favr-directory' ),
					'all_items'     => __( 'All Levels', 'favr-directory' ),
					'edit_item'     => __( 'Edit Level', 'favr-directory' ),
					'update_item'   => __( 'Update Level', 'favr-directory' ),
					'add_new_item'  => __( 'Add New Level', 'favr-directory' ),
					'new_item_name' => __( 'New Level Name', 'favr-directory' ),
					'not_found'     => __( 'No levels found.', 'favr-directory' ),
					'back_to_items' => __( '← Back to Levels', 'favr-directory' ),
				),
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

	/** The business post type. */
	public function registerPostType(): void {
		register_post_type(
			ID::POST_TYPE,
			array(
				'labels'          => array(
					'name'                  => __( 'Businesses', 'favr-directory' ),
					'singular_name'         => __( 'Business', 'favr-directory' ),
					'menu_name'             => __( 'Directory', 'favr-directory' ),
					'all_items'             => __( 'All Businesses', 'favr-directory' ),
					'add_new'               => __( 'Add Business', 'favr-directory' ),
					'add_new_item'          => __( 'Add New Business', 'favr-directory' ),
					'edit_item'             => __( 'Edit Business', 'favr-directory' ),
					'new_item'              => __( 'New Business', 'favr-directory' ),
					'view_item'             => __( 'View Business', 'favr-directory' ),
					'view_items'            => __( 'View Directory', 'favr-directory' ),
					'search_items'          => __( 'Search Businesses', 'favr-directory' ),
					'not_found'             => __( 'No businesses found.', 'favr-directory' ),
					'not_found_in_trash'    => __( 'No businesses found in Trash.', 'favr-directory' ),
					'featured_image'        => __( 'Cover photo', 'favr-directory' ),
					'set_featured_image'    => __( 'Set cover photo', 'favr-directory' ),
					'remove_featured_image' => __( 'Remove cover photo', 'favr-directory' ),
					'use_featured_image'    => __( 'Use as cover photo', 'favr-directory' ),
					'archives'              => __( 'Business Directory', 'favr-directory' ),
					'item_published'        => __( 'Business published.', 'favr-directory' ),
					'item_updated'          => __( 'Business updated.', 'favr-directory' ),
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
