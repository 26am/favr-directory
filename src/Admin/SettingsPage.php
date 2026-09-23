<?php
/**
 * Directory settings screen.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Admin;

use FavrDirectory\Model\Registrar;
use FavrDirectory\Schema\Identifiers as ID;
use FavrDirectory\Support\Settings;

/**
 * Directory → Settings, built on the Settings API.
 */
final class SettingsPage {

	public const SLUG = 'favr-directory-settings';

	/** Hook. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( FAVR_DIRECTORY_FILE ), array( $this, 'actionLinks' ) );
	}

	/** Submenu entry. */
	public function menu(): void {
		add_submenu_page(
			'edit.php?post_type=' . ID::POST_TYPE,
			__( 'Directory Settings', 'favr-directory' ),
			__( 'Settings', 'favr-directory' ),
			ID::CAP_SETTINGS,
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * "Settings" link on the Plugins screen.
	 *
	 * @param array<string, string> $links Links.
	 * @return array<string, string>
	 */
	public function actionLinks( array $links ): array {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Settings', 'favr-directory' ) )
		);
		return $links;
	}

	/** Settings page URL. */
	public static function url(): string {
		return admin_url( 'edit.php?post_type=' . ID::POST_TYPE . '&page=' . self::SLUG );
	}

	/** Register the option. */
	public function register(): void {
		register_setting(
			'favr_directory',
			ID::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => Settings::defaults(),
			)
		);
	}

	/**
	 * Sanitize the submitted settings.
	 *
	 * @param mixed $input Raw.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input    = is_array( $input ) ? $input : array();
		$defaults = Settings::defaults();
		$old      = Settings::all();

		$out = array(
			'directory_slug'    => sanitize_title( (string) ( $input['directory_slug'] ?? '' ) ) ?: $defaults['directory_slug'],
			'category_slug'     => sanitize_title( (string) ( $input['category_slug'] ?? '' ) ) ?: $defaults['category_slug'],
			'directory_title'   => sanitize_text_field( (string) ( $input['directory_title'] ?? '' ) ) ?: $defaults['directory_title'],
			'organization_name' => sanitize_text_field( (string) ( $input['organization_name'] ?? '' ) ),
			'per_page'          => min( 100, max( 1, absint( $input['per_page'] ?? 12 ) ) ),
			'layout'            => in_array( $input['layout'] ?? '', array( 'grid', 'list' ), true ) ? $input['layout'] : 'grid',
			'map_provider'      => in_array( $input['map_provider'] ?? '', array( 'google', 'none' ), true ) ? $input['map_provider'] : 'google',
			'show_open_now'     => empty( $input['show_open_now'] ) ? '0' : '1',
			'show_letters'      => empty( $input['show_letters'] ) ? '0' : '1',
			'accent_color'      => (string) sanitize_hex_color( (string) ( $input['accent_color'] ?? '' ) ),
			'sections'          => array_values( array_intersect( array_keys( Settings::sectionChoices() ), array_map( 'strval', (array) ( $input['sections'] ?? array() ) ) ) ),
			'delete_data'       => empty( $input['delete_data'] ) ? '0' : '1',
		);

		if ( $out['directory_slug'] !== $old['directory_slug'] || $out['category_slug'] !== $old['category_slug'] ) {
			update_option( ID::OPTION_FLUSH, 1 );
		}
		Settings::flush();
		return $out;
	}

	/** Render. */
	public function render(): void {
		if ( ! current_user_can( ID::CAP_SETTINGS ) ) {
			return;
		}
		$s       = Settings::all();
		$option  = ID::OPTION_SETTINGS;
		$archive = get_post_type_archive_link( ID::POST_TYPE );
		?>
		<div class="wrap favr-settings">
			<h1><?php esc_html_e( 'Directory Settings', 'favr-directory' ); ?></h1>

			<div class="favr-settings__layout">
				<form method="post" action="options.php" class="favr-settings__form">
					<?php settings_fields( 'favr_directory' ); ?>

					<div class="favr-card">
						<h2><?php esc_html_e( 'Directory page', 'favr-directory' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="favr-title"><?php esc_html_e( 'Directory title', 'favr-directory' ); ?></label></th>
								<td><input type="text" id="favr-title" class="regular-text" name="<?php echo esc_attr( $option ); ?>[directory_title]" value="<?php echo esc_attr( (string) $s['directory_title'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><label for="favr-slug"><?php esc_html_e( 'Directory URL', 'favr-directory' ); ?></label></th>
								<td>
									<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input type="text" id="favr-slug" class="regular-text code favr-slug-input" name="<?php echo esc_attr( $option ); ?>[directory_slug]" value="<?php echo esc_attr( (string) $s['directory_slug'] ); ?>"><code>/</code>
									<?php if ( $archive ) : ?>
										<p class="description"><a href="<?php echo esc_url( $archive ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'View the directory', 'favr-directory' ); ?> ↗</a></p>
									<?php endif; ?>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="favr-cat-slug"><?php esc_html_e( 'Category URL', 'favr-directory' ); ?></label></th>
								<td><code>/<?php echo esc_html( Registrar::directorySlug() ); ?>/</code><input type="text" id="favr-cat-slug" class="regular-text code favr-slug-input" name="<?php echo esc_attr( $option ); ?>[category_slug]" value="<?php echo esc_attr( (string) $s['category_slug'] ); ?>"><code>/restaurants/</code></td>
							</tr>
							<tr>
								<th scope="row"><label for="favr-per-page"><?php esc_html_e( 'Businesses per page', 'favr-directory' ); ?></label></th>
								<td><input type="number" id="favr-per-page" min="1" max="100" class="small-text" name="<?php echo esc_attr( $option ); ?>[per_page]" value="<?php echo esc_attr( (string) $s['per_page'] ); ?>"></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Default layout', 'favr-directory' ); ?></th>
								<td>
									<fieldset class="favr-radios">
										<label><input type="radio" name="<?php echo esc_attr( $option ); ?>[layout]" value="grid" <?php checked( $s['layout'], 'grid' ); ?>> <?php esc_html_e( 'Grid of cards', 'favr-directory' ); ?></label>
										<label><input type="radio" name="<?php echo esc_attr( $option ); ?>[layout]" value="list" <?php checked( $s['layout'], 'list' ); ?>> <?php esc_html_e( 'Compact list', 'favr-directory' ); ?></label>
									</fieldset>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Browsing', 'favr-directory' ); ?></th>
								<td>
									<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[show_letters]" value="1" <?php checked( $s['show_letters'], '1' ); ?>> <?php esc_html_e( 'Show the A–Z letter filter', 'favr-directory' ); ?></label><br>
									<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[show_open_now]" value="1" <?php checked( $s['show_open_now'], '1' ); ?>> <?php esc_html_e( 'Show “Open now” badges', 'favr-directory' ); ?></label>
								</td>
							</tr>
						</table>
					</div>

					<div class="favr-card">
						<h2><?php esc_html_e( 'Business pages', 'favr-directory' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Show these sections', 'favr-directory' ); ?></th>
								<td>
									<fieldset class="favr-columns">
										<?php foreach ( Settings::sectionChoices() as $key => $label ) : ?>
											<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[sections][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( Settings::sectionEnabled( $key ) ); ?>> <?php echo esc_html( $label ); ?></label>
										<?php endforeach; ?>
									</fieldset>
									<p class="description"><?php esc_html_e( 'Sections only appear when a business has filled them in.', 'favr-directory' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="favr-map"><?php esc_html_e( 'Maps', 'favr-directory' ); ?></label></th>
								<td>
									<select id="favr-map" name="<?php echo esc_attr( $option ); ?>[map_provider]">
										<option value="google" <?php selected( $s['map_provider'], 'google' ); ?>><?php esc_html_e( 'Google Maps (no API key needed)', 'favr-directory' ); ?></option>
										<option value="none" <?php selected( $s['map_provider'], 'none' ); ?>><?php esc_html_e( 'No maps', 'favr-directory' ); ?></option>
									</select>
									<p class="description"><?php esc_html_e( 'Maps load only when a visitor scrolls to them.', 'favr-directory' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="favr-accent"><?php esc_html_e( 'Accent color', 'favr-directory' ); ?></label></th>
								<td>
									<input type="text" id="favr-accent" class="favr-color" name="<?php echo esc_attr( $option ); ?>[accent_color]" value="<?php echo esc_attr( (string) $s['accent_color'] ); ?>">
									<p class="description"><?php esc_html_e( 'Leave blank to use your theme’s link color.', 'favr-directory' ); ?></p>
								</td>
							</tr>
						</table>
					</div>

					<div class="favr-card">
						<h2><?php esc_html_e( 'Search engines', 'favr-directory' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><label for="favr-org"><?php esc_html_e( 'Your organization', 'favr-directory' ); ?></label></th>
								<td>
									<input type="text" id="favr-org" class="regular-text" name="<?php echo esc_attr( $option ); ?>[organization_name]" value="<?php echo esc_attr( (string) $s['organization_name'] ); ?>" placeholder="<?php echo esc_attr( wp_strip_all_tags( (string) get_bloginfo( 'name' ) ) ); ?>">
									<p class="description"><?php esc_html_e( 'Every listing tells search engines it is a member of this organization (e.g. “Springfield Area Chamber of Commerce”). Leave blank to use the site title.', 'favr-directory' ); ?></p>
								</td>
							</tr>
						</table>
						<p class="description"><?php esc_html_e( 'Business pages include LocalBusiness structured data, directory pages an ItemList, and all pages breadcrumbs. With Yoast SEO or Rank Math active, this data is merged into their output instead of being printed separately.', 'favr-directory' ); ?></p>
					</div>

					<div class="favr-card">
						<h2><?php esc_html_e( 'Data', 'favr-directory' ); ?></h2>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'On uninstall', 'favr-directory' ); ?></th>
								<td><label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[delete_data]" value="1" <?php checked( $s['delete_data'], '1' ); ?>> <?php esc_html_e( 'Permanently delete all businesses, categories and settings when the plugin is deleted', 'favr-directory' ); ?></label></td>
							</tr>
						</table>
					</div>

					<?php submit_button(); ?>
				</form>

				<aside class="favr-settings__aside">
					<div class="favr-card">
						<h2><?php esc_html_e( 'Show the directory anywhere', 'favr-directory' ); ?></h2>
						<p><?php esc_html_e( 'The directory lives at its own URL automatically. You can also place it on any page:', 'favr-directory' ); ?></p>
						<ul class="favr-help">
							<li><strong><?php esc_html_e( 'Block editor:', 'favr-directory' ); ?></strong> <?php esc_html_e( 'add the “Business Directory” block.', 'favr-directory' ); ?></li>
							<li><strong><?php esc_html_e( 'Shortcode:', 'favr-directory' ); ?></strong> <code>[favr_directory]</code></li>
							<li><?php esc_html_e( 'Featured only:', 'favr-directory' ); ?> <code>[favr_directory featured="1" per_page="6" search="0"]</code></li>
							<li><?php esc_html_e( 'One category:', 'favr-directory' ); ?> <code>[favr_directory category="restaurants"]</code></li>
							<li><?php esc_html_e( 'A single profile:', 'favr-directory' ); ?> <code>[favr_business id="123"]</code></li>
							<li><?php esc_html_e( 'One field (page builders):', 'favr-directory' ); ?> <code>[favr_business_field field="phone"]</code></li>
						</ul>
					</div>
				</aside>
			</div>
		</div>
		<?php
	}
}
