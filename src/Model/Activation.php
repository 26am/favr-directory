<?php
/**
 * Activation, deactivation and version upgrades.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Model;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * Activation never touches existing listings; it only (re)installs capabilities, seeds the
 * default membership levels on a fresh install and schedules a rewrite flush.
 */
final class Activation {

	/** Plugin activated. */
	public static function activate(): void {
		Capabilities::install();
		( new Registrar() )->registerTaxonomies();
		( new Registrar() )->registerPostType();
		self::seedLevels();
		Ranking::refreshAll();
		update_option( ID::OPTION_VERSION, FAVR_DIRECTORY_VERSION );
		update_option( ID::OPTION_FLUSH, 1 );
	}

	/** Plugin deactivated: drop our rewrite rules. */
	public static function deactivate(): void {
		unregister_post_type( ID::POST_TYPE );
		flush_rewrite_rules( false );
	}

	/** Run upgrade routines when the stored version is behind the code. */
	public static function maybeUpgrade(): void {
		$stored = (string) get_option( ID::OPTION_VERSION, '' );
		if ( version_compare( $stored, FAVR_DIRECTORY_VERSION, '>=' ) ) {
			return;
		}
		Capabilities::install();
		Ranking::refreshAll();
		update_option( ID::OPTION_VERSION, FAVR_DIRECTORY_VERSION );
		update_option( ID::OPTION_FLUSH, 1 );
	}

	/** Typical chamber tiers, only when no levels exist yet. */
	private static function seedLevels(): void {
		$existing = get_terms(
			array(
				'taxonomy'   => ID::TAX_LEVEL,
				'hide_empty' => false,
				'number'     => 1,
				'fields'     => 'ids',
			)
		);
		if ( is_wp_error( $existing ) || array() !== $existing ) {
			return;
		}

		$levels = array(
			array( __( 'Platinum', 'favr-directory' ), 1, '#6d28d9' ),
			array( __( 'Gold', 'favr-directory' ), 2, '#b45309' ),
			array( __( 'Silver', 'favr-directory' ), 3, '#64748b' ),
			array( __( 'Member', 'favr-directory' ), 4, '#0f766e' ),
		);
		foreach ( $levels as $level ) {
			$term = wp_insert_term( $level[0], ID::TAX_LEVEL );
			if ( is_wp_error( $term ) ) {
				continue;
			}
			update_term_meta( (int) $term['term_id'], ID::TERM_META_ORDER, $level[1] );
			update_term_meta( (int) $term['term_id'], ID::TERM_META_COLOR, $level[2] );
		}
	}
}
