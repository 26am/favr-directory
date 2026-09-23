<?php
/**
 * Single business profile.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;

/**
 * Renders the full profile. Two contexts:
 *  - "page":    our own single templates (block + classic). Full hero with name and cover.
 *  - "content": injected via the_content when a theme or page builder (e.g. Elementor) owns
 *               the single template. The theme already prints the title and featured image,
 *               so the hero omits them.
 */
final class Profile {

	/**
	 * Reentrancy guard: the description itself runs through the_content.
	 *
	 * @var bool
	 */
	private static bool $rendering = false;

	/** Hook the content filter. */
	public function hook(): void {
		add_filter( 'the_content', array( $this, 'filterContent' ), 20 );
	}

	/**
	 * Render a profile.
	 *
	 * @param Business $business Business.
	 * @param string   $context  page|content.
	 */
	public static function render( Business $business, string $context = 'page' ): string {
		Assets::enqueue();
		self::$rendering = true;
		try {
			$description = apply_filters( 'the_content', $business->post()->post_content ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook.
			$html        = Template::render(
				'profile',
				array(
					'business'    => $business,
					'context'     => $context,
					'description' => str_replace( ']]>', ']]&gt;', (string) $description ),
				)
			);
		} finally {
			self::$rendering = false;
		}
		return $html;
	}

	/** Whether a profile is currently rendering. */
	public static function isRendering(): bool {
		return self::$rendering;
	}

	/**
	 * Replace a business's content with the full profile on its own page.
	 *
	 * @param string $content Content.
	 */
	public function filterContent( string $content ): string {
		if ( self::$rendering || ! is_singular( ID::POST_TYPE ) || is_feed() || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || ID::POST_TYPE !== $post->post_type || (int) get_queried_object_id() !== (int) $post->ID ) {
			return $content;
		}
		/**
		 * Whether to replace the business content with the full profile.
		 *
		 * @param bool     $enabled Default true.
		 * @param \WP_Post $post    Business.
		 */
		if ( ! apply_filters( 'favr_directory_filter_content', true, $post ) ) {
			return $content;
		}
		// Our own templates render the profile directly (block / classic template), so reaching
		// here means a theme or page builder owns the page: it prints the title and image.
		return self::render( new Business( $post ), 'content' );
	}
}
