<?php
/**
 * Directory card.
 *
 * Override by copying to yourtheme/favr-directory/parts/card.php.
 *
 * @package FavrDirectory
 *
 * @var FavrDirectory\Model\Business $business  Business.
 * @var bool                         $show_open Show the open-now badge.
 */

use FavrDirectory\Frontend\Icons;
use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;

defined( 'ABSPATH' ) || exit;

$favr_url        = $business->url();
$favr_name       = $business->name();
$favr_cover      = $business->coverId();
$favr_logo       = $business->logoId();
$favr_categories = $business->categories();
$favr_level      = $business->level();
$favr_phone      = $business->text( 'phone' );
$favr_locality   = $business->locality();
$favr_summary    = $business->text( 'tagline' ) ?: $business->summary();
$favr_hours      = $business->hours();
$favr_open       = $show_open ? $business->isOpenNow() : null;
$favr_color      = $favr_level ? (string) get_term_meta( $favr_level->term_id, ID::TERM_META_COLOR, true ) : '';
?>
<article class="favr-card<?php echo $business->isFeatured() ? ' is-featured' : ''; ?>">
	<div class="favr-card__media">
		<?php if ( $favr_cover ) : ?>
			<?php
			echo wp_get_attachment_image(
				$favr_cover,
				'medium_large',
				false,
				array(
					'class'   => 'favr-card__cover',
					'loading' => 'lazy',
					'alt'     => '',
				)
			);
			?>
		<?php elseif ( $favr_logo ) : ?>
			<div class="favr-card__cover favr-card__cover--logo">
				<?php
				echo wp_get_attachment_image(
					$favr_logo,
					'medium',
					false,
					array(
						'loading' => 'lazy',
						'alt'     => '',
					)
				);
				?>
			</div>
		<?php else : ?>
			<div class="favr-card__cover favr-card__cover--blank" aria-hidden="true"><span><?php echo esc_html( mb_strtoupper( mb_substr( wp_strip_all_tags( $favr_name ), 0, 1 ) ) ); ?></span></div>
		<?php endif; ?>

		<?php if ( $business->isFeatured() ) : ?>
			<span class="favr-badge favr-badge--featured"><?php echo Icons::svg( 'star', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Featured', 'favr-directory' ); ?></span>
		<?php endif; ?>
	</div>

	<div class="favr-card__body">
		<div class="favr-card__head">
			<?php if ( $favr_logo && $favr_cover ) : ?>
				<span class="favr-card__logo">
				<?php
				echo wp_get_attachment_image(
					$favr_logo,
					'thumbnail',
					false,
					array(
						'loading' => 'lazy',
						'alt'     => '',
					)
				);
				?>
				</span>
			<?php endif; ?>
			<div class="favr-card__heading">
				<h3 class="favr-card__title"><a href="<?php echo esc_url( $favr_url ); ?>" class="favr-card__link"><?php echo esc_html( wp_strip_all_tags( $favr_name ) ); ?></a></h3>
				<?php if ( $favr_categories ) : ?>
					<p class="favr-card__cats"><?php echo esc_html( implode( ' · ', wp_list_pluck( array_slice( $favr_categories, 0, 3 ), 'name' ) ) ); ?></p>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( '' !== $favr_summary ) : ?>
			<p class="favr-card__summary"><?php echo esc_html( wp_trim_words( $favr_summary, 22 ) ); ?></p>
		<?php endif; ?>

		<ul class="favr-card__meta">
			<?php if ( '' !== $favr_locality ) : ?>
				<li><?php echo Icons::svg( 'pin', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $favr_locality ); ?></span></li>
			<?php endif; ?>
			<?php if ( '' !== $favr_phone ) : ?>
				<li><?php echo Icons::svg( 'phone', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><a href="<?php echo esc_attr( Business::telHref( $favr_phone ) ); ?>" class="favr-card__phone"><?php echo esc_html( $favr_phone ); ?></a></li>
			<?php endif; ?>
		</ul>

		<?php if ( null !== $favr_open || $favr_level ) : ?>
			<div class="favr-card__badges">
				<?php if ( null !== $favr_open ) : ?>
					<span class="favr-status <?php echo $favr_open ? 'is-open' : 'is-closed'; ?>" data-open="<?php esc_attr_e( 'Open now', 'favr-directory' ); ?>" data-closed="<?php esc_attr_e( 'Closed now', 'favr-directory' ); ?>" data-favr-hours="<?php echo esc_attr( (string) wp_json_encode( $favr_hours ) ); ?>">
						<?php echo $favr_open ? esc_html__( 'Open now', 'favr-directory' ) : esc_html__( 'Closed now', 'favr-directory' ); ?>
					</span>
				<?php endif; ?>
				<?php if ( $favr_level ) : ?>
					<span class="favr-badge favr-badge--level"<?php echo '' !== $favr_color ? ' style="--favr-level:' . esc_attr( $favr_color ) . '"' : ''; ?>><?php echo esc_html( $favr_level->name ); ?></span>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
