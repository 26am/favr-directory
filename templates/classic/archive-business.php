<?php
/**
 * Classic-theme directory and category archive template.
 *
 * Override by copying to yourtheme/favr-directory/classic/archive-business.php.
 *
 * @package FavrDirectory
 */

use FavrDirectory\Frontend\Directory;
use FavrDirectory\Support\Settings;

defined( 'ABSPATH' ) || exit;

get_header();
$favr_term = is_tax() ? get_queried_object() : null;
?>
<main id="primary" class="site-main favr-classic">
	<div class="favr-classic__inner">
		<header class="favr-classic__header">
			<h1 class="favr-classic__title"><?php echo esc_html( $favr_term instanceof WP_Term ? $favr_term->name : (string) Settings::get( 'directory_title' ) ); ?></h1>
			<?php if ( $favr_term instanceof WP_Term && '' !== $favr_term->description ) : ?>
				<div class="favr-classic__intro"><?php echo wp_kses_post( wpautop( $favr_term->description ) ); ?></div>
			<?php endif; ?>
		</header>
		<?php echo Directory::render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes. ?>
	</div>
</main>
<?php
get_footer();
