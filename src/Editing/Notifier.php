<?php
/**
 * Emails about listing edits and claims.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Editing;

use FavrDirectory\Support\Settings;

/**
 * Plain-text emails. Every message passes through `favr_directory_email` so sites can reword,
 * redirect or suppress it.
 */
final class Notifier {

	/** Staff address. */
	public static function staffEmail(): string {
		$email = (string) Settings::get( 'notify_email' );
		return is_email( $email ) ? $email : (string) get_option( 'admin_email' );
	}

	/**
	 * Tell staff that changes were proposed.
	 *
	 * @param int          $post_id Business.
	 * @param int          $user_id Proposer.
	 * @param list<string> $fields  Item ids.
	 */
	public static function proposed( int $post_id, int $user_id, array $fields ): void {
		$user  = get_userdata( $user_id );
		$lines = array_map( static fn( string $id ): string => '- ' . (string) ( Values::item( $id )['label'] ?? $id ), $fields );
		self::send(
			'changes_proposed',
			self::staffEmail(),
			/* translators: %s: business name. */
			sprintf( __( 'Listing update to review: %s', 'favr-directory' ), get_the_title( $post_id ) ),
			sprintf(
				/* translators: 1: person, 2: business, 3: list of fields, 4: review link. */
				__( "%1\$s suggested changes to %2\$s:\n\n%3\$s\n\nReview them here:\n%4\$s", 'favr-directory' ),
				$user ? $user->display_name : __( 'A representative', 'favr-directory' ),
				get_the_title( $post_id ),
				implode( "\n", $lines ),
				admin_url( 'admin.php?page=favr-approvals' )
			),
			compact( 'post_id', 'user_id', 'fields' )
		);
	}

	/**
	 * Tell a representative what happened to their suggestions.
	 *
	 * @param int          $post_id  Business.
	 * @param int          $user_id  Proposer.
	 * @param string       $decision approved | rejected.
	 * @param list<string> $fields   Item ids.
	 * @param string       $note     Staff note.
	 */
	public static function decided( int $post_id, int $user_id, string $decision, array $fields, string $note ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$labels = implode( ', ', array_map( static fn( string $id ): string => (string) ( Values::item( $id )['label'] ?? $id ), $fields ) );
		$body   = 'approved' === $decision
			/* translators: 1: fields, 2: business. */
			? sprintf( __( 'Good news: your changes to %2$s (%1$s) are now live.', 'favr-directory' ), $labels, get_the_title( $post_id ) )
			/* translators: 1: fields, 2: business. */
			: sprintf( __( 'Your suggested changes to %2$s (%1$s) were not approved.', 'favr-directory' ), $labels, get_the_title( $post_id ) );
		if ( '' !== $note ) {
			$body .= "\n\n" . __( 'Note from our team:', 'favr-directory' ) . "\n" . $note;
		}
		$body .= "\n\n" . (string) get_permalink( $post_id );
		self::send(
			'changes_' . $decision,
			$user->user_email,
			'approved' === $decision
				/* translators: %s: business name. */
				? sprintf( __( 'Your listing update is live: %s', 'favr-directory' ), get_the_title( $post_id ) )
				/* translators: %s: business name. */
				: sprintf( __( 'About your listing update: %s', 'favr-directory' ), get_the_title( $post_id ) ),
			$body,
			compact( 'post_id', 'user_id', 'fields', 'note' )
		);
	}

	/**
	 * Tell staff about a claim.
	 *
	 * @param int    $post_id Business.
	 * @param int    $user_id Claimant.
	 * @param string $message Their message.
	 */
	public static function claimed( int $post_id, int $user_id, string $message ): void {
		$user = get_userdata( $user_id );
		self::send(
			'claim_received',
			self::staffEmail(),
			/* translators: %s: business name. */
			sprintf( __( 'Listing claim: %s', 'favr-directory' ), get_the_title( $post_id ) ),
			sprintf(
				/* translators: 1: person, 2: their email, 3: business, 4: message, 5: review link. */
				__( "%1\$s (%2\$s) says they represent %3\$s.\n\n%4\$s\n\nApprove or reject:\n%5\$s", 'favr-directory' ),
				$user ? $user->display_name : '',
				$user ? $user->user_email : '',
				get_the_title( $post_id ),
				'' !== $message ? $message : __( '(no message)', 'favr-directory' ),
				admin_url( 'admin.php?page=favr-approvals' )
			),
			compact( 'post_id', 'user_id', 'message' )
		);
	}

	/**
	 * Tell a claimant the outcome.
	 *
	 * @param int    $post_id  Business.
	 * @param int    $user_id  Claimant.
	 * @param string $decision approved | rejected.
	 * @param string $note     Staff note.
	 */
	public static function claimDecided( int $post_id, int $user_id, string $decision, string $note ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		if ( 'approved' === $decision ) {
			$url  = FrontEditor::url( $post_id );
			$body = sprintf(
				/* translators: %s: business name. */
				__( 'You can now update %s.', 'favr-directory' ),
				get_the_title( $post_id )
			) . ( '' !== $url ? "\n\n" . $url : '' );
		} else {
			/* translators: %s: business name. */
			$body = sprintf( __( 'Your request to manage %s was not approved.', 'favr-directory' ), get_the_title( $post_id ) );
		}
		if ( '' !== $note ) {
			$body .= "\n\n" . __( 'Note from our team:', 'favr-directory' ) . "\n" . $note;
		}
		self::send(
			'claim_' . $decision,
			$user->user_email,
			/* translators: %s: business name. */
			sprintf( __( 'Your listing claim: %s', 'favr-directory' ), get_the_title( $post_id ) ),
			$body,
			compact( 'post_id', 'user_id', 'note' )
		);
	}

	/**
	 * Tell someone they were added as a listing manager.
	 *
	 * @param int $post_id Business.
	 * @param int $user_id User.
	 */
	public static function managerAdded( int $post_id, int $user_id ): void {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$url = FrontEditor::url( $post_id );
		self::send(
			'manager_added',
			$user->user_email,
			/* translators: %s: business name. */
			sprintf( __( 'You can now update %s', 'favr-directory' ), get_the_title( $post_id ) ),
			sprintf(
				/* translators: %s: business name. */
				__( 'You have been added as a manager of the %s listing. Log in to keep it up to date.', 'favr-directory' ),
				get_the_title( $post_id )
			) . ( '' !== $url ? "\n\n" . $url : '' ),
			compact( 'post_id', 'user_id' )
		);
	}

	/**
	 * Filter and send.
	 *
	 * @param string               $type    Message type.
	 * @param string               $to      Recipient.
	 * @param string               $subject Subject.
	 * @param string               $message Body.
	 * @param array<string, mixed> $context Context for filters.
	 */
	private static function send( string $type, string $to, string $subject, string $message, array $context ): void {
		$mail = array(
			'to'      => $to,
			'subject' => wp_specialchars_decode( $subject ),
			'message' => wp_specialchars_decode( $message ),
			'headers' => array(),
		);

		/**
		 * Filter a Favr Directory email. Return false to skip sending.
		 *
		 * @param array|false $mail    { to, subject, message, headers }.
		 * @param string      $type    changes_proposed | changes_approved | changes_rejected | claim_received | claim_approved | claim_rejected | manager_added.
		 * @param array       $context post_id, user_id and related data.
		 */
		$mail = apply_filters( 'favr_directory_email', $mail, $type, $context );
		if ( is_array( $mail ) && ! empty( $mail['to'] ) ) {
			wp_mail( $mail['to'], (string) $mail['subject'], (string) $mail['message'], $mail['headers'] ?? array() );
		}
	}
}
