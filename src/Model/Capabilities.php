<?php
/**
 * Roles and capabilities.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Model;

use FavrDirectory\Schema\Identifiers as ID;

/**
 * Businesses use their own capability type so a chamber can give staff a
 * "Directory Manager" role that manages listings without touching the rest of the site.
 */
final class Capabilities {

	/**
	 * Every primitive capability for the business post type plus plugin caps.
	 *
	 * @return list<string>
	 */
	public static function all(): array {
		$plural = ID::CAP_TYPE_PLURAL;
		return array(
			'edit_' . $plural,
			'edit_others_' . $plural,
			'edit_private_' . $plural,
			'edit_published_' . $plural,
			'publish_' . $plural,
			'read_private_' . $plural,
			'delete_' . $plural,
			'delete_others_' . $plural,
			'delete_private_' . $plural,
			'delete_published_' . $plural,
			ID::CAP_MANAGE_TERMS,
			ID::CAP_SETTINGS,
		);
	}

	/**
	 * Grant caps to administrators/editors and make sure the Directory Manager role has its own.
	 * Idempotent; runs on activation and on every version upgrade.
	 *
	 * The role is never removed and re-created: other plugins add their capabilities to it (Favr
	 * Members lets Directory Managers manage members), and a Directory update must not take
	 * those away.
	 */
	public static function install(): void {
		foreach ( array( 'administrator', 'editor' ) as $role_name ) {
			$role = get_role( $role_name );
			if ( ! $role ) {
				continue;
			}
			foreach ( self::all() as $cap ) {
				if ( ID::CAP_SETTINGS === $cap && 'administrator' !== $role_name ) {
					continue;
				}
				$role->add_cap( $cap );
			}
		}

		$caps = array_fill_keys( self::all(), true );
		unset( $caps[ ID::CAP_SETTINGS ] );
		$caps['read']         = true;
		$caps['upload_files'] = true;

		$manager = get_role( ID::ROLE_MANAGER );
		if ( ! $manager ) {
			add_role( ID::ROLE_MANAGER, __( 'Directory Manager', 'favr-directory' ), $caps );
			return;
		}
		foreach ( array_keys( $caps ) as $cap ) {
			if ( ! $manager->has_cap( $cap ) ) {
				$manager->add_cap( $cap );
			}
		}
	}

	/** Remove every trace of our caps and role (uninstall only). */
	public static function uninstall(): void {
		foreach ( wp_roles()->role_objects as $role ) {
			foreach ( self::all() as $cap ) {
				$role->remove_cap( $cap );
			}
		}
		remove_role( ID::ROLE_MANAGER );
	}
}
