<?php
/**
 * "Is this your business? Claim it."
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Editing;

use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;
use FavrDirectory\Vendor\FavrCore\Support\RateLimit;

/**
 * Claims are stored as individual `_favr_claim` meta rows on the business ({ user, time,
 * message }); the meta id identifies a claim in the Approvals inbox. Approval hands the person
 * to `favr_directory_claim_approve` (Favr Members adds them as a representative of the linked
 * member) and otherwise makes them a listing manager.
 */
final class Claims {

	public const META   = '_favr_claim';
	public const ACTION = 'favr_claim';

	/** Claims per person per day. */
	private const LIMIT = 5;

	/** Hook. */
	public function hook(): void {
		add_action( 'favr_directory_after_profile', array( $this, 'box' ), 5, 2 );
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );
		add_filter( 'favr_approvals_providers', array( $this, 'provider' ) );
	}

	/**
	 * Owner box at the end of a profile: edit link, claim form, or login prompt.
	 *
	 * @param Business $business Business.
	 * @param string   $context  Render context.
	 */
	public function box( $business, string $context = '' ): void {
		if ( ! $business instanceof Business || ! is_singular( ID::POST_TYPE ) || get_queried_object_id() !== $business->id() ) {
			return;
		}
		$post_id = $business->id();
		$user_id = get_current_user_id();

		if ( $user_id && Editors::canEdit( $user_id, $post_id ) ) {
			$url = FrontEditor::url( $post_id );
			if ( '' !== $url ) {
				printf(
					'<aside class="favr-owner"><p>%1$s</p><a class="favr-btn favr-btn--primary" href="%2$s">%3$s</a></aside>',
					esc_html__( 'You manage this listing.', 'favr-directory' ),
					esc_url( $url ),
					esc_html__( 'Edit your listing', 'favr-directory' )
				);
			}
			return;
		}
		if ( '1' !== (string) Settings::get( 'claims' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$sent = isset( $_GET['fd_claim'] ) ? sanitize_key( wp_unslash( $_GET['fd_claim'] ) ) : '';
		echo '<aside class="favr-owner favr-owner--claim" id="favr-claim">';
		if ( ! $user_id ) {
			printf(
				'<p>%1$s</p><a class="favr-btn" href="%2$s">%3$s</a>',
				esc_html__( 'Is this your business? Log in to claim it and keep it up to date.', 'favr-directory' ),
				esc_url( FrontEditor::loginUrl( (string) get_permalink( $post_id ) . '#favr-claim' ) ),
				esc_html__( 'Log in to claim', 'favr-directory' )
			);
		} elseif ( 'sent' === $sent || null !== self::claimBy( $post_id, $user_id ) ) {
			echo '<p>' . esc_html__( 'Thanks! Your claim is with our team. We’ll email you once it’s reviewed.', 'favr-directory' ) . '</p>';
		} elseif ( 'slow' === $sent ) {
			echo '<p>' . esc_html__( 'You’ve sent several claims recently. Please try again tomorrow or contact us.', 'favr-directory' ) . '</p>';
		} else {
			printf(
				'<details><summary>%1$s</summary><form method="post" action="%2$s" class="favr-claim-form">',
				esc_html__( 'Is this your business? Claim it', 'favr-directory' ),
				esc_url( (string) get_permalink( $post_id ) )
			);
			wp_nonce_field( self::ACTION . '_' . $post_id, '_favr_claim_nonce' );
			printf(
				'<input type="hidden" name="favr_action" value="%1$s"><input type="hidden" name="favr_listing" value="%2$d">'
				. '<p class="favr-hp" aria-hidden="true"><label>%3$s <input type="text" name="favr_website_check" tabindex="-1" autocomplete="off"></label></p>'
				. '<p><label for="favr-claim-msg">%4$s</label><textarea id="favr-claim-msg" name="favr_claim_message" rows="3" maxlength="1000" placeholder="%5$s"></textarea></p>'
				. '<p><button type="submit" class="favr-btn favr-btn--primary">%6$s</button></p></form></details>',
				esc_attr( self::ACTION ),
				(int) $post_id,
				esc_html__( 'Leave this empty', 'favr-directory' ),
				esc_html__( 'How are you connected to this business?', 'favr-directory' ),
				esc_attr__( 'e.g. I’m the owner; you can reach me at the business phone number.', 'favr-directory' ),
				esc_html__( 'Send claim', 'favr-directory' )
			);
		}
		echo '</aside>';
	}

	/** Handle a claim submission. */
	public function handle(): void {
		if ( ! isset( $_POST['favr_action'] ) || self::ACTION !== $_POST['favr_action'] ) {
			return;
		}
		$post_id = isset( $_POST['favr_listing'] ) ? absint( $_POST['favr_listing'] ) : 0;
		$nonce   = isset( $_POST['_favr_claim_nonce'] ) ? sanitize_key( wp_unslash( $_POST['_favr_claim_nonce'] ) ) : '';
		$user_id = get_current_user_id();
		$back    = (string) get_permalink( $post_id );

		if ( ! $user_id || ! wp_verify_nonce( $nonce, self::ACTION . '_' . $post_id ) || 'publish' !== get_post_status( $post_id ) || ID::POST_TYPE !== get_post_type( $post_id ) || '1' !== (string) Settings::get( 'claims' ) ) {
			wp_safe_redirect( '' !== $back ? $back : home_url( '/' ), 303 );
			exit;
		}
		// Bots fill every field; people never see this one.
		if ( ! empty( $_POST['favr_website_check'] ) || Editors::canEdit( $user_id, $post_id ) || null !== self::claimBy( $post_id, $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'fd_claim', 'sent', $back ) . '#favr-claim', 303 );
			exit;
		}
		if ( ! RateLimit::hit( 'listing_claim_' . $user_id, self::LIMIT, DAY_IN_SECONDS ) ) {
			wp_safe_redirect( add_query_arg( 'fd_claim', 'slow', $back ) . '#favr-claim', 303 );
			exit;
		}
		$message = isset( $_POST['favr_claim_message'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['favr_claim_message'] ) ), 0, 1000 ) : '';
		add_post_meta(
			$post_id,
			self::META,
			array(
				'user'    => $user_id,
				'time'    => time(),
				'message' => $message,
			)
		);
		Notifier::claimed( $post_id, $user_id, $message );

		/**
		 * After someone claims a listing.
		 *
		 * @param int    $post_id Business.
		 * @param int    $user_id Claimant.
		 * @param string $message Their message.
		 */
		do_action( 'favr_directory_claim_received', $post_id, $user_id, $message );
		wp_safe_redirect( add_query_arg( 'fd_claim', 'sent', $back ) . '#favr-claim', 303 );
		exit;
	}

	/**
	 * A person's open claim on a listing.
	 *
	 * @param int $post_id Business.
	 * @param int $user_id User.
	 * @return array<string, mixed>|null
	 */
	public static function claimBy( int $post_id, int $user_id ): ?array {
		foreach ( (array) get_post_meta( $post_id, self::META, false ) as $claim ) {
			if ( is_array( $claim ) && (int) ( $claim['user'] ?? 0 ) === $user_id ) {
				return $claim;
			}
		}
		return null;
	}

	/**
	 * Register the queue.
	 *
	 * @param array<int, array<string, mixed>> $providers Providers.
	 * @return array<int, array<string, mixed>>
	 */
	public function provider( array $providers ): array {
		$providers[] = array(
			'id'         => 'favr_directory_claims',
			'label'      => __( 'Listing claims', 'favr-directory' ),
			'capability' => 'edit_others_' . ID::CAP_TYPE_PLURAL,
			'items'      => array( $this, 'items' ),
			'decide'     => array( $this, 'decide' ),
		);
		return $providers;
	}

	/**
	 * Open claims (meta rows).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function items(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- small admin-only list; no API returns meta ids.
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id ASC LIMIT 100", self::META ) );
		$items = array();
		foreach ( (array) $rows as $row ) {
			$claim = maybe_unserialize( $row->meta_value );
			$user  = is_array( $claim ) ? get_userdata( (int) ( $claim['user'] ?? 0 ) ) : false;
			if ( ! $user || ID::POST_TYPE !== get_post_type( (int) $row->post_id ) ) {
				continue;
			}
			$managers = array_filter( array_map( static fn( int $u ): string => (string) ( get_userdata( $u )->display_name ?? '' ), Editors::managers( (int) $row->post_id ) ) );
			$details  = sprintf(
				'<p><strong>%1$s</strong> &lt;%2$s&gt;</p>%3$s%4$s',
				esc_html( $user->display_name ),
				esc_html( $user->user_email ),
				'' !== (string) $claim['message'] ? '<blockquote>' . nl2br( esc_html( (string) $claim['message'] ) ) . '</blockquote>' : '',
				$managers ? '<p>' . esc_html( sprintf( /* translators: %s: names. */ __( 'Current managers: %s', 'favr-directory' ), implode( ', ', $managers ) ) ) . '</p>' : ''
			);
			$items[]  = array(
				'id'       => (int) $row->meta_id,
				'title'    => get_the_title( (int) $row->post_id ),
				/* translators: %s: person. */
				'subtitle' => sprintf( __( 'claimed by %s', 'favr-directory' ), $user->display_name ),
				'edit_url' => (string) get_edit_post_link( (int) $row->post_id, 'raw' ),
				'time'     => (int) ( $claim['time'] ?? 0 ),
				'details'  => $details,
			);
		}
		return $items;
	}

	/**
	 * Approve or reject a claim.
	 *
	 * @param int               $meta_id  Claim meta id.
	 * @param string            $decision approve | reject.
	 * @param list<string>|null $fields   Unused.
	 * @param string            $note     Note for the claimant.
	 */
	public function decide( int $meta_id, string $decision, ?array $fields, string $note ): string {
		$meta = get_metadata_by_mid( 'post', $meta_id );
		if ( ! $meta || self::META !== $meta->meta_key || ! is_array( $meta->meta_value ) ) {
			return __( 'That claim was already handled.', 'favr-directory' );
		}
		$post_id = (int) $meta->post_id;
		$user_id = (int) ( $meta->meta_value['user'] ?? 0 );
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return __( 'You can’t edit that listing.', 'favr-directory' );
		}
		delete_metadata_by_mid( 'post', $meta_id );
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return __( 'That person no longer has an account.', 'favr-directory' );
		}

		if ( 'approve' === $decision ) {
			/**
			 * Let another plugin take ownership of an approved claim (Favr Members makes the person
			 * a representative of the linked member). Return true when handled.
			 *
			 * @param bool $handled Whether handled.
			 * @param int  $post_id Business.
			 * @param int  $user_id Claimant.
			 */
			if ( ! apply_filters( 'favr_directory_claim_approve', false, $post_id, $user_id ) ) {
				Editors::add( $post_id, $user_id );
			}

			/**
			 * After a claim is approved.
			 *
			 * @param int $post_id Business.
			 * @param int $user_id Claimant.
			 */
			do_action( 'favr_directory_claim_approved', $post_id, $user_id );
		}
		Notifier::claimDecided( $post_id, $user_id, 'approve' === $decision ? 'approved' : 'rejected', $note );

		return sprintf(
			/* translators: 1: person, 2: business. */
			'approve' === $decision ? __( '%1$s can now manage %2$s.', 'favr-directory' ) : __( 'Rejected %1$s’s claim on %2$s.', 'favr-directory' ),
			$user->display_name,
			get_the_title( $post_id )
		);
	}
}
