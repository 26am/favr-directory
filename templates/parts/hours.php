<?php
/**
 * Opening hours box.
 *
 * Override by copying to yourtheme/favr-directory/parts/hours.php.
 *
 * @package FavrDirectory
 *
 * @var FavrDirectory\Model\Business $business Business.
 */

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Vendor\FavrCore\Support\Hours;

defined( 'ABSPATH' ) || exit;

$favr_hours = $business->hours();
if ( array() === $favr_hours && ! $business->flag( 'by_appointment' ) ) {
	return;
}
$favr_format = (string) get_option( 'time_format', 'g:i a' );
$favr_today  = Hours::dayKey( new DateTimeImmutable( 'now', wp_timezone() ) );
?>
<section class="favr-box favr-hours">
	<h2 class="favr-box__title"><?php esc_html_e( 'Hours', 'favr-directory' ); ?></h2>
	<?php if ( array() !== $favr_hours ) : ?>
		<table class="favr-hours__table">
			<tbody>
				<?php foreach ( FieldRegistry::days() as $favr_day => $favr_label ) : ?>
					<?php $favr_row = $favr_hours[ $favr_day ] ?? array( 'status' => 'closed' ); ?>
					<tr data-day="<?php echo esc_attr( $favr_day ); ?>"<?php echo $favr_day === $favr_today ? ' class="is-today"' : ''; ?>>
						<th scope="row"><?php echo esc_html( $favr_label ); ?></th>
						<td>
							<?php
							if ( 'open' === $favr_row['status'] ) {
								echo esc_html( Hours::formatTime( $favr_row['open'], $favr_format ) . ' – ' . Hours::formatTime( $favr_row['close'], $favr_format ) );
							} elseif ( '24h' === $favr_row['status'] ) {
								esc_html_e( 'Open 24 hours', 'favr-directory' );
							} else {
								echo '<span class="favr-hours__closed">' . esc_html__( 'Closed', 'favr-directory' ) . '</span>';
							}
							?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
	<?php if ( $business->flag( 'by_appointment' ) ) : ?>
		<p class="favr-hours__note"><?php esc_html_e( 'Also available by appointment.', 'favr-directory' ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $business->text( 'hours_note' ) ) : ?>
		<p class="favr-hours__note"><?php echo esc_html( $business->text( 'hours_note' ) ); ?></p>
	<?php endif; ?>
</section>
