<?php
/**
 * "Listing updates" queue in the shared Approvals inbox.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Editing;

use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Vendor\FavrCore\Approvals\Inbox;
use FavrDirectory\Vendor\FavrCore\Moderation\PendingChanges;

/**
 * Lists businesses with proposed changes, shows before/after, and applies or discards them.
 */
final class ChangeQueue {

	/** Hook. */
	public function hook(): void {
		add_filter( 'favr_approvals_providers', array( $this, 'provider' ) );
		add_action( 'admin_notices', array( $this, 'editScreenNotice' ) );
	}

	/**
	 * Register the queue.
	 *
	 * @param array<int, array<string, mixed>> $providers Providers.
	 * @return array<int, array<string, mixed>>
	 */
	public function provider( array $providers ): array {
		$providers[] = array(
			'id'         => 'favr_directory_changes',
			'label'      => __( 'Listing updates', 'favr-directory' ),
			'capability' => 'edit_others_' . ID::CAP_TYPE_PLURAL,
			'items'      => array( $this, 'items' ),
			'decide'     => array( $this, 'decide' ),
		);
		return $providers;
	}

	/**
	 * Businesses with pending changes.
	 *
	 * @return list<int>
	 */
	public static function pendingIds(): array {
		return array_map(
			'intval',
			get_posts(
				array(
					'post_type'      => ID::POST_TYPE,
					'post_status'    => array( 'publish', 'pending', 'draft', 'private' ),
					'posts_per_page' => 100,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => PendingChanges::META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- EXISTS on a rarely-set key.
					'orderby'        => 'modified',
					'order'          => 'ASC',
				)
			)
		);
	}

	/**
	 * Queue items.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function items(): array {
		$items = array();
		foreach ( self::pendingIds() as $post_id ) {
			$pending = PendingChanges::get( $post_id );
			if ( array() === $pending ) {
				continue;
			}
			$rows   = array();
			$fields = array();
			$users  = array();
			$time   = 0;
			foreach ( $pending as $id => $change ) {
				$label         = (string) ( Values::item( (string) $id )['label'] ?? $id );
				$fields[ $id ] = $label;
				$rows[]        = array(
					'label' => $label,
					'old'   => Values::display( (string) $id, Values::current( $post_id, (string) $id ) ),
					'new'   => Values::display( (string) $id, $change['new'] ),
				);
				$users[]       = (int) $change['user'];
				$time          = max( $time, (int) $change['time'] );
			}
			$names   = array_filter( array_map( static fn( int $u ): string => (string) ( get_userdata( $u )->display_name ?? '' ), array_unique( $users ) ) );
			$items[] = array(
				'id'       => $post_id,
				'title'    => get_the_title( $post_id ),
				/* translators: %s: names. */
				'subtitle' => sprintf( __( 'Suggested by %s', 'favr-directory' ), implode( ', ', $names ) ),
				'edit_url' => (string) get_edit_post_link( $post_id, 'raw' ),
				'time'     => $time,
				'details'  => Inbox::diff( $rows ),
				'fields'   => $fields,
			);
		}
		return $items;
	}

	/**
	 * Approve or reject.
	 *
	 * @param int               $post_id  Business.
	 * @param string            $decision approve | reject.
	 * @param list<string>|null $fields   Ticked fields, or null for all.
	 * @param string            $note     Note for the representative.
	 */
	public function decide( int $post_id, string $decision, ?array $fields, string $note ): string {
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return __( 'You can’t edit that listing.', 'favr-directory' );
		}
		if ( 'approve' === $decision && is_array( $fields ) && array() === $fields ) {
			return __( 'Nothing was selected, so nothing changed.', 'favr-directory' );
		}
		$taken = PendingChanges::take( $post_id, 'approve' === $decision ? $fields : null );
		if ( array() === $taken ) {
			return __( 'Those changes were already handled.', 'favr-directory' );
		}

		$by_user = array();
		foreach ( $taken as $id => $change ) {
			if ( 'approve' === $decision ) {
				Values::apply( $post_id, (string) $id, $change['new'] );
			}
			$by_user[ (int) $change['user'] ][] = (string) $id;
		}
		$ids = array_map( 'strval', array_keys( $taken ) );
		PendingChanges::log( $post_id, 'approve' === $decision ? 'approved' : 'rejected', $ids, get_current_user_id(), $note );

		// Approving some fields and leaving the rest: the rest stay pending for later.
		foreach ( $by_user as $user_id => $user_fields ) {
			Notifier::decided( $post_id, $user_id, 'approve' === $decision ? 'approved' : 'rejected', $user_fields, $note );
		}
		if ( 'approve' === $decision ) {
			clean_post_cache( $post_id );
			/** This action is documented in src/Editing/FrontEditor.php */
			do_action( 'favr_directory_member_saved', $post_id, $ids, get_current_user_id() );
		}

		return sprintf(
			/* translators: 1: number of changes, 2: business. */
			'approve' === $decision ? _n( 'Approved %1$d change to %2$s.', 'Approved %1$d changes to %2$s.', count( $ids ), 'favr-directory' ) : _n( 'Rejected %1$d change to %2$s.', 'Rejected %1$d changes to %2$s.', count( $ids ), 'favr-directory' ),
			count( $ids ),
			get_the_title( $post_id )
		);
	}

	/** On the business edit screen, point staff at waiting suggestions. */
	public function editScreenNotice(): void {
		$screen = get_current_screen();
		$post   = get_post();
		if ( ! $screen || 'post' !== $screen->base || ID::POST_TYPE !== $screen->post_type || ! $post ) {
			return;
		}
		$pending = PendingChanges::get( $post->ID );
		if ( array() === $pending || ! current_user_can( 'edit_others_' . ID::CAP_TYPE_PLURAL ) ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html(
				sprintf(
					/* translators: %d: number of fields. */
					_n( 'The business suggested %d change to this listing.', 'The business suggested %d changes to this listing.', count( $pending ), 'favr-directory' ),
					count( $pending )
				)
			),
			esc_url( admin_url( 'admin.php?page=' . Inbox::PAGE ) ),
			esc_html__( 'Review in Approvals →', 'favr-directory' )
		);
	}
}
