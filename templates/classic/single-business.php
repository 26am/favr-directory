<?php
/**
 * Classic-theme single business template.
 *
 * Override by copying to yourtheme/favr-directory/classic/single-business.php.
 *
 * @package FavrDirectory
 */

use FavrDirectory\Frontend\Profile;
use FavrDirectory\Model\Business;

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="site-main favr-classic">
	<div class="favr-classic__inner">
		<?php
		while ( have_posts() ) {
			the_post();
			$favr_business = Business::find( (int) get_the_ID() );
			if ( $favr_business ) {
				echo Profile::render( $favr_business, 'page' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes.
			}
		}
		?>
	</div>
</main>
<?php
get_footer();
