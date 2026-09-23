<?php
/**
 * The "All Businesses" list table.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Admin;

use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;

/**
 * Adds scannable columns (logo, contact, city, featured star, completeness), filters and a
 * one-click featured toggle to the native list table.
 */
final class ListScreen {

	/** Hook. */
	public function hook(): void {
		add_filter( 'manage_' . ID::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . ID::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
		add_filter( 'manage_edit-' . ID::POST_TYPE . '_sortable_columns', array( $this, 'sortable' ) );
		add_action( 'restrict_manage_posts', array( $this, 'filters' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'applyFilters' ) );
		add_filter( 'post_row_actions', array( $this, 'rowActions' ), 10, 2 );
		add_action( 'wp_ajax_favr_toggle_featured', array( $this, 'ajaxToggleFeatured' ) );
	}

	/**
	 * Column set.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$out = array();
		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$out['favr_logo']     = '<span class="screen-reader-text">' . esc_html__( 'Logo', 'favr-directory' ) . '</span>';
				$out[ $key ]          = __( 'Business', 'favr-directory' );
				$out['favr_featured'] = '<span class="dashicons dashicons-star-filled" title="' . esc_attr__( 'Featured', 'favr-directory' ) . '"></span><span class="screen-reader-text">' . esc_html__( 'Featured', 'favr-directory' ) . '</span>';
				continue;
			}
			if ( 'author' === $key || 'comments' === $key ) {
				continue;
			}
			if ( 'date' === $key ) {
				$out['favr_contact']  = __( 'Contact', 'favr-directory' );
				$out['favr_city']     = __( 'City', 'favr-directory' );
				$out['favr_complete'] = __( 'Profile', 'favr-directory' );
			}
			$out[ $key ] = $label;
		}
		if ( isset( $out[ 'taxonomy-' . ID::TAX_CATEGORY ] ) ) {
			$out[ 'taxonomy-' . ID::TAX_CATEGORY ] = __( 'Categories', 'favr-directory' );
		}
		if ( isset( $out[ 'taxonomy-' . ID::TAX_LEVEL ] ) ) {
			$out[ 'taxonomy-' . ID::TAX_LEVEL ] = __( 'Level', 'favr-directory' );
		}
		return $out;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post id.
	 */
	public function column( string $column, int $post_id ): void {
		$business = Business::find( $post_id );
		if ( ! $business ) {
			return;
		}
		switch ( $column ) {
			case 'favr_logo':
				$logo = $business->logoId() ?: $business->coverId();
				if ( $logo ) {
					echo wp_get_attachment_image( $logo, array( 48, 48 ), false, array( 'class' => 'favr-list-logo' ) );
				} else {
					printf( '<span class="favr-list-logo favr-list-logo--empty" aria-hidden="true">%s</span>', esc_html( mb_strtoupper( mb_substr( $business->name(), 0, 1 ) ) ) );
				}
				break;

			case 'favr_featured':
				$on = $business->isFeatured();
				printf(
					'<button type="button" class="favr-star%1$s" data-id="%2$d" aria-pressed="%3$s" title="%4$s"><span class="dashicons dashicons-star-%5$s"></span><span class="screen-reader-text">%4$s</span></button>',
					$on ? ' is-on' : '',
					(int) $post_id,
					$on ? 'true' : 'false',
					esc_attr__( 'Toggle featured', 'favr-directory' ),
					$on ? 'filled' : 'empty'
				);
				break;

			case 'favr_contact':
				$phone = $business->text( 'phone' );
				$email = $business->text( 'email' );
				if ( '' !== $phone ) {
					printf( '<div><span class="dashicons dashicons-phone"></span> %s</div>', esc_html( $phone ) );
				}
				if ( '' !== $email ) {
					printf( '<div><span class="dashicons dashicons-email"></span> <a href="mailto:%1$s">%2$s</a></div>', esc_attr( $email ), esc_html( $email ) );
				}
				if ( '' === $phone && '' === $email ) {
					echo '<span aria-hidden="true">—</span>';
				}
				break;

			case 'favr_city':
				echo esc_html( '' !== $business->locality() ? $business->locality() : '—' );
				break;

			case 'favr_complete':
				$score = $business->completeness();
				$tone  = EditScreen::tone( $score );
				printf(
					'<div class="favr-meter favr-meter--%1$s" title="%2$s"><span style="width:%3$d%%"></span></div><small>%3$d%%</small>',
					esc_attr( $tone ),
					/* translators: %d: percent. */
					esc_attr( sprintf( __( 'Profile %d%% complete', 'favr-directory' ), $score ) ),
					(int) $score
				);
				break;
		}
	}

	/**
	 * Sortable columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function sortable( array $columns ): array {
		$columns['favr_city']     = 'favr_city';
		$columns['favr_featured'] = 'favr_featured';
		return $columns;
	}

	/**
	 * Filter dropdowns above the table.
	 *
	 * @param string $post_type Post type.
	 * @param string $which     top|bottom.
	 */
	public function filters( string $post_type, string $which = 'top' ): void {
		if ( ID::POST_TYPE !== $post_type || 'top' !== $which ) {
			return;
		}
		foreach ( array( ID::TAX_CATEGORY, ID::TAX_LEVEL ) as $taxonomy ) {
			$tax = get_taxonomy( $taxonomy );
			if ( ! $tax ) {
				continue;
			}
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
			$selected = isset( $_GET[ $taxonomy ] ) ? sanitize_title( wp_unslash( $_GET[ $taxonomy ] ) ) : '';
			wp_dropdown_categories(
				array(
					/* translators: %s: taxonomy name. */
					'show_option_all' => sprintf( __( 'All %s', 'favr-directory' ), $tax->labels->name ),
					'taxonomy'        => $taxonomy,
					'name'            => $taxonomy,
					'value_field'     => 'slug',
					'selected'        => $selected,
					'hierarchical'    => true,
					'hide_empty'      => false,
					'show_count'      => false,
				)
			);
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$featured = isset( $_GET['favr_featured'] ) ? sanitize_key( wp_unslash( $_GET['favr_featured'] ) ) : '';
		printf(
			'<select name="favr_featured"><option value="">%s</option><option value="1"%s>%s</option></select>',
			esc_html__( 'Featured & regular', 'favr-directory' ),
			selected( $featured, '1', false ),
			esc_html__( 'Featured only', 'favr-directory' )
		);
	}

	/**
	 * Apply the featured filter and meta sorting.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function applyFilters( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || ID::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		if ( isset( $_GET['favr_featured'] ) && '1' === $_GET['favr_featured'] ) {
			$meta   = (array) $query->get( 'meta_query' );
			$meta[] = array(
				'key'   => ID::meta( 'featured' ),
				'value' => '1',
			);
			$query->set( 'meta_query', $meta );
		}

		$orderby = (string) $query->get( 'orderby' );
		if ( 'favr_city' === $orderby || 'favr_featured' === $orderby ) {
			$key = 'favr_city' === $orderby ? 'city' : 'featured';
			$query->set(
				'meta_query',
				array_merge(
					(array) $query->get( 'meta_query' ),
					array(
						'relation' => 'AND',
						// EXISTS OR NOT EXISTS keeps rows without the meta; the named
						// clause (resolved at any depth) is what ORDER BY targets.
						array(
							'relation'  => 'OR',
							'favr_sort' => array(
								'key'     => ID::meta( $key ),
								'compare' => 'EXISTS',
							),
							array(
								'key'     => ID::meta( $key ),
								'compare' => 'NOT EXISTS',
							),
						),
					)
				)
			);
			$query->set(
				'orderby',
				array(
					'favr_sort' => $query->get( 'order' ) ?: 'ASC',
					'title'     => 'ASC',
				)
			);
		}
	}

	/**
	 * Row actions.
	 *
	 * @param array<string, string> $actions Actions.
	 * @param \WP_Post              $post    Post.
	 * @return array<string, string>
	 */
	public function rowActions( array $actions, \WP_Post $post ): array {
		if ( ID::POST_TYPE === $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );
		}
		return $actions;
	}

	/** AJAX: flip the featured flag. */
	public function ajaxToggleFeatured(): void {
		check_ajax_referer( ID::NONCE_AJAX, 'nonce' );
		$post_id = isset( $_POST['id'] ) ? absint( wp_unslash( $_POST['id'] ) ) : 0;
		if ( ! $post_id || ! Business::find( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this business.', 'favr-directory' ) ), 403 );
		}
		$on = '1' !== get_post_meta( $post_id, ID::meta( 'featured' ), true );
		if ( $on ) {
			update_post_meta( $post_id, ID::meta( 'featured' ), '1' );
		} else {
			delete_post_meta( $post_id, ID::meta( 'featured' ) );
		}
		wp_send_json_success( array( 'featured' => $on ) );
	}
}
