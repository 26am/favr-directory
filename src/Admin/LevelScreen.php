<?php
/**
 * Membership level term fields.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Admin;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * Levels carry a display order (drives "higher tiers first" sorting in the directory) and a
 * badge color. Their list is in that order, with the colour in front of each name. Favr Members
 * draws the same list when it runs without the Directory (its Admin\LevelScreen).
 */
final class LevelScreen {

	/** Hook. */
	public function hook(): void {
		add_action( ID::TAX_LEVEL . '_add_form_fields', array( $this, 'addFields' ) );
		add_action( ID::TAX_LEVEL . '_edit_form_fields', array( $this, 'editFields' ) );
		add_action( 'created_' . ID::TAX_LEVEL, array( $this, 'save' ) );
		add_action( 'edited_' . ID::TAX_LEVEL, array( $this, 'save' ) );
		add_filter( 'manage_edit-' . ID::TAX_LEVEL . '_columns', array( $this, 'columns' ) );
		add_filter( 'manage_' . ID::TAX_LEVEL . '_custom_column', array( $this, 'column' ), 10, 3 );
		// After WordPress has escaped the name for the list (`_wp_specialchars`, priority 30).
		add_filter( 'term_name', array( $this, 'name' ), 40, 2 );
		add_filter( 'get_terms', array( $this, 'order' ), 10, 3 );
		add_filter( 'manage_edit-' . ID::TAX_LEVEL . '_sortable_columns', array( $this, 'sortable' ) );
	}

	/** Fields on the "Add level" form. */
	public function addFields(): void {
		wp_nonce_field( 'favr_level_meta', '_favr_level_nonce' );
		printf(
			'<div class="form-field"><label for="favr-level-order">%1$s</label><input type="number" id="favr-level-order" name="favr_level_order" value="10" min="0" step="1"><p>%2$s</p></div>',
			esc_html__( 'Display order', 'favr-directory' ),
			esc_html__( 'Lower numbers are higher tiers and are listed first.', 'favr-directory' )
		);
		printf(
			'<div class="form-field"><label for="favr-level-color">%1$s</label><input type="text" id="favr-level-color" name="favr_level_color" value="#0f766e" class="favr-color"></div>',
			esc_html__( 'Badge color', 'favr-directory' )
		);
	}

	/**
	 * Fields on the "Edit level" form.
	 *
	 * @param \WP_Term $term Term.
	 */
	public function editFields( \WP_Term $term ): void {
		wp_nonce_field( 'favr_level_meta', '_favr_level_nonce' );
		printf(
			'<tr class="form-field"><th scope="row"><label for="favr-level-order">%1$s</label></th><td><input type="number" id="favr-level-order" name="favr_level_order" value="%2$d" min="0" step="1"><p class="description">%3$s</p></td></tr>',
			esc_html__( 'Display order', 'favr-directory' ),
			(int) get_term_meta( $term->term_id, ID::TERM_META_ORDER, true ),
			esc_html__( 'Lower numbers are higher tiers and are listed first.', 'favr-directory' )
		);
		printf(
			'<tr class="form-field"><th scope="row"><label for="favr-level-color">%1$s</label></th><td><input type="text" id="favr-level-color" name="favr_level_color" value="%2$s" class="favr-color"></td></tr>',
			esc_html__( 'Badge color', 'favr-directory' ),
			esc_attr( (string) get_term_meta( $term->term_id, ID::TERM_META_COLOR, true ) )
		);
	}

	/**
	 * Save term meta.
	 *
	 * @param int $term_id Term id.
	 */
	public function save( int $term_id ): void {
		if ( ! isset( $_POST['_favr_level_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_favr_level_nonce'] ) ), 'favr_level_meta' ) ) {
			return;
		}
		if ( ! current_user_can( ID::CAP_MANAGE_TERMS ) ) {
			return;
		}
		if ( isset( $_POST['favr_level_order'] ) ) {
			update_term_meta( $term_id, ID::TERM_META_ORDER, absint( wp_unslash( $_POST['favr_level_order'] ) ) );
		}
		if ( isset( $_POST['favr_level_color'] ) ) {
			$color = sanitize_hex_color( sanitize_text_field( wp_unslash( $_POST['favr_level_color'] ) ) );
			update_term_meta( $term_id, ID::TERM_META_COLOR, $color ? $color : '' );
		}
	}

	/**
	 * Columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'description' === $key ) {
				continue;
			}
			$out[ $key ] = $label;
			if ( 'name' === $key ) {
				$out['favr_order'] = __( 'Order', 'favr-directory' );
			}
		}
		return $out;
	}

	/**
	 * The Order cell: the number, and nothing else. (The colour sits before the name.)
	 *
	 * @param mixed $content Content.
	 * @param mixed $column  Column.
	 * @param mixed $term_id Term id.
	 */
	public function column( $content, $column, $term_id ): string {
		return 'favr_order' === $column ? (string) (int) get_term_meta( (int) $term_id, ID::TERM_META_ORDER, true ) : (string) $content;
	}

	/**
	 * A level's name in the list, after its colour: the same dot as in the Membership level box
	 * and the Directory list's Level column. A level without a colour gets none.
	 *
	 * @param mixed $name The name as WordPress prints it, already escaped.
	 * @param mixed $term The term.
	 */
	public function name( $name, $term = null ): string {
		if ( ! is_object( $term ) || ! isset( $term->term_id, $term->taxonomy ) || ID::TAX_LEVEL !== $term->taxonomy ) {
			return (string) $name;
		}
		$dot = LevelChip::swatch( (string) get_term_meta( (int) $term->term_id, ID::TERM_META_COLOR, true ) );
		return '' !== $dot ? $dot . ' ' . trim( (string) $name ) : (string) $name;
	}

	/**
	 * The Membership levels list in the order its Order column shows, unless a column heading
	 * was clicked. WordPress fetches every level for this list (levels are hierarchical) and
	 * lays them out in the order it gets them, so sorting what it fetched sorts the screen.
	 * Nothing is queried again: the order values are term meta WordPress has already loaded.
	 *
	 * @param mixed $terms      What get_terms() found.
	 * @param mixed $taxonomies The taxonomies asked for.
	 * @param mixed $args       The query.
	 * @return mixed The same levels; on the levels screen, by order.
	 */
	public function order( $terms, $taxonomies = array(), $args = array() ) {
		if ( ! is_array( $terms ) || array( ID::TAX_LEVEL ) !== array_values( (array) $taxonomies ) || 'all' !== ( is_array( $args ) ? ( $args['fields'] ?? 'all' ) : 'all' ) ) {
			return $terms;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only reads whether a column heading was clicked.
		if ( ! $screen || 'edit-' . ID::TAX_LEVEL !== $screen->id || ! empty( $_GET['orderby'] ) ) {
			return $terms;
		}
		$orders = array();
		foreach ( $terms as $term ) {
			if ( ! is_object( $term ) || ! isset( $term->term_id, $term->name ) ) {
				return $terms;
			}
			$orders[ (int) $term->term_id ] = (int) get_term_meta( (int) $term->term_id, ID::TERM_META_ORDER, true );
		}
		return self::byOrder( $terms, $orders );
	}

	/**
	 * WordPress marks Name as the sorted column of every term list it has just opened (an arrow,
	 * and aria-sort for screen readers). This list opens in the levels' own order, so the mark
	 * would contradict the rows: Name keeps its link and loses the mark until it is clicked.
	 *
	 * @param mixed $columns Sortable columns: key => { orderby, descending first, label, table caption, opening order }.
	 * @return mixed
	 */
	public function sortable( $columns ) {
		if ( is_array( $columns ) && isset( $columns['name'] ) && is_array( $columns['name'] ) ) {
			$columns['name'] = array_slice( $columns['name'], 0, 4 );
		}
		return $columns;
	}

	/**
	 * Levels by their order value (none counts as 0, which is what the column shows), then by
	 * name.
	 *
	 * @param array<int|string, object> $terms  Levels (term_id, name).
	 * @param array<int, int>           $orders Term id => order.
	 * @return list<object>
	 */
	public static function byOrder( array $terms, array $orders ): array {
		$terms = array_values( $terms );
		usort(
			$terms,
			static fn( object $a, object $b ): int => array( $orders[ (int) $a->term_id ] ?? 0, strtolower( (string) $a->name ) ) <=> array( $orders[ (int) $b->term_id ] ?? 0, strtolower( (string) $b->name ) )
		);
		return $terms;
	}
}
