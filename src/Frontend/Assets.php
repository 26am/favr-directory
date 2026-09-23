<?php
/**
 * Front-end assets.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\AssetVersion;
use FavrDirectory\Support\Settings;

/**
 * Registers the stylesheet/script everywhere but only enqueues them where the directory
 * actually renders: early on directory URLs and pages containing the block/shortcode (no
 * flash of unstyled content), and lazily from any renderer as a fallback.
 */
final class Assets {

	public const HANDLE = 'favr-directory';

	/** Hook. */
	public function hook(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ), 5 );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybeEnqueueEarly' ), 20 );
		add_action( 'enqueue_block_assets', array( $this, 'editorStyles' ) );
	}

	/** Register handles. */
	public function register(): void {
		if ( wp_style_is( self::HANDLE, 'registered' ) ) {
			return;
		}
		wp_register_style( self::HANDLE, FAVR_DIRECTORY_URL . 'assets/public/directory.css', array(), AssetVersion::of( 'assets/public/directory.css' ) );
		wp_add_inline_style( self::HANDLE, self::inlineCss() );
		wp_register_script(
			self::HANDLE,
			FAVR_DIRECTORY_URL . 'assets/public/directory.js',
			array(),
			AssetVersion::of( 'assets/public/directory.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			self::HANDLE,
			'favrDirectoryPublic',
			array(
				'i18n' => array(
					'loading' => __( 'Loading…', 'favr-directory' ),
					'close'   => __( 'Close', 'favr-directory' ),
					'prev'    => __( 'Previous photo', 'favr-directory' ),
					'next'    => __( 'Next photo', 'favr-directory' ),
					'copied'  => __( 'Copied!', 'favr-directory' ),
					'photos'  => __( 'Photos', 'favr-directory' ),
				),
			)
		);
	}

	/** Enqueue (safe to call from renderers at any point). */
	public static function enqueue(): void {
		if ( ! wp_style_is( self::HANDLE, 'registered' ) ) {
			( new self() )->register();
		}
		wp_enqueue_style( self::HANDLE );
		wp_enqueue_script( self::HANDLE );
	}

	/** Enqueue in <head> on pages we know render the directory. */
	public function maybeEnqueueEarly(): void {
		if ( is_singular( ID::POST_TYPE ) || is_post_type_archive( ID::POST_TYPE ) || is_tax( ID::TAX_CATEGORY ) ) {
			self::enqueue();
			return;
		}
		$post = get_post();
		if ( is_singular() && $post && (
			has_block( ID::BLOCK_DIRECTORY, $post )
			|| has_block( 'favr-directory/business-profile', $post )
			|| has_shortcode( $post->post_content, ID::SHORTCODE_DIR )
			|| has_shortcode( $post->post_content, ID::SHORTCODE_PROFILE )
		) ) {
			self::enqueue();
		}
	}

	/** Styles inside the block editor so previews match the front end. */
	public function editorStyles(): void {
		if ( is_admin() ) {
			$this->register();
			wp_enqueue_style( self::HANDLE );
		}
	}

	/** Accent color custom property. */
	private static function inlineCss(): string {
		$accent = (string) Settings::get( 'accent_color' );
		if ( '' === $accent || ! sanitize_hex_color( $accent ) ) {
			return '';
		}
		// A root variable, so a per-widget --favr-brand (Elementor) can still override it.
		return ':root{--favr-directory-accent:' . $accent . ';}';
	}
}
