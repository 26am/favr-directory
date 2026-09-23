<?php
/**
 * The business add/edit screen.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Admin;

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Fields\Sanitizer;
use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Completeness;

/**
 * Businesses are structured records, not articles, so the edit screen uses the classic form
 * with one tabbed "Business Profile" panel directly under the title (the description editor
 * lives in its first tab) plus a slim sidebar. Opt back into the block editor with the
 * `favr_directory_use_block_editor` filter.
 */
final class EditScreen {

	/**
	 * Field input renderer.
	 *
	 * @var FieldRenderer
	 */
	private FieldRenderer $renderer;

	/** Constructor. */
	public function __construct() {
		$this->renderer = new FieldRenderer();
	}

	/** Hook. */
	public function hook(): void {
		add_filter( 'use_block_editor_for_post_type', array( $this, 'useBlockEditor' ), 10, 2 );
		add_action( 'load-post.php', array( $this, 'prepareScreen' ) );
		add_action( 'load-post-new.php', array( $this, 'prepareScreen' ) );
		add_filter( 'enter_title_here', array( $this, 'titlePlaceholder' ), 10, 2 );
		add_action( 'edit_form_after_title', array( $this, 'renderPanel' ) );
		add_action( 'add_meta_boxes_' . ID::POST_TYPE, array( $this, 'metaBoxes' ) );
		add_action( 'save_post_' . ID::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_filter( 'post_updated_messages', array( $this, 'messages' ) );
		add_action( 'admin_notices', array( $this, 'saveNotices' ) );
	}

	/**
	 * Classic editor for businesses unless a site opts in to the block editor.
	 *
	 * @param bool   $use_block Current decision.
	 * @param string $post_type Post type.
	 */
	public function useBlockEditor( bool $use_block, string $post_type ): bool {
		if ( ID::POST_TYPE !== $post_type ) {
			return $use_block;
		}
		/**
		 * Use the block editor for businesses (the profile panel then appears below it).
		 *
		 * @param bool $use Default false.
		 */
		return (bool) apply_filters( 'favr_directory_use_block_editor', false );
	}

	/** The description editor moves into the Overview tab, so hide the default one. */
	public function prepareScreen(): void {
		$screen = get_current_screen();
		if ( ! $screen || ID::POST_TYPE !== $screen->post_type || $this->useBlockEditor( false, ID::POST_TYPE ) ) {
			return;
		}
		// Request-scoped: REST and the front end still see full editor support.
		remove_post_type_support( ID::POST_TYPE, 'editor' );
	}

	/**
	 * Title placeholder.
	 *
	 * @param string   $text Placeholder.
	 * @param \WP_Post $post Post.
	 */
	public function titlePlaceholder( string $text, \WP_Post $post ): string {
		return ID::POST_TYPE === $post->post_type ? __( 'Business name', 'favr-directory' ) : $text;
	}

	/**
	 * Sidebar boxes; drop boxes whose job the panel does.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function metaBoxes( \WP_Post $post ): void {
		remove_meta_box( 'postexcerpt', ID::POST_TYPE, 'normal' );
		remove_meta_box( 'postcustom', ID::POST_TYPE, 'normal' );
		remove_meta_box( 'slugdiv', ID::POST_TYPE, 'normal' );
		remove_meta_box( 'authordiv', ID::POST_TYPE, 'normal' );

		add_meta_box( 'favr-health', __( 'Listing health', 'favr-directory' ), array( $this, 'renderHealth' ), ID::POST_TYPE, 'side', 'default' );
		add_meta_box( 'favr-level', __( 'Membership level', 'favr-directory' ), array( $this, 'renderLevel' ), ID::POST_TYPE, 'side', 'default' );

		if ( $this->useBlockEditor( false, ID::POST_TYPE ) ) {
			add_meta_box( 'favr-profile', __( 'Business Profile', 'favr-directory' ), array( $this, 'renderPanel' ), ID::POST_TYPE, 'normal', 'high' );
		}
	}

	/**
	 * The tabbed profile panel.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderPanel( \WP_Post $post ): void {
		if ( ID::POST_TYPE !== $post->post_type ) {
			return;
		}
		$block_editor = $this->useBlockEditor( false, ID::POST_TYPE );
		wp_nonce_field( ID::NONCE_META, '_favr_nonce' );

		$tabs     = FieldRegistry::tabs();
		$business = new Business( $post );

		echo '<div class="favr-panel" id="favr-panel">';
		echo '<nav class="favr-tabs" role="tablist" aria-label="' . esc_attr__( 'Business profile sections', 'favr-directory' ) . '">';
		$first = true;
		foreach ( $tabs as $tab_id => $tab ) {
			printf(
				'<button type="button" role="tab" class="favr-tab%1$s" id="favr-tab-%2$s" aria-controls="favr-pane-%2$s" aria-selected="%3$s" data-tab="%2$s"><span class="dashicons %4$s" aria-hidden="true"></span><span class="favr-tab__label">%5$s</span><span class="favr-tab__dot" aria-hidden="true"></span></button>',
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
				'<section class="favr-pane%1$s" role="tabpanel" id="favr-pane-%2$s" aria-labelledby="favr-tab-%2$s"%3$s><h2 class="favr-pane__title">%4$s</h2><div class="favr-grid">',
				$first ? ' is-active' : '',
				esc_attr( (string) $tab_id ),
				$first ? '' : ' hidden',
				esc_html( (string) $tab['label'] )
			);

			if ( 'overview' === $tab_id && ! $block_editor ) {
				$this->renderDescriptionEditor( $post );
			}

			foreach ( FieldRegistry::forTab( (string) $tab_id ) as $field ) {
				$this->renderer->render( $field, $business->field( (string) $field['id'] ) );
			}

			/**
			 * Render extra markup at the end of a profile tab.
			 *
			 * @param string   $tab_id Tab id.
			 * @param \WP_Post $post   Post being edited.
			 */
			do_action( 'favr_directory_after_tab', $tab_id, $post );

			echo '</div></section>';
			$first = false;
		}
		echo '</div></div>';
	}

	/**
	 * The long description, in the Overview tab.
	 *
	 * @param \WP_Post $post Post.
	 */
	private function renderDescriptionEditor( \WP_Post $post ): void {
		echo '<div class="favr-field favr-w-full favr-field--editor">';
		printf( '<label class="favr-field__label" for="content">%s</label>', esc_html__( 'About this business', 'favr-directory' ) );
		wp_editor(
			$post->post_content,
			'content',
			array(
				'textarea_name'    => 'content',
				'textarea_rows'    => 10,
				'editor_height'    => 240,
				'media_buttons'    => true,
				'drag_drop_upload' => true,
			)
		);
		echo '</div>';
	}

	/**
	 * Completeness meter + suggestions.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderHealth( \WP_Post $post ): void {
		$business = new Business( $post );
		$score    = $business->completeness();
		$labels   = $this->missingLabels();
		$missing  = array_slice( $business->missingFields(), 0, 4 );

		printf(
			'<div class="favr-health" data-score="%1$d" data-tone="%4$s"><div class="favr-health__ring" style="--favr-score:%1$d"><span class="favr-health__value">%1$d%%</span></div><div class="favr-health__text"><strong class="favr-health__headline">%2$s</strong><p class="favr-health__hint">%3$s</p></div></div>',
			(int) $score,
			esc_html( $this->healthHeadline( $score ) ),
			esc_html__( 'Complete listings get more clicks.', 'favr-directory' ),
			esc_attr( self::tone( $score ) )
		);

		echo '<ul class="favr-health__todo">';
		foreach ( $missing as $key ) {
			$field = FieldRegistry::get( $key );
			printf(
				'<li><button type="button" class="button-link favr-goto" data-tab="%1$s" data-field="%2$s">%3$s</button></li>',
				esc_attr( $field ? (string) $field['tab'] : 'overview' ),
				esc_attr( $key ),
				/* translators: %s: field name, e.g. "Logo". */
				esc_html( sprintf( __( 'Add %s', 'favr-directory' ), strtolower( $labels[ $key ] ?? $key ) ) )
			);
		}
		echo '</ul>';
	}

	/**
	 * Single-choice membership level.
	 *
	 * @param \WP_Post $post Post.
	 */
	public function renderLevel( \WP_Post $post ): void {
		$levels  = get_terms(
			array(
				'taxonomy'   => ID::TAX_LEVEL,
				'hide_empty' => false,
				// EXISTS OR NOT EXISTS: levels without an order still appear (sorted last).
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- tiny taxonomy.
					'relation'   => 'OR',
					'favr_order' => array(
						'key'     => ID::TERM_META_ORDER,
						'compare' => 'EXISTS',
						'type'    => 'NUMERIC',
					),
					array(
						'key'     => ID::TERM_META_ORDER,
						'compare' => 'NOT EXISTS',
					),
				),
				'orderby'    => 'favr_order',
				'order'      => 'ASC',
			)
		);
		$current = wp_get_object_terms( $post->ID, ID::TAX_LEVEL, array( 'fields' => 'ids' ) );
		$current = is_array( $current ) && isset( $current[0] ) ? (int) $current[0] : 0;

		echo '<div class="favr-levels">';
		printf(
			'<label class="favr-level"><input type="radio" name="favr_level" value="0"%s> <span>%s</span></label>',
			checked( 0, $current, false ),
			esc_html__( 'None', 'favr-directory' )
		);
		if ( is_array( $levels ) ) {
			foreach ( $levels as $level ) {
				$color = (string) get_term_meta( $level->term_id, ID::TERM_META_COLOR, true );
				printf(
					'<label class="favr-level"><input type="radio" name="favr_level" value="%1$d"%2$s> <span class="favr-level__swatch" style="background:%3$s"></span> <span>%4$s</span></label>',
					(int) $level->term_id,
					checked( (int) $level->term_id, $current, false ),
					esc_attr( '' !== $color ? $color : '#94a3b8' ),
					esc_html( $level->name )
				);
			}
		}
		echo '</div>';
		if ( current_user_can( ID::CAP_MANAGE_TERMS ) ) {
			printf(
				'<p class="favr-levels__manage"><a href="%s">%s</a></p>',
				esc_url( admin_url( 'edit-tags.php?taxonomy=' . ID::TAX_LEVEL . '&post_type=' . ID::POST_TYPE ) ),
				esc_html__( 'Manage levels', 'favr-directory' )
			);
		}
	}

	/**
	 * Persist the panel. Every field is sanitized by type; empty values delete the meta.
	 *
	 * @param int      $post_id Post id.
	 * @param \WP_Post $post    Post.
	 */
	public function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['_favr_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_favr_nonce'] ) ), ID::NONCE_META ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$input    = isset( $_POST['favr'] ) && is_array( $_POST['favr'] ) ? wp_unslash( $_POST['favr'] ) : array();
		$warnings = array();

		foreach ( FieldRegistry::all() as $id => $field ) {
			$raw   = $input[ $id ] ?? null;
			$value = Sanitizer::sanitize( $field, $raw );

			if ( in_array( $field['type'], array( 'email', 'url' ), true ) && is_string( $raw ) && '' !== trim( $raw ) && '' === $value ) {
				$warnings[] = $field['label'];
			}

			if ( Sanitizer::isEmpty( $field, $value ) ) {
				delete_post_meta( $post_id, ID::meta( $id ) );
			} else {
				update_post_meta( $post_id, ID::meta( $id ), $value );
			}
		}

		if ( isset( $_POST['favr_level'] ) && current_user_can( 'edit_' . ID::CAP_TYPE_PLURAL ) ) {
			$level = absint( wp_unslash( $_POST['favr_level'] ) );
			wp_set_object_terms( $post_id, $level > 0 ? array( $level ) : array(), ID::TAX_LEVEL );
		}

		if ( $warnings ) {
			set_transient( 'favr_save_warnings_' . get_current_user_id(), $warnings, 60 );
		}

		/**
		 * After a business profile is saved from the edit screen.
		 *
		 * @param int      $post_id Post id.
		 * @param \WP_Post $post    Post.
		 */
		do_action( 'favr_directory_business_saved', $post_id, $post );
	}

	/** Tell the editor which values could not be saved. */
	public function saveNotices(): void {
		$key      = 'favr_save_warnings_' . get_current_user_id();
		$warnings = get_transient( $key );
		if ( ! is_array( $warnings ) || array() === $warnings ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: comma-separated field names. */
					__( 'Some values were not valid and were not saved: %s. Please check them and update again.', 'favr-directory' ),
					implode( ', ', array_map( 'strval', $warnings ) )
				)
			)
		);
	}

	/**
	 * Friendly update messages.
	 *
	 * @param array<string, array<int, string>> $messages Messages.
	 * @return array<string, array<int, string>>
	 */
	public function messages( array $messages ): array {
		$post = get_post();
		$link = $post ? sprintf( ' <a href="%s">%s</a>', esc_url( (string) get_permalink( $post ) ), esc_html__( 'View listing', 'favr-directory' ) ) : '';

		$messages[ ID::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Business updated.', 'favr-directory' ) . $link,
			4  => __( 'Business updated.', 'favr-directory' ),
			6  => __( 'Business published.', 'favr-directory' ) . $link,
			7  => __( 'Business saved.', 'favr-directory' ),
			8  => __( 'Business submitted.', 'favr-directory' ),
			9  => __( 'Business scheduled.', 'favr-directory' ),
			10 => __( 'Business draft updated.', 'favr-directory' ),
		);
		return $messages;
	}

	/**
	 * Human labels for completeness keys.
	 *
	 * @return array<string, string>
	 */
	public function missingLabels(): array {
		$labels = array(
			'title'       => __( 'Business name', 'favr-directory' ),
			'description' => __( 'Description', 'favr-directory' ),
			'category'    => __( 'Category', 'favr-directory' ),
			'cover'       => __( 'Cover photo', 'favr-directory' ),
		);
		foreach ( FieldRegistry::all() as $id => $field ) {
			$labels[ $id ] = (string) $field['label'];
		}
		return $labels;
	}

	/**
	 * Weights used by the live meter in JS.
	 *
	 * @return array<string, int>
	 */
	public static function weights(): array {
		$weights = Completeness::CORE_WEIGHTS;
		foreach ( FieldRegistry::all() as $id => $field ) {
			if ( (int) $field['weight'] > 0 ) {
				$weights[ $id ] = (int) $field['weight'];
			}
		}
		return $weights;
	}

	/**
	 * Color tone for a score (shared with the list table meter).
	 *
	 * @param int $score Score.
	 */
	public static function tone( int $score ): string {
		return $score >= 90 ? 'good' : ( $score >= 60 ? 'ok' : 'low' );
	}

	/**
	 * Headline for a score.
	 *
	 * @param int $score Score.
	 */
	private function healthHeadline( int $score ): string {
		if ( $score >= 90 ) {
			return __( 'Looking great!', 'favr-directory' );
		}
		if ( $score >= 60 ) {
			return __( 'Almost there', 'favr-directory' );
		}
		return __( 'Needs more details', 'favr-directory' );
	}
}
