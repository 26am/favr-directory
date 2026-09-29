<?php
/**
 * Favr dashboard card and quick action (the Favr Sites plugin).
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Integration;

use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;

/**
 * Contributes plain data to the Favr dashboard; inert when Favr Sites isn't installed.
 */
final class FavrSites {

	/** Hooks. */
	public function hook(): void {
		add_filter( 'favr_sites_quick_actions', array( $this, 'actions' ) );
		add_filter( 'favr_sites_dashboard_cards', array( $this, 'cards' ) );
	}

	/**
	 * Quick action.
	 *
	 * @param array<mixed> $actions Actions.
	 * @return array<mixed>
	 */
	public function actions( array $actions ): array {
		$actions[] = array(
			'id'         => 'add-listing',
			// Not "Add member": on people directories Favr Members adds its own "Add member" (a member record).
			'label'      => __( 'Add directory listing', 'favr-directory' ),
			'url'        => admin_url( 'post-new.php?post_type=' . ID::POST_TYPE ),
			'capability' => 'edit_' . ID::CAP_TYPE_PLURAL,
			'icon'       => 'store',
			'priority'   => 40,
		);
		return $actions;
	}

	/**
	 * Card, built only when the dashboard renders.
	 *
	 * @param array<mixed> $cards Cards.
	 * @return array<mixed>
	 */
	public function cards( array $cards ): array {
		$cards[] = array( $this, 'card' );
		return $cards;
	}

	/**
	 * The directory card.
	 *
	 * @return array<string, mixed>
	 */
	public function card(): array {
		$list   = admin_url( 'edit.php?post_type=' . ID::POST_TYPE );
		$count  = (int) ( wp_count_posts( ID::POST_TYPE )->publish ?? 0 );
		$newest = get_posts(
			array(
				'post_type'      => ID::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 3,
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		return array(
			'id'         => 'directory',
			'title'      => __( 'Directory', 'favr-directory' ),
			'capability' => 'edit_' . ID::CAP_TYPE_PLURAL,
			'priority'   => 20,
			'stats'      => $count ? array(
				array(
					'label' => Settings::noun( true ),
					'value' => number_format_i18n( $count ),
					'url'   => $list,
				),
			) : array(),
			'items'      => array_map(
				static fn( \WP_Post $post ): array => array(
					'title' => get_the_title( $post ),
					/* translators: %s: date the listing was added. */
					'meta'  => sprintf( __( 'added %s', 'favr-directory' ), get_the_date( 'M j', $post ) ),
					'url'   => (string) get_edit_post_link( $post->ID, 'raw' ),
				),
				$newest
			),
			'link'       => array(
				'label' => __( 'Open the directory', 'favr-directory' ),
				'url'   => $list,
			),
			'empty'      => array(
				'text'  => __( 'No listings yet.', 'favr-directory' ),
				'label' => __( 'Add the first one', 'favr-directory' ),
				'url'   => admin_url( 'post-new.php?post_type=' . ID::POST_TYPE ),
			),
		);
	}
}
