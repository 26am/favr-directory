<?php
/**
 * "Listing managers" box on the business edit screen.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Admin;

use FavrDirectory\Editing\Editors;
use FavrDirectory\Editing\Notifier;
use FavrDirectory\Editing\Values;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Vendor\FavrCore\Moderation\PendingChanges;

/**
 * Staff see who can edit the listing from the front end, add someone by email (inviting them
 * when they have no account yet), remove access, and read the recent change log.
 */
final class ManagersBox {

	private const NONCE = 'favr_directory_managers';

	/** Hook. */
	public function hook(): void {
		add_action( 'add_meta_boxes_' . ID::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . ID::POST_TYPE, array( $this, 'save' ), 20, 2 );
		add_action( 'admin_notices', array( $this, 'notices' ) );
	}

	/** Register the box. */
	public function add(): void {
		add_meta_box( 'favr-managers', __( 'Listing managers', 'favr-directory' ), array( $this, 'render' ), ID::POST_TYPE, 'side', 'default' );
	}

	/**
	 * Render.
	 *
	 * @param \WP_Post $post Business.
	 */
	public function render( \WP_Post $post ): void {
		wp_nonce_field( self::NONCE, '_favr_managers_nonce' );

		/**
		 * Another plugin managing who represents this listing (Favr Members: the member record).
		 *
		 * @param array{label: string, url: string}|null $elsewhere Link, or null.
		 * @param \WP_Post                               $post      Business.
		 */
		$elsewhere = apply_filters( 'favr_directory_managed_elsewhere', null, $post );
		if ( is_array( $elsewhere ) && ! empty( $elsewhere['url'] ) ) {
			printf(
				'<p class="description">%1$s <a href="%2$s">%3$s</a></p>',
				esc_html__( 'Representatives of the linked member can edit this listing.', 'favr-directory' ),
				esc_url( (string) $elsewhere['url'] ),
				esc_html( (string) ( $elsewhere['label'] ?? __( 'Manage on the member record →', 'favr-directory' ) ) )
			);
		}

		$managers = Editors::managers( $post->ID );
		if ( $managers ) {
			echo '<ul class="favr-managers">';
			foreach ( $managers as $user_id ) {
				$user = get_userdata( $user_id );
				if ( ! $user ) {
					continue;
				}
				printf(
					'<li><span>%1$s<br><small>%2$s</small></span> <label><input type="checkbox" name="favr_managers_remove[]" value="%3$d"> %4$s</label></li>',
					esc_html( $user->display_name ),
					esc_html( $user->user_email ),
					(int) $user_id,
					esc_html__( 'Remove', 'favr-directory' )
				);
			}
			echo '</ul>';
		} elseif ( ! is_array( $elsewhere ) ) {
			echo '<p class="description">' . esc_html__( 'Nobody manages this listing from the front end yet.', 'favr-directory' ) . '</p>';
		}

		printf(
			'<p><label for="favr-manager-add">%1$s</label><input type="text" id="favr-manager-add" name="favr_manager_add" class="widefat" placeholder="%2$s" autocomplete="off"></p><p class="description">%3$s</p>',
			esc_html__( 'Add by email or username', 'favr-directory' ),
			esc_attr__( 'owner@business.com', 'favr-directory' ),
			esc_html__( 'New email addresses get an account and an invitation to set a password.', 'favr-directory' )
		);

		$log = array_reverse( array_slice( PendingChanges::logEntries( $post->ID ), -6 ) );
		if ( $log ) {
			echo '<details class="favr-changelog"><summary>' . esc_html__( 'Recent changes by representatives', 'favr-directory' ) . '</summary><ul>';
			$actions = array(
				'saved'    => __( 'updated', 'favr-directory' ),
				'proposed' => __( 'suggested', 'favr-directory' ),
				'approved' => __( 'approved', 'favr-directory' ),
				'rejected' => __( 'rejected', 'favr-directory' ),
			);
			foreach ( $log as $entry ) {
				$who    = get_userdata( (int) $entry['user'] );
				$labels = array_map( static fn( string $id ): string => (string) ( Values::item( $id )['label'] ?? $id ), (array) $entry['fields'] );
				printf(
					'<li><strong>%1$s</strong> %2$s %3$s <small>(%4$s)</small></li>',
					esc_html( $who ? $who->display_name : __( 'Someone', 'favr-directory' ) ),
					esc_html( $actions[ $entry['action'] ] ?? (string) $entry['action'] ),
					esc_html( implode( ', ', $labels ) ),
					esc_html( sprintf( /* translators: %s: time ago. */ __( '%s ago', 'favr-directory' ), human_time_diff( (int) $entry['time'] ) ) )
				);
			}
			echo '</ul></details>';
		}
	}

	/**
	 * Save.
	 *
	 * @param int      $post_id Business.
	 * @param \WP_Post $post    Post.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['_favr_managers_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_favr_managers_nonce'] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'edit_others_' . ID::CAP_TYPE_PLURAL ) ) {
			return;
		}

		foreach ( array_map( 'absint', (array) wp_unslash( $_POST['favr_managers_remove'] ?? array() ) ) as $user_id ) {
			Editors::remove( $post_id, $user_id );
		}

		$who = isset( $_POST['favr_manager_add'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['favr_manager_add'] ) ) ) : '';
		if ( '' === $who ) {
			return;
		}
		$user = is_email( $who ) ? get_user_by( 'email', $who ) : get_user_by( 'login', $who );
		if ( ! $user && is_email( $who ) ) {
			$user = $this->invite( $who, $post );
		}
		if ( is_wp_error( $user ) || ! $user ) {
			set_transient( 'favr_managers_notice_' . get_current_user_id(), is_wp_error( $user ) ? $user->get_error_message() : __( 'No account matches that username. Use an email address to invite someone new.', 'favr-directory' ), 60 );
			return;
		}
		if ( user_can( $user, 'edit_others_posts' ) ) {
			set_transient( 'favr_managers_notice_' . get_current_user_id(), __( 'That person is staff and can already edit every listing.', 'favr-directory' ), 60 );
			return;
		}
		if ( ! in_array( (int) $user->ID, Editors::managers( $post_id ), true ) ) {
			Editors::add( $post_id, (int) $user->ID );
			Notifier::managerAdded( $post_id, (int) $user->ID );
		}
	}

	/**
	 * Create an account for a new email address and send the set-password email.
	 *
	 * @param string   $email Email.
	 * @param \WP_Post $post  Business.
	 * @return \WP_User|\WP_Error
	 */
	private function invite( string $email, \WP_Post $post ) {
		/**
		 * Create (and invite) a person for a listing. Favr Members hooks this to use its own
		 * accounts and invitation emails. Return a user id, a WP_User or a WP_Error; null falls
		 * back to a core WordPress account.
		 *
		 * @param int|\WP_User|\WP_Error|null $user  Result.
		 * @param string                      $email Email address.
		 * @param \WP_Post                    $post  Business.
		 */
		$result = apply_filters( 'favr_directory_invite_user', null, $email, $post );
		if ( $result instanceof \WP_User || is_wp_error( $result ) ) {
			return $result;
		}
		if ( is_int( $result ) && $result > 0 ) {
			$user = get_userdata( $result );
			return $user ? $user : new \WP_Error( 'favr_invite', __( 'The account could not be created.', 'favr-directory' ) );
		}

		$login = sanitize_user( (string) strstr( $email, '@', true ), true );
		$base  = '' !== $login ? $login : 'member';
		$n     = 1;
		while ( username_exists( $login ) || '' === $login ) {
			$login = $base . ( ++$n );
		}
		$user_id = wp_insert_user(
			array(
				'user_login' => $login,
				'user_email' => $email,
				'user_pass'  => wp_generate_password( 32, true, true ),
				'role'       => get_option( 'default_role', 'subscriber' ),
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		wp_new_user_notification( $user_id, null, 'user' );
		$user = get_userdata( $user_id );
		return $user ? $user : new \WP_Error( 'favr_invite', __( 'The account could not be created.', 'favr-directory' ) );
	}

	/** Show a problem from the last save. */
	public function notices(): void {
		$key     = 'favr_managers_notice_' . get_current_user_id();
		$message = get_transient( $key );
		if ( is_string( $message ) && '' !== $message ) {
			delete_transient( $key );
			printf( '<div class="notice notice-warning is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}
	}
}
