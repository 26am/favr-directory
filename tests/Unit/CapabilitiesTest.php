<?php
/**
 * Installing capabilities on activation and on every version upgrade.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Model\Capabilities;
use FavrDirectory\Schema\Identifiers as ID;

final class CapabilitiesTest extends TestCase {

	/**
	 * A stand-in for WP_Role.
	 *
	 * @param list<string> $caps Capabilities it starts with.
	 */
	private function role( array $caps = array() ): object {
		return new class( $caps ) {
			/** @var array<string, bool> */
			public array $capabilities;

			/** @param list<string> $caps Caps. */
			public function __construct( array $caps ) {
				$this->capabilities = array_fill_keys( $caps, true );
			}

			public function add_cap( string $cap ): void {
				$this->capabilities[ $cap ] = true;
			}

			public function has_cap( string $cap ): bool {
				return ! empty( $this->capabilities[ $cap ] );
			}
		};
	}

	public function test_an_upgrade_keeps_what_other_plugins_granted_the_directory_manager(): void {
		// Favr Members lets Directory Managers manage members by adding its capabilities to this role.
		$manager = $this->role( array( 'read', 'edit_favr_businesses', 'edit_favr_members' ) );
		$roles   = array(
			'administrator'  => $this->role(),
			'editor'         => $this->role(),
			ID::ROLE_MANAGER => $manager,
		);
		Functions\when( 'get_role' )->alias( static fn( $name ) => $roles[ $name ] ?? null );
		Functions\expect( 'remove_role' )->never();
		Functions\expect( 'add_role' )->never();

		Capabilities::install();

		$this->assertTrue( $manager->has_cap( 'edit_favr_members' ), 'Another plugin\'s grant survives.' );
		$this->assertTrue( $manager->has_cap( 'publish_' . ID::CAP_TYPE_PLURAL ), 'Missing Directory capabilities are added.' );
		$this->assertTrue( $manager->has_cap( ID::CAP_MANAGE_TERMS ) );
		$this->assertTrue( $manager->has_cap( 'upload_files' ) );
		$this->assertFalse( $manager->has_cap( ID::CAP_SETTINGS ), 'Settings stay with administrators.' );
		$this->assertTrue( $roles['administrator']->has_cap( ID::CAP_SETTINGS ) );
		$this->assertFalse( $roles['editor']->has_cap( ID::CAP_SETTINGS ) );
		$this->assertTrue( $roles['editor']->has_cap( 'edit_' . ID::CAP_TYPE_PLURAL ) );
	}

	public function test_a_first_install_creates_the_directory_manager(): void {
		$roles = array(
			'administrator' => $this->role(),
			'editor'        => $this->role(),
		);
		Functions\when( 'get_role' )->alias( static fn( $name ) => $roles[ $name ] ?? null );
		Functions\expect( 'remove_role' )->never();
		$created = array();
		Functions\when( 'add_role' )->alias(
			static function ( $role, $name, $caps ) use ( &$created ): void {
				$created[] = compact( 'role', 'name', 'caps' );
			}
		);

		Capabilities::install();

		$this->assertCount( 1, $created );
		$this->assertSame( ID::ROLE_MANAGER, $created[0]['role'] );
		$this->assertSame( 'Directory Manager', $created[0]['name'] );
		$this->assertTrue( $created[0]['caps']['read'] );
		$this->assertTrue( $created[0]['caps']['upload_files'] );
		$this->assertTrue( $created[0]['caps'][ 'edit_others_' . ID::CAP_TYPE_PLURAL ] );
		$this->assertArrayNotHasKey( ID::CAP_SETTINGS, $created[0]['caps'] );
	}
}
