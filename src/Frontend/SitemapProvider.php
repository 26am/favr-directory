<?php
/**
 * Core sitemap provider for the directory landing page.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Frontend;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * WordPress core sitemaps list businesses and categories automatically but never post-type
 * archives, so the main /directory/ page would be missing. This provider adds it
 * (/wp-sitemap-favrdirectory-1.xml). Yoast and Rank Math list CPT archives themselves.
 */
final class SitemapProvider extends \WP_Sitemaps_Provider {

	/** Constructor. */
	public function __construct() {
		$this->name        = 'favrdirectory';
		$this->object_type = 'favrdirectory';
	}

	/**
	 * URL list.
	 *
	 * @param int    $page_num       Page.
	 * @param string $object_subtype Unused.
	 * @return list<array<string, string>>
	 */
	public function get_url_list( $page_num, $object_subtype = '' ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- core API.
		$link = get_post_type_archive_link( ID::POST_TYPE );
		if ( 1 !== (int) $page_num || ! $link ) {
			return array();
		}
		$entry  = array( 'loc' => $link );
		$latest = get_lastpostmodified( 'gmt', ID::POST_TYPE );
		if ( $latest ) {
			$entry['lastmod'] = gmdate( 'c', (int) strtotime( $latest . ' UTC' ) );
		}
		return array( $entry );
	}

	/**
	 * Pages.
	 *
	 * @param string $object_subtype Unused.
	 */
	public function get_max_num_pages( $object_subtype = '' ): int { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- core API.
		return get_post_type_archive_link( ID::POST_TYPE ) ? 1 : 0;
	}
}
