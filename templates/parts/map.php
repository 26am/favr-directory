<?php
/**
 * Map box.
 *
 * Override by copying to yourtheme/favr-directory/parts/map.php.
 *
 * @package FavrDirectory
 *
 * @var FavrDirectory\Model\Business $business Business.
 */

use FavrDirectory\Support\Settings;

defined( 'ABSPATH' ) || exit;

if ( 'google' !== Settings::get( 'map_provider' ) || ! $business->hasAddress() || ! $business->flag( 'show_map' ) ) {
	return;
}
$favr_query = $business->mapQuery();
if ( '' === $favr_query ) {
	return;
}
$favr_src = 'https://www.google.com/maps?q=' . rawurlencode( $favr_query ) . '&z=15&output=embed';
?>
<section class="favr-box favr-map">
	<div class="favr-map__frame">
		<iframe
			src="<?php echo esc_url( $favr_src ); ?>"
			title="<?php echo esc_attr( sprintf( /* translators: %s: business name. */ __( 'Map showing the location of %s', 'favr-directory' ), wp_strip_all_tags( $business->name() ) ) ); ?>"
			loading="lazy"
			referrerpolicy="no-referrer-when-downgrade"
			allowfullscreen></iframe>
	</div>
</section>
