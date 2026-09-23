<?php
/**
 * Block (FSE) theme templates.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;

/**
 * Registers single/archive/category templates for block themes (WP 6.7+). They are more
 * specific than a theme's generic templates, so they apply with zero configuration, yet
 * remain editable in the Site Editor and overridable by a theme template of the same slug.
 */
final class BlockTemplates {

	/** Hook. */
	public function hook(): void {
		add_action( 'init', array( $this, 'register' ), 20 );
	}

	/** Register templates. */
	public function register(): void {
		if ( ! function_exists( 'register_block_template' ) ) {
			return;
		}
		$this->registerOne(
			'single-' . ID::POST_TYPE,
			__( 'Single Business', 'favr-directory' ),
			__( 'Displays a business profile from the directory.', 'favr-directory' ),
			$this->single()
		);
		$this->registerOne(
			'archive-' . ID::POST_TYPE,
			__( 'Business Directory', 'favr-directory' ),
			__( 'The searchable business directory.', 'favr-directory' ),
			$this->archive( true )
		);
		$this->registerOne(
			'taxonomy-' . ID::TAX_CATEGORY,
			__( 'Business Category', 'favr-directory' ),
			__( 'Businesses in one directory category.', 'favr-directory' ),
			$this->archive( false )
		);
	}

	/**
	 * Register one template (idempotent).
	 *
	 * @param string $slug        Template slug.
	 * @param string $title       Title.
	 * @param string $description Description.
	 * @param string $content     Block markup.
	 */
	private function registerOne( string $slug, string $title, string $description, string $content ): void {
		$name = ID::TEMPLATE_NAMESPACE . '//' . $slug;
		if ( class_exists( '\WP_Block_Templates_Registry' ) && \WP_Block_Templates_Registry::get_instance()->is_registered( $name ) ) {
			return;
		}
		register_block_template(
			$name,
			array(
				'title'       => $title,
				'description' => $description,
				'content'     => $content,
				'post_types'  => array( ID::POST_TYPE ),
			)
		);
	}

	/** Single business. */
	private function single(): string {
		return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
			. '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","wideSize":"1200px"}} -->'
			. '<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60)">'
			. '<!-- wp:favr-directory/business-profile {"align":"wide"} /-->'
			. '</main><!-- /wp:group -->'
			. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
	}

	/**
	 * Directory archive / category archive.
	 *
	 * @param bool $is_main True for the main archive (title from settings).
	 */
	private function archive( bool $is_main ): string {
		$heading = $is_main
			? '<!-- wp:heading {"level":1} --><h1 class="wp-block-heading">' . esc_html( (string) Settings::get( 'directory_title' ) ) . '</h1><!-- /wp:heading -->'
			: '<!-- wp:query-title {"type":"archive","level":1,"showPrefix":false} /--><!-- wp:term-description /-->';

		return '<!-- wp:template-part {"slug":"header","tagName":"header"} /-->'
			. '<!-- wp:group {"tagName":"main","style":{"spacing":{"padding":{"top":"var:preset|spacing|50","bottom":"var:preset|spacing|60"}}},"layout":{"type":"constrained","wideSize":"1200px"}} -->'
			. '<main class="wp-block-group" style="padding-top:var(--wp--preset--spacing--50);padding-bottom:var(--wp--preset--spacing--60)">'
			. '<!-- wp:group {"align":"wide","layout":{"type":"default"}} --><div class="wp-block-group alignwide">'
			. $heading
			. '<!-- wp:favr-directory/directory /-->'
			. '</div><!-- /wp:group -->'
			. '</main><!-- /wp:group -->'
			. '<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->';
	}
}
