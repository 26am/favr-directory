<?php
/**
 * Declarative schema for every business field.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Fields;

/**
 * The single definition of the business profile. The admin UI, the sanitizer, the REST
 * registration, CSV import/export and the front end all read from here, so adding a field is
 * a one-place change. Third parties extend it with the `favr_directory_fields` and
 * `favr_directory_tabs` filters.
 *
 * Field keys:
 *  - id          string  Unique id; stored as meta key `favr_{id}`.
 *  - label       string
 *  - type        string  text|textarea|email|url|tel|number|select|toggle|date|image|gallery|hours|repeater|checkboxes
 *  - tab         string  Tab id.
 *  - width       string  full|half|third (admin layout only).
 *  - description string  Help text under the input.
 *  - placeholder string
 *  - options     array   value => label (select, checkboxes).
 *  - default     mixed
 *  - maxlength   int
 *  - min/max     int     (number)
 *  - sub_fields  array   (repeater) list of simple fields.
 *  - conditions  array   { field, value } show only when another field matches.
 *  - private     bool    Never exposed publicly (REST, front end, public export).
 *  - weight      int     Contribution to the profile-completeness score (0 = ignored).
 */
final class FieldRegistry {

	/** @var array<string, array<string, mixed>>|null */
	private static ?array $fields = null;

	/** @var array<string, array<string, string>>|null */
	private static ?array $tabs = null;

	/**
	 * Admin tabs in display order.
	 *
	 * @return array<string, array{label: string, icon: string}>
	 */
	public static function tabs(): array {
		if ( null === self::$tabs ) {
			$tabs = array(
				'overview'   => array(
					'label' => __( 'Overview', 'favr-directory' ),
					'icon'  => 'dashicons-store',
				),
				'contact'    => array(
					'label' => __( 'Contact', 'favr-directory' ),
					'icon'  => 'dashicons-phone',
				),
				'location'   => array(
					'label' => __( 'Location', 'favr-directory' ),
					'icon'  => 'dashicons-location',
				),
				'hours'      => array(
					'label' => __( 'Hours', 'favr-directory' ),
					'icon'  => 'dashicons-clock',
				),
				'social'     => array(
					'label' => __( 'Social', 'favr-directory' ),
					'icon'  => 'dashicons-share',
				),
				'media'      => array(
					'label' => __( 'Photos & Video', 'favr-directory' ),
					'icon'  => 'dashicons-format-gallery',
				),
				'membership' => array(
					'label' => __( 'Membership', 'favr-directory' ),
					'icon'  => 'dashicons-id-alt',
				),
				'extras'     => array(
					'label' => __( 'Deals & Extras', 'favr-directory' ),
					'icon'  => 'dashicons-tag',
				),
			);

			/**
			 * Filter the admin tabs for the business editor.
			 *
			 * @param array $tabs Tab id => { label, icon }.
			 */
			self::$tabs = (array) apply_filters( 'favr_directory_tabs', $tabs );
		}
		return self::$tabs;
	}

	/**
	 * All fields keyed by id, in display order.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		if ( null === self::$fields ) {
			$fields = array();
			foreach ( self::definitions() as $field ) {
				$fields[ $field['id'] ] = self::normalize( $field );
			}

			/**
			 * Filter the business field definitions.
			 *
			 * @param array $fields Field id => definition. See FieldRegistry docblock.
			 */
			$fields = (array) apply_filters( 'favr_directory_fields', $fields );

			foreach ( $fields as $id => $field ) {
				$field['id']   = (string) ( $field['id'] ?? $id );
				$fields[ $id ] = self::normalize( $field );
			}
			self::$fields = $fields;
		}
		return self::$fields;
	}

	/**
	 * One field definition, or null.
	 *
	 * @param string $id Field id.
	 * @return array<string, mixed>|null
	 */
	public static function get( string $id ): ?array {
		return self::all()[ $id ] ?? null;
	}

	/**
	 * Fields in a tab.
	 *
	 * @param string $tab Tab id.
	 * @return array<string, array<string, mixed>>
	 */
	public static function forTab( string $tab ): array {
		return array_filter( self::all(), static fn( array $f ): bool => $f['tab'] === $tab );
	}

	/** Reset caches (tests, or after late filter registration). */
	public static function reset(): void {
		self::$fields = null;
		self::$tabs   = null;
	}

	/**
	 * Days of the week in display order.
	 *
	 * @return array<string, string>
	 */
	public static function days(): array {
		return array(
			'mon' => __( 'Monday', 'favr-directory' ),
			'tue' => __( 'Tuesday', 'favr-directory' ),
			'wed' => __( 'Wednesday', 'favr-directory' ),
			'thu' => __( 'Thursday', 'favr-directory' ),
			'fri' => __( 'Friday', 'favr-directory' ),
			'sat' => __( 'Saturday', 'favr-directory' ),
			'sun' => __( 'Sunday', 'favr-directory' ),
		);
	}

	/**
	 * Social networks: field id => label.
	 *
	 * @return array<string, string>
	 */
	public static function socialNetworks(): array {
		return array(
			'facebook'  => 'Facebook',
			'instagram' => 'Instagram',
			'linkedin'  => 'LinkedIn',
			'x'         => 'X (Twitter)',
			'youtube'   => 'YouTube',
			'tiktok'    => 'TikTok',
			'pinterest' => 'Pinterest',
			'yelp'      => 'Yelp',
		);
	}

	/**
	 * Fill defaults so consumers never need isset checks.
	 *
	 * @param array<string, mixed> $field Raw definition.
	 * @return array<string, mixed>
	 */
	private static function normalize( array $field ): array {
		return array_merge(
			array(
				'id'          => '',
				'label'       => '',
				'type'        => 'text',
				'tab'         => 'overview',
				'width'       => 'full',
				'description' => '',
				'placeholder' => '',
				'options'     => array(),
				'default'     => '',
				'maxlength'   => 0,
				'min'         => null,
				'max'         => null,
				'sub_fields'  => array(),
				'conditions'  => array(),
				'private'     => false,
				'weight'      => 0,
			),
			$field
		);
	}

	/**
	 * Built-in fields.
	 *
	 * @return list<array<string, mixed>>
	 */
	private static function definitions(): array {
		$fields = array(
			// Overview.
			array(
				'id'          => 'logo',
				'label'       => __( 'Logo', 'favr-directory' ),
				'type'        => 'image',
				'tab'         => 'overview',
				'width'       => 'third',
				'description' => __( 'Square images look best. The featured image is used as the cover photo.', 'favr-directory' ),
				'weight'      => 10,
			),
			array(
				'id'          => 'tagline',
				'label'       => __( 'Tagline', 'favr-directory' ),
				'type'        => 'text',
				'tab'         => 'overview',
				'width'       => 'full',
				'placeholder' => __( 'e.g. Family-owned bakery serving Main Street since 1982', 'favr-directory' ),
				'maxlength'   => 120,
				'weight'      => 5,
			),
			array(
				'id'          => 'summary',
				'label'       => __( 'Short description', 'favr-directory' ),
				'type'        => 'textarea',
				'tab'         => 'overview',
				'description' => __( 'Shown on directory cards and in search results. Use the main editor above for the full story.', 'favr-directory' ),
				'maxlength'   => 300,
				'weight'      => 10,
			),
			array(
				'id'     => 'year_established',
				'label'  => __( 'Year established', 'favr-directory' ),
				'type'   => 'number',
				'tab'    => 'overview',
				'width'  => 'half',
				'min'    => 1600,
				'max'    => 2100,
				'weight' => 2,
			),
			array(
				'id'      => 'employees',
				'label'   => __( 'Number of employees', 'favr-directory' ),
				'type'    => 'select',
				'tab'     => 'overview',
				'width'   => 'half',
				'options' => array(
					''        => __( '— Select —', 'favr-directory' ),
					'1-10'    => '1–10',
					'11-50'   => '11–50',
					'51-200'  => '51–200',
					'201-500' => '201–500',
					'500+'    => '500+',
				),
			),

			// Contact.
			array(
				'id'     => 'contact_name',
				'label'  => __( 'Contact person', 'favr-directory' ),
				'type'   => 'text',
				'tab'    => 'contact',
				'width'  => 'half',
				'weight' => 3,
			),
			array(
				'id'          => 'contact_title',
				'label'       => __( 'Contact title', 'favr-directory' ),
				'type'        => 'text',
				'tab'         => 'contact',
				'width'       => 'half',
				'placeholder' => __( 'e.g. Owner', 'favr-directory' ),
			),
			array(
				'id'          => 'phone',
				'label'       => __( 'Phone', 'favr-directory' ),
				'type'        => 'tel',
				'tab'         => 'contact',
				'width'       => 'half',
				'placeholder' => '(555) 555-0100',
				'weight'      => 10,
			),
			array(
				'id'    => 'phone_alt',
				'label' => __( 'Alternate phone', 'favr-directory' ),
				'type'  => 'tel',
				'tab'   => 'contact',
				'width' => 'half',
			),
			array(
				'id'          => 'email',
				'label'       => __( 'Email', 'favr-directory' ),
				'type'        => 'email',
				'tab'         => 'contact',
				'width'       => 'half',
				'placeholder' => 'hello@example.com',
				'weight'      => 8,
			),
			array(
				'id'          => 'website',
				'label'       => __( 'Website', 'favr-directory' ),
				'type'        => 'url',
				'tab'         => 'contact',
				'width'       => 'half',
				'placeholder' => 'https://',
				'weight'      => 10,
			),
			array(
				'id'          => 'show_email',
				'label'       => __( 'Show email address publicly', 'favr-directory' ),
				'type'        => 'toggle',
				'tab'         => 'contact',
				'width'       => 'half',
				'default'     => '1',
				'description' => __( 'Turn off to keep the email on file for staff only.', 'favr-directory' ),
			),
			array(
				'id'          => 'booking_url',
				'label'       => __( 'Booking / ordering link', 'favr-directory' ),
				'type'        => 'url',
				'tab'         => 'contact',
				'width'       => 'half',
				'placeholder' => 'https://',
				'description' => __( 'Adds a “Book now” button to the listing.', 'favr-directory' ),
			),

			// Location.
			array(
				'id'          => 'online_only',
				'label'       => __( 'Online-only business (no public address)', 'favr-directory' ),
				'type'        => 'toggle',
				'tab'         => 'location',
				'description' => __( 'Hides the address and map on the listing.', 'favr-directory' ),
			),
			array(
				'id'         => 'address_1',
				'label'      => __( 'Street address', 'favr-directory' ),
				'type'       => 'text',
				'tab'        => 'location',
				'width'      => 'half',
				'conditions' => array(
					'field' => 'online_only',
					'value' => '',
				),
				'weight'     => 10,
			),
			array(
				'id'          => 'address_2',
				'label'       => __( 'Suite / unit', 'favr-directory' ),
				'type'        => 'text',
				'tab'         => 'location',
				'width'       => 'half',
				'placeholder' => __( 'Suite 200', 'favr-directory' ),
				'conditions'  => array(
					'field' => 'online_only',
					'value' => '',
				),
			),
			array(
				'id'         => 'city',
				'label'      => __( 'City', 'favr-directory' ),
				'type'       => 'text',
				'tab'        => 'location',
				'width'      => 'third',
				'conditions' => array(
					'field' => 'online_only',
					'value' => '',
				),
				'weight'     => 5,
			),
			array(
				'id'         => 'state',
				'label'      => __( 'State / province', 'favr-directory' ),
				'type'       => 'text',
				'tab'        => 'location',
				'width'      => 'third',
				'conditions' => array(
					'field' => 'online_only',
					'value' => '',
				),
			),
			array(
				'id'         => 'postal_code',
				'label'      => __( 'ZIP / postal code', 'favr-directory' ),
				'type'       => 'text',
				'tab'        => 'location',
				'width'      => 'third',
				'conditions' => array(
					'field' => 'online_only',
					'value' => '',
				),
			),
			array(
				'id'          => 'country',
				'label'       => __( 'Country', 'favr-directory' ),
				'type'        => 'text',
				'tab'         => 'location',
				'width'       => 'half',
				'description' => __( 'Optional. Leave blank for local businesses.', 'favr-directory' ),
				'conditions'  => array(
					'field' => 'online_only',
					'value' => '',
				),
			),
			array(
				'id'          => 'service_area',
				'label'       => __( 'Service area', 'favr-directory' ),
				'type'        => 'text',
				'tab'         => 'location',
				'width'       => 'half',
				'placeholder' => __( 'e.g. Greater Springfield & surrounding counties', 'favr-directory' ),
			),
			array(
				'id'         => 'show_map',
				'label'      => __( 'Show a map on the listing', 'favr-directory' ),
				'type'       => 'toggle',
				'tab'        => 'location',
				'width'      => 'full',
				'default'    => '1',
				'conditions' => array(
					'field' => 'online_only',
					'value' => '',
				),
			),
			array(
				'id'          => 'latitude',
				'label'       => __( 'Latitude', 'favr-directory' ),
				'type'        => 'text',
				'tab'         => 'location',
				'width'       => 'half',
				'description' => __( 'Optional. Pin the map precisely; otherwise the address is used.', 'favr-directory' ),
				'conditions'  => array(
					'field' => 'online_only',
					'value' => '',
				),
			),
			array(
				'id'         => 'longitude',
				'label'      => __( 'Longitude', 'favr-directory' ),
				'type'       => 'text',
				'tab'        => 'location',
				'width'      => 'half',
				'conditions' => array(
					'field' => 'online_only',
					'value' => '',
				),
			),

			// Hours.
			array(
				'id'     => 'hours',
				'label'  => __( 'Opening hours', 'favr-directory' ),
				'type'   => 'hours',
				'tab'    => 'hours',
				'weight' => 8,
			),
			array(
				'id'    => 'by_appointment',
				'label' => __( 'Also available by appointment', 'favr-directory' ),
				'type'  => 'toggle',
				'tab'   => 'hours',
				'width' => 'half',
			),
			array(
				'id'          => 'hours_note',
				'label'       => __( 'Hours note', 'favr-directory' ),
				'type'        => 'text',
				'tab'         => 'hours',
				'width'       => 'half',
				'placeholder' => __( 'e.g. Closed on public holidays', 'favr-directory' ),
			),

			// Media.
			array(
				'id'          => 'gallery',
				'label'       => __( 'Photo gallery', 'favr-directory' ),
				'type'        => 'gallery',
				'tab'         => 'media',
				'description' => __( 'Drag to reorder.', 'favr-directory' ),
				'weight'      => 6,
			),
			array(
				'id'          => 'video_url',
				'label'       => __( 'Video', 'favr-directory' ),
				'type'        => 'url',
				'tab'         => 'media',
				'placeholder' => 'https://www.youtube.com/watch?v=…',
				'description' => __( 'Paste a YouTube or Vimeo link.', 'favr-directory' ),
			),

			// Membership.
			array(
				'id'          => 'featured',
				'label'       => __( 'Featured listing', 'favr-directory' ),
				'type'        => 'toggle',
				'tab'         => 'membership',
				'description' => __( 'Featured businesses are highlighted and shown first in the directory.', 'favr-directory' ),
			),
			array(
				'id'    => 'member_since',
				'label' => __( 'Member since', 'favr-directory' ),
				'type'  => 'date',
				'tab'   => 'membership',
				'width' => 'half',
			),
			array(
				'id'      => 'renewal_date',
				'label'   => __( 'Renewal date', 'favr-directory' ),
				'type'    => 'date',
				'tab'     => 'membership',
				'width'   => 'half',
				'private' => true,
			),
			array(
				'id'      => 'member_id',
				'label'   => __( 'Member ID', 'favr-directory' ),
				'type'    => 'text',
				'tab'     => 'membership',
				'width'   => 'half',
				'private' => true,
			),
			array(
				'id'          => 'admin_notes',
				'label'       => __( 'Staff notes', 'favr-directory' ),
				'type'        => 'textarea',
				'tab'         => 'membership',
				'description' => __( 'Private. Only visible to directory staff.', 'favr-directory' ),
				'private'     => true,
			),

			// Deals & extras.
			array(
				'id'          => 'highlights',
				'label'       => __( 'Business highlights', 'favr-directory' ),
				'type'        => 'checkboxes',
				'tab'         => 'extras',
				'options'     => self::highlightOptions(),
				'description' => __( 'Shown as badges on the listing.', 'favr-directory' ),
				'weight'      => 3,
			),
			array(
				'id'    => 'has_deal',
				'label' => __( 'Offer a member deal', 'favr-directory' ),
				'type'  => 'toggle',
				'tab'   => 'extras',
			),
			array(
				'id'          => 'deal_title',
				'label'       => __( 'Deal headline', 'favr-directory' ),
				'type'        => 'text',
				'tab'         => 'extras',
				'width'       => 'half',
				'placeholder' => __( 'e.g. 10% off for chamber members', 'favr-directory' ),
				'conditions'  => array(
					'field' => 'has_deal',
					'value' => '1',
				),
			),
			array(
				'id'         => 'deal_code',
				'label'      => __( 'Promo code', 'favr-directory' ),
				'type'       => 'text',
				'tab'        => 'extras',
				'width'      => 'half',
				'conditions' => array(
					'field' => 'has_deal',
					'value' => '1',
				),
			),
			array(
				'id'         => 'deal_description',
				'label'      => __( 'Deal details', 'favr-directory' ),
				'type'       => 'textarea',
				'tab'        => 'extras',
				'conditions' => array(
					'field' => 'has_deal',
					'value' => '1',
				),
			),
			array(
				'id'          => 'deal_expires',
				'label'       => __( 'Deal expires', 'favr-directory' ),
				'type'        => 'date',
				'tab'         => 'extras',
				'width'       => 'half',
				'description' => __( 'The deal hides itself automatically after this date.', 'favr-directory' ),
				'conditions'  => array(
					'field' => 'has_deal',
					'value' => '1',
				),
			),
			array(
				'id'          => 'links',
				'label'       => __( 'Additional links', 'favr-directory' ),
				'type'        => 'repeater',
				'tab'         => 'extras',
				'description' => __( 'Menus, brochures, donation pages — anything else worth linking.', 'favr-directory' ),
				'sub_fields'  => array(
					array(
						'id'          => 'label',
						'label'       => __( 'Label', 'favr-directory' ),
						'type'        => 'text',
						'placeholder' => __( 'e.g. View our menu', 'favr-directory' ),
					),
					array(
						'id'          => 'url',
						'label'       => __( 'URL', 'favr-directory' ),
						'type'        => 'url',
						'placeholder' => 'https://',
					),
				),
			),
		);

		// Social profiles, one URL field each.
		foreach ( self::socialNetworks() as $network => $label ) {
			$fields[] = array(
				'id'          => $network,
				'label'       => $label,
				'type'        => 'url',
				'tab'         => 'social',
				'width'       => 'half',
				'placeholder' => 'https://',
				'weight'      => 'facebook' === $network || 'instagram' === $network || 'linkedin' === $network ? 2 : 0,
			);
		}

		return $fields;
	}

	/**
	 * Highlight badges.
	 *
	 * @return array<string, string>
	 */
	public static function highlightOptions(): array {
		/**
		 * Filter the "Business highlights" badge options.
		 *
		 * @param array $options value => label.
		 */
		return (array) apply_filters(
			'favr_directory_highlight_options',
			array(
				'locally_owned'   => __( 'Locally owned', 'favr-directory' ),
				'family_owned'    => __( 'Family owned', 'favr-directory' ),
				'woman_owned'     => __( 'Woman owned', 'favr-directory' ),
				'veteran_owned'   => __( 'Veteran owned', 'favr-directory' ),
				'minority_owned'  => __( 'Minority owned', 'favr-directory' ),
				'nonprofit'       => __( 'Nonprofit', 'favr-directory' ),
				'accessible'      => __( 'Wheelchair accessible', 'favr-directory' ),
				'free_parking'    => __( 'Free parking', 'favr-directory' ),
				'free_wifi'       => __( 'Free Wi-Fi', 'favr-directory' ),
				'pet_friendly'    => __( 'Pet friendly', 'favr-directory' ),
				'online_ordering' => __( 'Online ordering', 'favr-directory' ),
				'delivery'        => __( 'Delivery', 'favr-directory' ),
				'bilingual'       => __( 'Bilingual staff', 'favr-directory' ),
			)
		);
	}
}
