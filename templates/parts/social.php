<?php
/**
 * Social links.
 *
 * Override by copying to yourtheme/favr-directory/parts/social.php.
 *
 * @package FavrDirectory
 *
 * @var FavrDirectory\Model\Business $business Business.
 */

use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Frontend\Icons;

defined( 'ABSPATH' ) || exit;

$favr_social = $business->social();
if ( array() === $favr_social ) {
	return;
}
$favr_labels = FieldRegistry::socialNetworks();
?>
<section class="favr-box favr-social">
	<h2 class="favr-box__title"><?php esc_html_e( 'Follow', 'favr-directory' ); ?></h2>
	<ul class="favr-social__list">
		<?php foreach ( $favr_social as $favr_network => $favr_url ) : ?>
			<li>
				<a class="favr-social__link favr-social__link--<?php echo esc_attr( $favr_network ); ?>" href="<?php echo esc_url( $favr_url ); ?>" target="_blank" rel="noopener me">
					<?php echo Icons::svg( $favr_network, 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<span class="screen-reader-text"><?php echo esc_html( sprintf( /* translators: 1: business name, 2: network name. */ __( '%1$s on %2$s', 'favr-directory' ), wp_strip_all_tags( $business->name() ), $favr_labels[ $favr_network ] ?? $favr_network ) ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
