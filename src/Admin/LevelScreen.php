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
 * badge color.
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
	 * Column content.
	 *
	 * @param string $content Content.
	 * @param string $column  Column.
	 * @param int    $term_id Term id.
	 */
	public function column( string $content, string $column, int $term_id ): string {
		if ( 'favr_order' !== $column ) {
			return $content;
		}
		$color = (string) get_term_meta( $term_id, ID::TERM_META_COLOR, true );
		return sprintf(
			'<span class="favr-level__swatch" style="background:%s"></span> %d',
			esc_attr( '' !== $color ? $color : '#94a3b8' ),
			(int) get_term_meta( $term_id, ID::TERM_META_ORDER, true )
		);
	}
}
