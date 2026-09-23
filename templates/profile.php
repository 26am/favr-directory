<?php
/**
 * Business profile.
 *
 * Override by copying to yourtheme/favr-directory/profile.php.
 *
 * @package FavrDirectory
 *
 * @var FavrDirectory\Model\Business $business    Business.
 * @var string                       $context     "page" (our templates) or "content" (theme/page builder owns the page).
 * @var string                       $description Rendered description HTML.
 */

use FavrDirectory\Frontend\Icons;
use FavrDirectory\Frontend\Template;
use FavrDirectory\Model\Business;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;

defined( 'ABSPATH' ) || exit;

$favr_is_page      = 'page' === $context;
$favr_name         = wp_strip_all_tags( $business->name() );
$favr_cover        = $business->coverId();
$favr_logo         = $business->logoId();
$favr_tagline      = $business->text( 'tagline' );
$favr_org          = $business->text( 'organization' );
$favr_languages    = $business->languages();
$favr_categories   = $business->categories();
$favr_level        = $business->level();
$favr_phone        = $business->text( 'phone' );
$favr_phone_alt    = $business->text( 'phone_alt' );
$favr_email        = $business->publicEmail();
$favr_website      = $business->text( 'website' );
$favr_booking      = $business->text( 'booking_url' );
$favr_address      = $business->addressLines();
$favr_directions   = $business->hasAddress() ? $business->directionsUrl() : '';
$favr_open         = '1' === Settings::get( 'show_open_now' ) ? $business->isOpenNow() : null;
$favr_deal         = Settings::sectionEnabled( 'deal' ) ? $business->deal() : null;
$favr_highlights   = Settings::sectionEnabled( 'highlights' ) ? $business->highlights() : array();
$favr_gallery      = Settings::sectionEnabled( 'gallery' ) ? $business->gallery() : array();
$favr_links        = Settings::sectionEnabled( 'links' ) ? $business->links() : array();
$favr_video        = Settings::sectionEnabled( 'video' ) ? $business->text( 'video_url' ) : '';
$favr_contact      = $business->text( 'contact_name' );
$favr_archive      = get_post_type_archive_link( ID::POST_TYPE );
$favr_level_clr    = $favr_level ? (string) get_term_meta( $favr_level->term_id, ID::TERM_META_COLOR, true ) : '';
$favr_since        = $business->text( 'member_since' );
$favr_year         = (int) $business->field( 'year_established' );
$favr_employees    = $business->text( 'employees' );
$favr_has_about    = '' !== trim( wp_strip_all_tags( $description ) );
$favr_show_contact = Settings::sectionEnabled( 'contact' ) && ( $favr_address || '' !== $favr_phone || '' !== $favr_email || '' !== $favr_website || '' !== $favr_contact || '' !== $business->text( 'service_area' ) );
?>
<article class="favr-profile favr-profile--<?php echo esc_attr( $context ); ?><?php echo $favr_cover && $favr_is_page ? ' has-cover' : ''; ?>" data-favr-tz="<?php echo esc_attr( wp_timezone_string() ); ?>">

	<?php if ( $favr_is_page && $favr_archive ) : ?>
		<a class="favr-back" href="<?php echo esc_url( $favr_archive ); ?>"><?php echo Icons::svg( 'back', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( (string) Settings::get( 'directory_title' ) ); ?></a>
	<?php endif; ?>

	<header class="favr-profile__hero">
		<?php if ( $favr_is_page && $favr_cover ) : ?>
			<div class="favr-profile__cover">
				<?php
				echo wp_get_attachment_image(
					$favr_cover,
					'full',
					false,
					array(
						'alt'           => '',
						'fetchpriority' => 'high',
						'sizes'         => '(max-width: 1200px) 100vw, 1200px',
					)
				);
				?>
			</div>
		<?php endif; ?>

		<div class="favr-profile__identity">
			<?php if ( $favr_logo ) : ?>
				<div class="favr-profile__logo"><?php echo wp_get_attachment_image( $favr_logo, 'medium', false, array( 'alt' => sprintf( /* translators: %s: business name. */ __( '%s logo', 'favr-directory' ), $favr_name ) ) ); ?></div>
			<?php elseif ( $favr_is_page ) : ?>
				<div class="favr-profile__logo favr-profile__logo--blank" aria-hidden="true"><span><?php echo esc_html( mb_strtoupper( mb_substr( $favr_name, 0, 1 ) ) ); ?></span></div>
			<?php endif; ?>

			<div class="favr-profile__titles">
				<div class="favr-profile__badges">
					<?php if ( $business->isFeatured() ) : ?>
						<span class="favr-badge favr-badge--featured"><?php echo Icons::svg( 'star', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Featured', 'favr-directory' ); ?></span>
					<?php endif; ?>
					<?php if ( $favr_level && Settings::sectionEnabled( 'membership' ) ) : ?>
						<span class="favr-badge favr-badge--level"<?php echo '' !== $favr_level_clr ? ' style="--favr-level:' . esc_attr( $favr_level_clr ) . '"' : ''; ?>><?php echo esc_html( $favr_level->name ); ?></span>
					<?php endif; ?>
					<?php if ( null !== $favr_open ) : ?>
						<span class="favr-status <?php echo $favr_open ? 'is-open' : 'is-closed'; ?>" data-open="<?php esc_attr_e( 'Open now', 'favr-directory' ); ?>" data-closed="<?php esc_attr_e( 'Closed now', 'favr-directory' ); ?>" data-favr-hours="<?php echo esc_attr( (string) wp_json_encode( $business->hours() ) ); ?>">
							<?php echo $favr_open ? esc_html__( 'Open now', 'favr-directory' ) : esc_html__( 'Closed now', 'favr-directory' ); ?>
						</span>
					<?php endif; ?>
				</div>

				<?php if ( $favr_is_page ) : ?>
					<h1 class="favr-profile__name"><?php echo esc_html( $favr_name ); ?></h1>
				<?php endif; ?>

				<?php if ( '' !== $favr_org ) : ?>
					<p class="favr-profile__org"><?php echo esc_html( $favr_org ); ?></p>
				<?php endif; ?>

				<?php if ( '' !== $favr_tagline ) : ?>
					<p class="favr-profile__tagline"><?php echo esc_html( $favr_tagline ); ?></p>
				<?php endif; ?>

				<?php if ( $favr_languages ) : ?>
					<p class="favr-profile__langs">
						<span class="favr-profile__langs-label"><?php esc_html_e( 'Speaks', 'favr-directory' ); ?></span>
						<?php foreach ( $favr_languages as $favr_language ) : ?>
							<span class="favr-lang"><?php echo esc_html( $favr_language ); ?></span>
						<?php endforeach; ?>
					</p>
				<?php endif; ?>

				<?php if ( $favr_categories ) : ?>
					<ul class="favr-chips" aria-label="<?php esc_attr_e( 'Categories', 'favr-directory' ); ?>">
						<?php foreach ( $favr_categories as $favr_cat ) : ?>
							<?php $favr_cat_link = get_term_link( $favr_cat ); ?>
							<li><a class="favr-chip" href="<?php echo esc_url( is_wp_error( $favr_cat_link ) ? '#' : $favr_cat_link ); ?>"><?php echo esc_html( $favr_cat->name ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( '' !== $favr_phone || '' !== $favr_website || '' !== $favr_directions || '' !== $favr_email || '' !== $favr_booking ) : ?>
			<div class="favr-profile__actions">
				<?php if ( '' !== $favr_booking ) : ?>
					<a class="favr-btn favr-btn--primary" href="<?php echo esc_url( $favr_booking ); ?>" target="_blank" rel="noopener"><?php echo Icons::svg( 'calendar', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Book now', 'favr-directory' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $favr_phone ) : ?>
					<a class="favr-btn<?php echo '' === $favr_booking ? ' favr-btn--primary' : ''; ?>" href="<?php echo esc_attr( Business::telHref( $favr_phone ) ); ?>"><?php echo Icons::svg( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Call', 'favr-directory' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $favr_website ) : ?>
					<a class="favr-btn" href="<?php echo esc_url( $favr_website ); ?>" target="_blank" rel="noopener"><?php echo Icons::svg( 'globe', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Website', 'favr-directory' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $favr_directions ) : ?>
					<a class="favr-btn" href="<?php echo esc_url( $favr_directions ); ?>" target="_blank" rel="noopener"><?php echo Icons::svg( 'directions', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Directions', 'favr-directory' ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $favr_email ) : ?>
					<a class="favr-btn" href="<?php echo esc_attr( 'mailto:' . antispambot( $favr_email ) ); ?>"><?php echo Icons::svg( 'email', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Email', 'favr-directory' ); ?></a>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</header>

	<div class="favr-profile__layout">
		<div class="favr-profile__main">

			<?php if ( $favr_deal ) : ?>
				<section class="favr-deal" aria-labelledby="favr-deal-title-<?php echo (int) $business->id(); ?>">
					<div class="favr-deal__icon"><?php echo Icons::svg( 'tag', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></div>
					<div class="favr-deal__body">
						<p class="favr-deal__eyebrow"><?php esc_html_e( 'Member deal', 'favr-directory' ); ?></p>
						<h2 class="favr-deal__title" id="favr-deal-title-<?php echo (int) $business->id(); ?>"><?php echo esc_html( $favr_deal['title'] ); ?></h2>
						<?php if ( '' !== $favr_deal['description'] ) : ?>
							<p><?php echo nl2br( esc_html( $favr_deal['description'] ) ); ?></p>
						<?php endif; ?>
						<div class="favr-deal__meta">
							<?php if ( '' !== $favr_deal['code'] ) : ?>
								<button type="button" class="favr-deal__code" data-favr-copy="<?php echo esc_attr( $favr_deal['code'] ); ?>" title="<?php esc_attr_e( 'Copy code', 'favr-directory' ); ?>"><?php echo esc_html( $favr_deal['code'] ); ?></button>
							<?php endif; ?>
							<?php if ( '' !== $favr_deal['expires'] ) : ?>
								<span class="favr-deal__expires">
									<?php
									/* translators: %s: date. */
									echo esc_html( sprintf( __( 'Valid until %s', 'favr-directory' ), date_i18n( get_option( 'date_format' ), (int) strtotime( $favr_deal['expires'] ) ) ) );
									?>
								</span>
							<?php endif; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( $favr_has_about ) : ?>
				<section class="favr-section favr-about">
					<h2 class="favr-section__title">
						<?php
						/* translators: %s: business name. */
						echo esc_html( sprintf( __( 'About %s', 'favr-directory' ), $favr_name ) );
						?>
					</h2>
					<div class="favr-about__content"><?php echo $description; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the_content output. ?></div>
				</section>
			<?php elseif ( '' !== $business->summary() ) : ?>
				<section class="favr-section favr-about">
					<p><?php echo esc_html( $business->summary() ); ?></p>
				</section>
			<?php endif; ?>

			<?php if ( $favr_highlights ) : ?>
				<section class="favr-section">
					<h2 class="favr-section__title"><?php esc_html_e( 'Highlights', 'favr-directory' ); ?></h2>
					<ul class="favr-highlights">
						<?php foreach ( $favr_highlights as $favr_label ) : ?>
							<li><?php echo Icons::svg( 'check', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $favr_label ); ?></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php if ( $favr_gallery ) : ?>
				<section class="favr-section">
					<h2 class="favr-section__title"><?php esc_html_e( 'Photos', 'favr-directory' ); ?></h2>
					<div class="favr-gallery" data-favr-gallery>
						<?php foreach ( $favr_gallery as $favr_index => $favr_image ) : ?>
							<?php $favr_caption = wp_get_attachment_caption( $favr_image ); ?>
							<a class="favr-gallery__item" href="<?php echo esc_url( (string) wp_get_attachment_image_url( $favr_image, 'large' ) ); ?>" data-favr-lightbox="<?php echo (int) $favr_index; ?>" data-caption="<?php echo esc_attr( (string) $favr_caption ); ?>">
								<?php
								echo wp_get_attachment_image(
									$favr_image,
									'medium_large',
									false,
									array(
										'loading' => 'lazy',
										'alt'     => (string) ( get_post_meta( $favr_image, '_wp_attachment_image_alt', true ) ?: sprintf( /* translators: 1: business name, 2: photo number. */ __( '%1$s photo %2$d', 'favr-directory' ), $favr_name, $favr_index + 1 ) ),
									)
								);
								?>
							</a>
						<?php endforeach; ?>
					</div>
				</section>
			<?php endif; ?>

			<?php if ( '' !== $favr_video ) : ?>
				<?php $favr_embed = isset( $GLOBALS['wp_embed'] ) ? $GLOBALS['wp_embed']->shortcode( array(), $favr_video ) : ''; ?>
				<?php if ( is_string( $favr_embed ) && str_contains( $favr_embed, '<iframe' ) ) : ?>
					<section class="favr-section">
						<h2 class="favr-section__title"><?php esc_html_e( 'Video', 'favr-directory' ); ?></h2>
						<div class="favr-video"><?php echo $favr_embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core oEmbed (allow-listed providers). ?></div>
					</section>
				<?php endif; ?>
			<?php endif; ?>

			<?php if ( $favr_links ) : ?>
				<section class="favr-section">
					<h2 class="favr-section__title"><?php esc_html_e( 'Links', 'favr-directory' ); ?></h2>
					<ul class="favr-links">
						<?php foreach ( $favr_links as $favr_link ) : ?>
							<li><a href="<?php echo esc_url( $favr_link['url'] ); ?>" target="_blank" rel="noopener"><?php echo Icons::svg( 'link', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $favr_link['label'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endif; ?>
		</div>

		<aside class="favr-profile__side">
			<?php if ( $favr_show_contact ) : ?>
				<section class="favr-box">
					<h2 class="favr-box__title"><?php esc_html_e( 'Contact', 'favr-directory' ); ?></h2>
					<ul class="favr-contact">
						<?php if ( $favr_address ) : ?>
							<li>
								<?php echo Icons::svg( 'pin', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<div>
									<address><?php echo implode( '<br>', array_map( 'esc_html', $favr_address ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped per line. ?></address>
									<?php if ( '' !== $favr_directions ) : ?>
										<a class="favr-contact__sub" href="<?php echo esc_url( $favr_directions ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get directions', 'favr-directory' ); ?></a>
									<?php endif; ?>
								</div>
							</li>
						<?php endif; ?>
						<?php if ( '' !== $business->text( 'service_area' ) ) : ?>
							<li>
								<?php echo Icons::svg( 'directions', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<div><span class="favr-contact__label"><?php esc_html_e( 'Service area', 'favr-directory' ); ?></span><?php echo esc_html( $business->text( 'service_area' ) ); ?></div>
							</li>
						<?php endif; ?>
						<?php if ( '' !== $favr_phone ) : ?>
							<li>
								<?php echo Icons::svg( 'phone', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<div>
									<a href="<?php echo esc_attr( Business::telHref( $favr_phone ) ); ?>"><?php echo esc_html( $favr_phone ); ?></a>
									<?php if ( '' !== $favr_phone_alt ) : ?>
										<br><a href="<?php echo esc_attr( Business::telHref( $favr_phone_alt ) ); ?>"><?php echo esc_html( $favr_phone_alt ); ?></a>
									<?php endif; ?>
								</div>
							</li>
						<?php endif; ?>
						<?php if ( '' !== $favr_email ) : ?>
							<li>
								<?php echo Icons::svg( 'email', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<div><a href="<?php echo esc_attr( 'mailto:' . antispambot( $favr_email ) ); ?>"><?php echo esc_html( antispambot( $favr_email ) ); ?></a></div>
							</li>
						<?php endif; ?>
						<?php if ( '' !== $favr_website ) : ?>
							<li>
								<?php echo Icons::svg( 'globe', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<div><a href="<?php echo esc_url( $favr_website ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $business->websiteLabel() ?: $favr_website ); ?></a></div>
							</li>
						<?php endif; ?>
						<?php if ( '' !== $favr_contact ) : ?>
							<li>
								<?php echo Icons::svg( 'user', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?>
								<div>
									<?php echo esc_html( $favr_contact ); ?>
									<?php if ( '' !== $business->text( 'contact_title' ) ) : ?>
										<span class="favr-contact__sub"><?php echo esc_html( $business->text( 'contact_title' ) ); ?></span>
									<?php endif; ?>
								</div>
							</li>
						<?php endif; ?>
					</ul>
				</section>
			<?php endif; ?>

			<?php
			if ( Settings::sectionEnabled( 'hours' ) ) {
				echo Template::render( 'parts/hours', array( 'business' => $business ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes.
			}
			if ( Settings::sectionEnabled( 'map' ) ) {
				echo Template::render( 'parts/map', array( 'business' => $business ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes.
			}
			if ( Settings::sectionEnabled( 'social' ) ) {
				echo Template::render( 'parts/social', array( 'business' => $business ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- template escapes.
			}
			?>

			<?php if ( Settings::sectionEnabled( 'membership' ) && ( '' !== $favr_since || $favr_year > 0 || '' !== $favr_employees ) ) : ?>
				<section class="favr-box">
					<h2 class="favr-box__title"><?php esc_html_e( 'At a glance', 'favr-directory' ); ?></h2>
					<dl class="favr-facts">
						<?php if ( '' !== $favr_since ) : ?>
							<div><dt><?php esc_html_e( 'Member since', 'favr-directory' ); ?></dt><dd><?php echo esc_html( date_i18n( 'Y', (int) strtotime( $favr_since ) ) ); ?></dd></div>
						<?php endif; ?>
						<?php if ( $favr_year > 0 ) : ?>
							<div><dt><?php esc_html_e( 'Established', 'favr-directory' ); ?></dt><dd><?php echo esc_html( (string) $favr_year ); ?></dd></div>
						<?php endif; ?>
						<?php if ( '' !== $favr_employees ) : ?>
							<div><dt><?php esc_html_e( 'Employees', 'favr-directory' ); ?></dt><dd><?php echo esc_html( str_replace( '-', '–', $favr_employees ) ); ?></dd></div>
						<?php endif; ?>
					</dl>
				</section>
			<?php endif; ?>
		</aside>
	</div>

	<?php
	/**
	 * After the business profile markup.
	 *
	 * @param FavrDirectory\Model\Business $business Business.
	 * @param string                       $context  Render context.
	 */
	do_action( 'favr_directory_after_profile', $business, $context );
	?>
</article>
