<?php
/**
 * Directory listing.
 *
 * Override by copying to yourtheme/favr-directory/directory.php.
 *
 * @package FavrDirectory
 *
 * @var array<string, mixed>                      $atts        Normalized attributes.
 * @var array<string, mixed>                      $args        Effective query args.
 * @var WP_Query                                  $query       Results query.
 * @var list<FavrDirectory\Model\Business>        $businesses  Results.
 * @var bool                                      $interactive Whether filters are shown.
 * @var bool                                      $filtered    Whether visitor filters are active.
 * @var list<array{slug: string, name: string, depth: int, count: int}> $categories Category options.
 * @var list<WP_Term>                             $levels      Level options.
 * @var list<string>                              $letters     Letters with results.
 * @var string                                    $action      Base URL.
 * @var string                                    $pagination  Pagination HTML.
 * @var bool                                      $show_open   Show open-now badges.
 */

use FavrDirectory\Frontend\Directory;
use FavrDirectory\Frontend\Icons;
use FavrDirectory\Frontend\Template;
use FavrDirectory\Schema\Identifiers as ID;

defined( 'ABSPATH' ) || exit;

$favr_total   = (int) $query->found_posts;
$favr_page    = max( 1, (int) $args['page'] );
$favr_from    = $favr_total ? ( ( $favr_page - 1 ) * (int) $atts['per_page'] ) + 1 : 0;
$favr_to      = min( $favr_total, $favr_from + count( $businesses ) - 1 );
$favr_request = Directory::request();

// Keep non-directory query args (e.g. ?page_id=12 on plain permalinks) when the form submits.
$favr_hidden = array();
$favr_query  = (string) wp_parse_url( $action, PHP_URL_QUERY );
if ( '' !== $favr_query ) {
	wp_parse_str( $favr_query, $favr_hidden );
}
?>
<div class="favr-dir favr-dir--<?php echo esc_attr( (string) $atts['layout'] ); ?>" data-favr-dir data-layout="<?php echo esc_attr( (string) $atts['layout'] ); ?>" data-favr-tz="<?php echo esc_attr( wp_timezone_string() ); ?>">

	<?php if ( '' !== $atts['title'] ) : ?>
		<h2 class="favr-dir__title"><?php echo esc_html( (string) $atts['title'] ); ?></h2>
	<?php endif; ?>

	<?php if ( $interactive ) : ?>
		<form class="favr-dir__toolbar" method="get" action="<?php echo esc_url( $action ); ?>" role="search" data-favr-form>
			<?php foreach ( $favr_hidden as $favr_key => $favr_value ) : ?>
				<?php if ( is_scalar( $favr_value ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( (string) $favr_key ); ?>" value="<?php echo esc_attr( (string) $favr_value ); ?>">
				<?php endif; ?>
			<?php endforeach; ?>

			<?php if ( $atts['search'] ) : ?>
				<label class="favr-search">
					<span class="screen-reader-text"><?php esc_html_e( 'Search the directory', 'favr-directory' ); ?></span>
					<?php echo Icons::svg( 'search', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
					<input type="search" name="<?php echo esc_attr( ID::QV_SEARCH ); ?>" value="<?php echo esc_attr( (string) $args['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search by name, service or keyword…', 'favr-directory' ); ?>" autocomplete="off" data-favr-search>
				</label>

				<?php if ( $categories ) : ?>
					<label class="favr-select">
						<span class="screen-reader-text"><?php esc_html_e( 'Category', 'favr-directory' ); ?></span>
						<select name="<?php echo esc_attr( ID::QV_CATEGORY ); ?>" data-favr-autosubmit>
							<option value=""><?php esc_html_e( 'All categories', 'favr-directory' ); ?></option>
							<?php foreach ( $categories as $favr_cat ) : ?>
								<option value="<?php echo esc_attr( $favr_cat['slug'] ); ?>" <?php selected( $favr_cat['slug'], (string) $args['category'] ); ?>>
									<?php echo esc_html( str_repeat( '— ', $favr_cat['depth'] ) . $favr_cat['name'] ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</label>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $levels ) : ?>
				<label class="favr-select">
					<span class="screen-reader-text"><?php esc_html_e( 'Membership level', 'favr-directory' ); ?></span>
					<select name="<?php echo esc_attr( ID::QV_LEVEL ); ?>" data-favr-autosubmit>
						<option value=""><?php esc_html_e( 'All levels', 'favr-directory' ); ?></option>
						<?php foreach ( $levels as $favr_level ) : ?>
							<option value="<?php echo esc_attr( $favr_level->slug ); ?>" <?php selected( $favr_level->slug, (string) $args['level'] ); ?>><?php echo esc_html( $favr_level->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endif; ?>

			<button type="submit" class="favr-btn favr-btn--primary"><?php esc_html_e( 'Search', 'favr-directory' ); ?></button>
		</form>

		<?php if ( $letters ) : ?>
			<nav class="favr-letters" aria-label="<?php esc_attr_e( 'Browse by first letter', 'favr-directory' ); ?>">
				<a href="
				<?php
				echo esc_url(
					Directory::url(
						array(
							ID::QV_LETTER => '',
							ID::QV_PAGE   => '',
						)
					)
				);
				?>
							" class="favr-letters__item<?php echo '' === $favr_request['letter'] ? ' is-active' : ''; ?>"<?php echo '' === $favr_request['letter'] ? ' aria-current="true"' : ''; ?> data-favr-link><?php esc_html_e( 'All', 'favr-directory' ); ?></a>
				<?php foreach ( array_merge( range( 'A', 'Z' ), array( '#' ) ) as $favr_letter ) : ?>
					<?php if ( in_array( $favr_letter, $letters, true ) ) : ?>
						<a href="
						<?php
						echo esc_url(
							Directory::url(
								array(
									ID::QV_LETTER => $favr_letter,
									ID::QV_PAGE   => '',
								)
							)
						);
						?>
									" class="favr-letters__item<?php echo $favr_letter === $favr_request['letter'] ? ' is-active' : ''; ?>"<?php echo $favr_letter === $favr_request['letter'] ? ' aria-current="true"' : ''; ?> data-favr-link><?php echo esc_html( $favr_letter ); ?></a>
					<?php else : ?>
						<span class="favr-letters__item is-disabled" aria-hidden="true"><?php echo esc_html( $favr_letter ); ?></span>
					<?php endif; ?>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>

	<div class="favr-dir__results" data-favr-results aria-live="polite" aria-busy="false">
		<?php if ( $interactive ) : ?>
			<div class="favr-dir__status">
				<p class="favr-dir__count">
					<?php
					if ( $favr_total ) {
						echo esc_html(
							sprintf(
								/* translators: 1: first result number, 2: last result number, 3: total results. */
								_n( 'Showing %1$d–%2$d of %3$d business', 'Showing %1$d–%2$d of %3$d businesses', $favr_total, 'favr-directory' ),
								$favr_from,
								$favr_to,
								$favr_total
							)
						);
					}
					?>
					<?php if ( $filtered ) : ?>
						<a class="favr-dir__clear" href="<?php echo esc_url( $action ); ?>" data-favr-link><?php esc_html_e( 'Clear filters', 'favr-directory' ); ?></a>
					<?php endif; ?>
				</p>
				<div class="favr-layout-toggle" role="group" aria-label="<?php esc_attr_e( 'Layout', 'favr-directory' ); ?>">
					<button type="button" class="favr-layout-toggle__btn" data-favr-layout="grid" aria-pressed="<?php echo 'grid' === $atts['layout'] ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'Grid view', 'favr-directory' ); ?>">
						<?php echo Icons::svg( 'grid', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span class="screen-reader-text"><?php esc_html_e( 'Grid view', 'favr-directory' ); ?></span>
					</button>
					<button type="button" class="favr-layout-toggle__btn" data-favr-layout="list" aria-pressed="<?php echo 'list' === $atts['layout'] ? 'true' : 'false'; ?>" title="<?php esc_attr_e( 'List view', 'favr-directory' ); ?>">
						<?php echo Icons::svg( 'list', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span class="screen-reader-text"><?php esc_html_e( 'List view', 'favr-directory' ); ?></span>
					</button>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $businesses ) : ?>
			<div class="favr-dir__grid">
				<?php
				// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- the card template escapes its own output.
				foreach ( $businesses as $favr_business ) {
					echo Template::render(
						'parts/card',
						array(
							'business'  => $favr_business,
							'show_open' => $show_open,
						)
					);
				}
				// phpcs:enable
				?>
			</div>
			<?php if ( '' !== $pagination ) : ?>
				<nav class="favr-dir__pagination" aria-label="<?php esc_attr_e( 'Directory pages', 'favr-directory' ); ?>">
					<?php echo wp_kses_post( $pagination ); ?>
				</nav>
			<?php endif; ?>
		<?php else : ?>
			<div class="favr-dir__empty">
				<?php echo Icons::svg( 'search', 40 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
				<p class="favr-dir__empty-title"><?php esc_html_e( 'No businesses found', 'favr-directory' ); ?></p>
				<?php if ( $filtered ) : ?>
					<p><?php esc_html_e( 'Try a different search term or category.', 'favr-directory' ); ?></p>
					<a class="favr-btn" href="<?php echo esc_url( $action ); ?>" data-favr-link><?php esc_html_e( 'Show all businesses', 'favr-directory' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</div>
