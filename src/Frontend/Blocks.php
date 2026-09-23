<?php
/**
 * Dynamic blocks.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Model\Business;

/**
 * Two server-rendered blocks (metadata in /blocks/*, no build step):
 *  - favr-directory/directory         the searchable listing (block twin of [favr_directory])
 *  - favr-directory/business-profile  the full profile of the current business (used by the
 *                                     single-business block template)
 */
final class Blocks {

	/** Hook. */
	public function hook(): void {
		add_action( 'init', array( $this, 'register' ) );
	}

	/** Register block types from block.json. */
	public function register(): void {
		wp_register_script(
			'favr-directory-blocks',
			FAVR_DIRECTORY_URL . 'assets/blocks/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n', 'wp-data', 'wp-core-data' ),
			\FavrDirectory\Support\AssetVersion::of( 'assets/blocks/editor.js' ),
			true
		);
		wp_set_script_translations( 'favr-directory-blocks', 'favr-directory', FAVR_DIRECTORY_PATH . 'languages' );

		// block.json references the front-end style handle, so it must exist before registration.
		( new Assets() )->register();

		register_block_type(
			FAVR_DIRECTORY_PATH . 'blocks/directory',
			array( 'render_callback' => array( $this, 'renderDirectory' ) )
		);
		register_block_type(
			FAVR_DIRECTORY_PATH . 'blocks/business-profile',
			array( 'render_callback' => array( $this, 'renderProfile' ) )
		);
	}

	/**
	 * Directory block.
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 */
	public function renderDirectory( array $attributes ): string {
		// Map only attributes that are set, so unset ones fall back to directory settings.
		$map  = array(
			'perPage'         => 'per_page',
			'category'        => 'category',
			'level'           => 'level',
			'featuredOnly'    => 'featured',
			'showSearch'      => 'search',
			'showLetters'     => 'letters',
			'showLevelFilter' => 'level_filter',
			'layout'          => 'layout',
			'order'           => 'order',
			'title'           => 'title',
			'showPagination'  => 'pagination',
		);
		$atts = array();
		foreach ( $map as $attribute => $key ) {
			if ( isset( $attributes[ $attribute ] ) && '' !== $attributes[ $attribute ] ) {
				$atts[ $key ] = $attributes[ $attribute ];
			}
		}
		$html = Directory::render( $atts );
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), $html );
	}

	/**
	 * Profile block (current post).
	 *
	 * @param array<string, mixed> $attributes Block attributes.
	 * @param string               $content    Inner content.
	 * @param \WP_Block|null       $block      Block instance.
	 */
	public function renderProfile( array $attributes, string $content = '', $block = null ): string {
		$post_id  = $block instanceof \WP_Block && isset( $block->context['postId'] ) ? (int) $block->context['postId'] : (int) get_the_ID();
		$business = Business::find( $post_id );
		if ( ! $business || ! Shortcodes::visible( $business ) || Profile::isRendering() ) {
			return '';
		}
		return sprintf( '<div %s>%s</div>', get_block_wrapper_attributes(), Profile::render( $business, 'page' ) );
	}
}
