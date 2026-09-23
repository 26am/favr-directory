<?php
/**
 * Every post type, taxonomy, option, meta and capability string the plugin uses.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Schema;

/**
 * Single source of truth for identifiers. Never hardcode one of these strings elsewhere.
 */
final class Identifiers {
	public const TEXT_DOMAIN = 'favr-directory';

	public const POST_TYPE          = 'favr_business';
	public const TAX_CATEGORY       = 'favr_business_cat';
	public const TAX_LEVEL          = 'favr_member_level';
	public const META_PREFIX        = 'favr_';
	public const OPTION_SETTINGS    = 'favr_directory_settings';
	public const OPTION_VERSION     = 'favr_directory_version';
	public const OPTION_FLUSH       = 'favr_directory_flush_rewrite';
	public const TERM_META_ORDER    = 'favr_level_order';
	public const TERM_META_COLOR    = 'favr_level_color';
	public const TERM_META_ICON     = 'favr_cat_icon';
	public const ROLE_MANAGER       = 'favr_directory_manager';
	public const CAP_TYPE           = 'favr_business';
	public const CAP_TYPE_PLURAL    = 'favr_businesses';
	public const CAP_MANAGE_TERMS   = 'manage_favr_business_terms';
	public const CAP_SETTINGS       = 'manage_favr_directory';
	public const NONCE_META         = 'favr_directory_meta';
	public const NONCE_AJAX         = 'favr_directory_ajax';
	public const BLOCK_DIRECTORY    = 'favr-directory/directory';
	public const SHORTCODE_DIR      = 'favr_directory';
	public const SHORTCODE_PROFILE  = 'favr_business';
	public const SHORTCODE_FIELD    = 'favr_business_field';
	public const TEMPLATE_NAMESPACE = 'favr-directory';

	/** Query-string keys used by the front-end directory (short, stable, prefixed). */
	public const QV_SEARCH   = 'fd_q';
	public const QV_CATEGORY = 'fd_cat';
	public const QV_LEVEL    = 'fd_level';
	public const QV_LETTER   = 'fd_letter';
	public const QV_PAGE     = 'fd_page';

	/**
	 * Full meta key for a field id.
	 *
	 * @param string $field_id Field id without prefix.
	 */
	public static function meta( string $field_id ): string {
		return self::META_PREFIX . $field_id;
	}
}
