<?php
/**
 * "My Listing": representatives update their listing from the front end.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Editing;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\AssetVersion;
use FavrDirectory\Support\Settings;
use FavrDirectory\Vendor\FavrCore\Admin\FieldRenderer;
use FavrDirectory\Vendor\FavrCore\Moderation\PendingChanges;
use FavrDirectory\Vendor\FavrCore\Moderation\Uploads;
use FavrDirectory\Vendor\FavrCore\Support\RateLimit;

/**
 * Renders the edit form ([favr_my_listing], the My Listing block, the Favr Members dashboard
 * tab) and handles its submission with post/redirect/get. Items the policy marks `edit` are
 * saved immediately; `review` items become proposals in the Approvals inbox.
 */
final class FrontEditor {

	public const ACTION    = 'favr_listing_save';
	public const SHORTCODE = 'favr_my_listing';
	public const QV_LIST   = 'fd_listing';
	public const QV_MSG    = 'fd_msg';

	/** Saves per person per hour. */
	private const SAVE_LIMIT = 30;

	/** Hook. */
	public function hook(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
		add_action( 'template_redirect', array( $this, 'handle' ), 5 );
		add_action( 'template_redirect', array( $this, 'noCache' ) );
		add_filter( 'favr_members_dashboard_tabs', array( $this, 'dashboardTab' ), 10, 2 );
		add_action( 'save_post_page', array( self::class, 'forgetPage' ) );
		// Public API for sibling plugins (e.g. Favr Events: which businesses may a person host for).
		add_filter( 'favr_directory_listings_for_user', static fn( $ids, $user_id ): array => array_merge( (array) $ids, Editors::listingsFor( (int) $user_id ) ), 10, 2 );
	}

	/**
	 * URL where a person edits a listing (empty when no edit page is known).
	 *
	 * @param int $post_id Business (0 for the page itself).
	 */
	public static function url( int $post_id = 0 ): string {
		$page = (int) Settings::get( 'edit_page' );
		$page = $page > 0 ? $page : self::detectPage();
		$url  = $page > 0 && 'publish' === get_post_status( $page ) ? (string) get_permalink( $page ) : '';

		/**
		 * Filter the "edit my listing" URL. Favr Members points it at its dashboard tab.
		 *
		 * @param string $url     URL or ''.
		 * @param int    $post_id Business id, or 0.
		 */
		$url = (string) apply_filters( 'favr_directory_edit_url', $url, $post_id );
		return ( '' !== $url && $post_id > 0 ) ? add_query_arg( self::QV_LIST, $post_id, $url ) : $url;
	}

	/**
	 * First published page carrying the shortcode or block (cached for a day, reset when pages change).
	 */
	private static function detectPage(): int {
		$cached = get_transient( 'favr_directory_edit_page' );
		if ( false !== $cached ) {
			return (int) $cached;
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- cached above; runs at most once a day.
		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND ( post_content LIKE %s OR post_content LIKE %s ) ORDER BY ID ASC LIMIT 1",
				'%' . $wpdb->esc_like( '[' . self::SHORTCODE ) . '%',
				'%' . $wpdb->esc_like( '<!-- wp:favr-directory/my-listing' ) . '%'
			)
		);
		set_transient( 'favr_directory_edit_page', $found, DAY_IN_SECONDS );
		return $found;
	}

	/** Forget the detected page when pages change. */
	public static function forgetPage(): void {
		delete_transient( 'favr_directory_edit_page' );
	}

	/**
	 * Login URL that returns here.
	 *
	 * @param string $return_to Where to come back to.
	 */
	public static function loginUrl( string $return_to ): string {
		/**
		 * Filter the login URL used by listing editing and claims.
		 *
		 * @param string $url       URL.
		 * @param string $return_to Return URL.
		 */
		return (string) apply_filters( 'favr_directory_login_url', wp_login_url( $return_to ), $return_to );
	}

	/** Shortcode. */
	public static function shortcode(): string {
		return self::render();
	}

	/** Never cache a page that carries the form. */
	public function noCache(): void {
		$post = get_queried_object();
		if ( is_singular() && $post instanceof \WP_Post && ( has_shortcode( $post->post_content, self::SHORTCODE ) || has_block( 'favr-directory/my-listing', $post ) ) ) {
			nocache_headers();
			if ( ! defined( 'DONOTCACHEPAGE' ) ) {
				define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- page-cache convention.
			}
		}
	}

	/**
	 * Members dashboard tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tabs.
	 * @param \WP_User                            $user Person.
	 * @return array<string, array<string, mixed>>
	 */
	public function dashboardTab( array $tabs, $user ): array {
		if ( ! $user instanceof \WP_User ) {
			return $tabs;
		}
		$count = count( Editors::listingsFor( $user->ID ) );
		if ( $count > 0 ) {
			$tabs['listing'] = array(
				'label'    => _n( 'My Listing', 'My Listings', $count, 'favr-directory' ),
				'priority' => 20,
				'render'   => static fn(): string => self::render(),
			);
		}
		return $tabs;
	}

	/** Register and enqueue the form assets. */
	private static function enqueue(): void {
		$v = array( AssetVersion::class, 'of' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'favr-core-fields', FAVR_DIRECTORY_URL . 'assets/core/fields.css', array( 'dashicons' ), $v( 'assets/core/fields.css' ) );
		wp_enqueue_style( 'favr-core-front', FAVR_DIRECTORY_URL . 'assets/core/front-fields.css', array( 'favr-core-fields' ), $v( 'assets/core/front-fields.css' ) );
		wp_enqueue_script( 'favr-core-fields', FAVR_DIRECTORY_URL . 'assets/core/fields.js', array( 'jquery', 'jquery-ui-sortable' ), $v( 'assets/core/fields.js' ), true );
		wp_localize_script(
			'favr-core-fields',
			'favrCoreFields',
			array(
				'i18n' => array(
					'confirmClear' => __( 'Remove all opening hours?', 'favr-directory' ),
					'removeImage'  => __( 'Remove image', 'favr-directory' ),
					/* translators: %d: number of characters. */
					'charsLeft'    => __( '%d characters left', 'favr-directory' ),
				),
			)
		);
		wp_enqueue_script( 'favr-core-uploader', FAVR_DIRECTORY_URL . 'assets/core/uploader.js', array( 'favr-core-fields' ), $v( 'assets/core/uploader.js' ), true );
		wp_enqueue_style( 'favr-directory' );
	}

	/**
	 * The editor for the current person.
	 *
	 * @param array<string, mixed> $atts Unused (reserved).
	 */
	public static function render( array $atts = array() ): string {
		$here = self::currentUrl();
		if ( ! is_user_logged_in() ) {
			return sprintf(
				'<div class="favr-front favr-my-listing favr-my-listing--empty"><p>%1$s</p><p><a class="favr-btn favr-btn--primary" href="%2$s">%3$s</a></p></div>',
				esc_html__( 'Log in to update your business listing.', 'favr-directory' ),
				esc_url( self::loginUrl( $here ) ),
				esc_html__( 'Log in', 'favr-directory' )
			);
		}

		$user_id = get_current_user_id();
		$ids     = Editors::listingsFor( $user_id );
		if ( array() === $ids ) {
			$directory = get_post_type_archive_link( ID::POST_TYPE );
			return sprintf(
				'<div class="favr-front favr-my-listing favr-my-listing--empty"><p>%1$s</p>%2$s</div>',
				esc_html__( 'Your account isn’t linked to a business listing yet. Find your business in the directory and choose “Claim this listing”, or contact us.', 'favr-directory' ),
				$directory ? '<p><a class="favr-btn" href="' . esc_url( $directory ) . '">' . esc_html__( 'Browse the directory', 'favr-directory' ) . '</a></p>' : ''
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only; access checked via $ids.
		$requested = isset( $_GET[ self::QV_LIST ] ) ? absint( $_GET[ self::QV_LIST ] ) : 0;
		$post_id   = in_array( $requested, $ids, true ) ? $requested : $ids[0];
		$post      = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		self::enqueue();
		ob_start();
		echo '<div class="favr-front favr-my-listing" id="favr-my-listing">';

		if ( count( $ids ) > 1 ) {
			echo '<nav class="favr-my-listing__switch" aria-label="' . esc_attr__( 'Your listings', 'favr-directory' ) . '">';
			foreach ( $ids as $id ) {
				printf(
					'<a href="%1$s"%2$s>%3$s</a>',
					esc_url( add_query_arg( self::QV_LIST, $id, remove_query_arg( self::QV_MSG, $here ) ) ),
					$id === $post_id ? ' aria-current="page" class="is-current"' : '',
					esc_html( get_the_title( $id ) )
				);
			}
			echo '</nav>';
		}

		self::renderNotice( $user_id );
		self::renderForm( $post, $user_id, $here );

		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Result message after a save.
	 *
	 * @param int $user_id Person.
	 */
	private static function renderNotice( int $user_id ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$code     = isset( $_GET[ self::QV_MSG ] ) ? sanitize_key( wp_unslash( $_GET[ self::QV_MSG ] ) ) : '';
		$messages = array(
			'saved'    => array( 'success', __( 'Your listing has been updated.', 'favr-directory' ) ),
			'pending'  => array( 'info', __( 'Thanks! Your changes were saved. Items marked “reviewed” go live once our team approves them.', 'favr-directory' ) ),
			'nochange' => array( 'info', __( 'Nothing changed.', 'favr-directory' ) ),
			'expired'  => array( 'error', __( 'Your session expired. Please try again.', 'favr-directory' ) ),
			'slow'     => array( 'error', __( 'You’ve made a lot of changes in a short time. Please wait a little and try again.', 'favr-directory' ) ),
		);
		if ( isset( $messages[ $code ] ) ) {
			printf( '<div class="favr-notice favr-notice--%1$s" role="status">%2$s</div>', esc_attr( $messages[ $code ][0] ), esc_html( $messages[ $code ][1] ) );
		}
		$invalid = get_transient( 'favr_listing_invalid_' . $user_id );
		if ( is_array( $invalid ) && array() !== $invalid ) {
			delete_transient( 'favr_listing_invalid_' . $user_id );
			printf(
				'<div class="favr-notice favr-notice--error" role="alert">%s</div>',
				esc_html(
					sprintf(
						/* translators: %s: field names. */
						__( 'These values weren’t valid and were not saved: %s.', 'favr-directory' ),
						implode( ', ', array_map( 'strval', $invalid ) )
					)
				)
			);
		}
	}

	/**
	 * The tabbed form.
	 *
	 * @param \WP_Post $post    Business.
	 * @param int      $user_id Person.
	 * @param string   $here    Current URL.
	 */
	private static function renderForm( \WP_Post $post, int $user_id, string $here ): void {
		$pending  = PendingChanges::get( $post->ID );
		$renderer = new FieldRenderer(
			'favr',
			array(
				'endpoint' => rest_url( UploadRoute::NAMESPACE . UploadRoute::ROUTE ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'parent'   => $post->ID,
			)
		);
		$items    = self::itemsByTab();
		$tabs     = array_intersect_key( FieldRegistry::tabs(), $items );
		$status   = get_post_status_object( (string) $post->post_status );

		echo '<header class="favr-my-listing__head">';
		printf( '<h2 class="favr-my-listing__title">%s</h2>', esc_html( get_the_title( $post ) ) );
		if ( 'publish' === $post->post_status ) {
			printf( '<a class="favr-btn" href="%1$s">%2$s</a>', esc_url( (string) get_permalink( $post ) ), esc_html__( 'View listing', 'favr-directory' ) );
		} elseif ( $status ) {
			printf( '<span class="favr-badge">%s</span>', esc_html( (string) $status->label ) );
		}
		echo '</header>';

		if ( array() !== $pending ) {
			$labels = array_map( static fn( string $id ): string => (string) ( Values::item( $id )['label'] ?? $id ), array_keys( $pending ) );
			printf(
				'<div class="favr-notice favr-notice--info">%s</div>',
				esc_html(
					sprintf(
						/* translators: %s: field names. */
						__( 'Waiting for review: %s. You can keep editing; the latest version is what our team sees.', 'favr-directory' ),
						implode( ', ', $labels )
					)
				)
			);
		}

		printf( '<form class="favr-my-listing__form" method="post" action="%s">', esc_url( $here ) );
		wp_nonce_field( self::ACTION . '_' . $post->ID, '_favr_listing_nonce' );
		printf(
			'<input type="hidden" name="favr_action" value="%1$s"><input type="hidden" name="favr_listing" value="%2$d"><input type="hidden" name="favr_redirect" value="%3$s">',
			esc_attr( self::ACTION ),
			(int) $post->ID,
			esc_attr( remove_query_arg( self::QV_MSG, $here ) )
		);

		echo '<div class="favr-panel" id="favr-panel"><nav class="favr-tabs" role="tablist" aria-label="' . esc_attr__( 'Listing sections', 'favr-directory' ) . '">';
		$first = true;
		foreach ( $tabs as $tab_id => $tab ) {
			printf(
				'<button type="button" role="tab" class="favr-tab%1$s" id="favr-tab-%2$s" aria-controls="favr-pane-%2$s" aria-selected="%3$s" data-tab="%2$s"><span class="dashicons %4$s" aria-hidden="true"></span><span class="favr-tab__label">%5$s</span></button>',
				$first ? ' is-active' : '',
				esc_attr( (string) $tab_id ),
				$first ? 'true' : 'false',
				esc_attr( (string) $tab['icon'] ),
				esc_html( (string) $tab['label'] )
			);
			$first = false;
		}
		echo '</nav><div class="favr-panes">';

		$first = true;
		foreach ( $tabs as $tab_id => $tab ) {
			printf(
				'<section class="favr-pane%1$s" role="tabpanel" id="favr-pane-%2$s" aria-labelledby="favr-tab-%2$s"%3$s><h3 class="favr-pane__title">%4$s</h3><div class="favr-grid">',
				$first ? ' is-active' : '',
				esc_attr( (string) $tab_id ),
				$first ? '' : ' hidden',
				esc_html( (string) $tab['label'] )
			);
			foreach ( $items[ $tab_id ] as $id => $item ) {
				$is_review = Policy::REVIEW === Policy::access( (string) $id );
				if ( $is_review ) {
					$item['description'] = trim( $item['description'] . ' ' . __( 'Reviewed by our team before it goes live.', 'favr-directory' ) );
				}
				$live  = Values::current( $post->ID, (string) $id );
				$value = Values::effective( (string) $id, isset( $pending[ $id ] ) ? $pending[ $id ]['new'] : $live );
				if ( 'categories' === $id ) {
					$value = array_map( 'strval', (array) $value );
				}
				$renderer->render( $item, $value );
				if ( isset( $pending[ $id ] ) ) {
					$shown = Values::display( (string) $id, $live );
					printf(
						'<div class="favr-pending"><strong>%1$s</strong> %2$s</div>',
						esc_html__( 'Waiting for review.', 'favr-directory' ),
						'' !== $shown ? esc_html__( 'Live now:', 'favr-directory' ) . ' ' . $shown : esc_html__( 'Currently empty.', 'favr-directory' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- display() escapes.
					);
				}
			}
			echo '</div></section>';
			$first = false;
		}
		echo '</div></div>';

		printf(
			'<p class="favr-my-listing__submit"><button type="submit" class="button button-primary">%s</button></p></form>',
			esc_html__( 'Save changes', 'favr-directory' )
		);
	}

	/**
	 * Visible items grouped by tab, core items first in their tab.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	private static function itemsByTab(): array {
		$grouped = array();
		foreach ( Policy::visible() as $id => $item ) {
			if ( 'categories' === $id ) {
				$terms = get_terms(
					array(
						'taxonomy'   => ID::TAX_CATEGORY,
						'hide_empty' => false,
					)
				);
				if ( is_wp_error( $terms ) || array() === $terms ) {
					continue;
				}
				foreach ( $terms as $term ) {
					$item['options'][ (string) $term->term_id ] = $term->name;
				}
				$item['description'] = __( 'Choose up to five.', 'favr-directory' );
			}
			$grouped[ (string) $item['tab'] ][ (string) $id ] = $item;
		}
		return $grouped;
	}

	/** Handle a submission. */
	public function handle(): void {
		if ( ! isset( $_POST['favr_action'] ) || self::ACTION !== $_POST['favr_action'] ) {
			return;
		}
		$post_id  = isset( $_POST['favr_listing'] ) ? absint( $_POST['favr_listing'] ) : 0;
		$redirect = isset( $_POST['favr_redirect'] ) ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['favr_redirect'] ) ), home_url( '/' ) ) : home_url( '/' );
		$redirect = add_query_arg( self::QV_LIST, $post_id, $redirect );
		$user_id  = get_current_user_id();

		$nonce = isset( $_POST['_favr_listing_nonce'] ) ? sanitize_key( wp_unslash( $_POST['_favr_listing_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::ACTION . '_' . $post_id ) ) {
			$this->back( $redirect, 'expired' );
		}
		if ( ! Editors::canEdit( $user_id, $post_id ) ) {
			wp_die( esc_html__( 'You can’t edit this listing.', 'favr-directory' ), '', array( 'response' => 403 ) );
		}
		if ( ! RateLimit::hit( 'listing_save_' . $user_id, self::SAVE_LIMIT ) ) {
			$this->back( $redirect, 'slow' );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- each value is sanitized by Values::sanitize().
		$input  = isset( $_POST['favr'] ) && is_array( $_POST['favr'] ) ? wp_unslash( $_POST['favr'] ) : array();
		$result = self::save( $post_id, $user_id, $input );

		if ( array() !== $result['invalid'] ) {
			set_transient( 'favr_listing_invalid_' . $user_id, $result['invalid'], 120 );
		}
		if ( array() !== $result['proposed'] ) {
			$code = 'pending';
		} else {
			$code = array() !== $result['saved'] ? 'saved' : 'nochange';
		}
		$this->back( $redirect, $code );
	}

	/**
	 * Apply a submission. Public for tests and for other front ends (e.g. an app).
	 *
	 * @param int                  $post_id Business.
	 * @param int                  $user_id Person (must be allowed to edit).
	 * @param array<string, mixed> $input   Raw values keyed by item id.
	 * @return array{saved: list<string>, proposed: list<string>, invalid: list<string>}
	 */
	public static function save( int $post_id, int $user_id, array $input ): array {
		$pending   = PendingChanges::get( $post_id );
		$saved     = array();
		$proposals = array();
		$invalid   = array();

		foreach ( Policy::visible() as $id => $item ) {
			$id    = (string) $id;
			$raw   = $input[ $id ] ?? null;
			$value = Values::sanitize( $id, $raw );

			if ( in_array( $item['type'], array( 'image', 'gallery' ), true ) ) {
				$known = array_merge(
					Values::attachments( $id, Values::current( $post_id, $id ) ),
					Values::attachments( $id, $pending[ $id ]['new'] ?? null )
				);
				$ok    = Uploads::filterUsable( Values::attachments( $id, $value ), $user_id, $post_id, $known );
				if ( 'gallery' === $item['type'] ) {
					$value = $ok;
				} else {
					$value = $ok[0] ?? ( 'cover' === $id ? 0 : '' );
				}
			}

			$typed_but_invalid = is_string( $raw ) && '' !== trim( $raw ) && '' === $value && in_array( $item['type'], array( 'email', 'url', 'tel', 'date' ), true );
			if ( $typed_but_invalid || ( 'business_name' === $id && '' === $value ) ) {
				$invalid[] = (string) $item['label'];
				continue; // Keep what's there rather than wiping it.
			}

			$live    = Values::effective( $id, Values::current( $post_id, $id ) );
			$changed = PendingChanges::differs( $live, Values::effective( $id, $value ) );

			if ( Policy::EDIT === Policy::access( $id ) ) {
				if ( $changed ) {
					Values::apply( $post_id, $id, $value );
					$saved[] = $id;
				}
				continue;
			}

			$was_pending = isset( $pending[ $id ] );
			if ( ( $was_pending && PendingChanges::differs( $pending[ $id ]['new'], $value ) ) || ( ! $was_pending && $changed ) ) {
				$proposals[ $id ] = array(
					'old' => $live,
					'new' => $value,
				);
			}
		}

		$proposed = array() !== $proposals ? PendingChanges::propose( $post_id, $proposals, $user_id ) : array();

		if ( array() !== $saved ) {
			PendingChanges::log( $post_id, 'saved', $saved, $user_id );
			clean_post_cache( $post_id );

			/**
			 * After a representative saved listing changes that went live immediately.
			 *
			 * @param int          $post_id Business.
			 * @param list<string> $saved   Item ids.
			 * @param int          $user_id Person.
			 */
			do_action( 'favr_directory_member_saved', $post_id, $saved, $user_id );
		}
		if ( array() !== $proposed ) {
			PendingChanges::log( $post_id, 'proposed', $proposed, $user_id );
			Notifier::proposed( $post_id, $user_id, $proposed );
		}

		return array(
			'saved'    => $saved,
			'proposed' => $proposed,
			'invalid'  => $invalid,
		);
	}

	/**
	 * Redirect back with a result code.
	 *
	 * @param string $url  URL.
	 * @param string $code Result.
	 */
	private function back( string $url, string $code ): void {
		wp_safe_redirect( add_query_arg( self::QV_MSG, $code, $url ) . '#favr-my-listing', 303 );
		exit;
	}

	/** Current front-end URL (path and query of this request). */
	private static function currentUrl(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
		if ( '' !== $base && str_starts_with( $uri, $base . '/' ) ) {
			$uri = substr( $uri, strlen( $base ) ); // WordPress in a subdirectory: home_url() adds it back.
		}
		return home_url( $uri );
	}
}
