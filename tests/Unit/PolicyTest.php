<?php
/**
 * Member access policy tests.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Editing\Policy;
use FavrDirectory\Editing\Values;
use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Support\Settings;

final class PolicyTest extends TestCase {

	/** @var array<string, mixed> */
	private array $option = array();

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'_x'           => static fn( $v ) => $v,
				'esc_html__'   => static fn( $v ) => $v,
				'get_option'   => fn( $key, $default = false ) => 'favr_directory_settings' === $key ? $this->option : $default,
			)
		);
		FieldRegistry::reset();
		Settings::flush();
	}

	public function test_defaults_follow_the_spec(): void {
		$this->assertSame( Policy::EDIT, Policy::access( 'phone' ) );
		$this->assertSame( Policy::EDIT, Policy::access( 'hours' ) );
		$this->assertSame( Policy::REVIEW, Policy::access( 'business_name' ) );
		$this->assertSame( Policy::REVIEW, Policy::access( 'address_1' ) );
		$this->assertSame( Policy::NONE, Policy::access( 'featured' ) );
	}

	public function test_private_fields_are_never_exposed_even_if_overridden(): void {
		$this->option = array( 'member_access' => array( 'admin_notes' => 'edit' ) );
		Settings::flush();
		$this->assertSame( Policy::NONE, Policy::access( 'admin_notes' ) );
		$this->assertSame( Policy::NONE, Policy::access( 'renewal_date' ) );
		$this->assertArrayNotHasKey( 'member_id', Policy::configurable() );
	}

	public function test_settings_override_defaults(): void {
		$this->option = array(
			'member_access' => array(
				'phone'         => 'review',
				'business_name' => 'none',
			),
		);
		Settings::flush();
		$this->assertSame( Policy::REVIEW, Policy::access( 'phone' ) );
		$this->assertSame( Policy::NONE, Policy::access( 'business_name' ) );
	}

	public function test_unknown_items_are_hidden(): void {
		$this->assertSame( Policy::NONE, Policy::access( 'not_a_field' ) );
	}

	public function test_unset_toggle_means_its_default(): void {
		$this->assertSame( '1', Values::effective( 'show_map', '' ) );
		$this->assertSame( '0', Values::effective( 'show_map', '0' ) );
		$this->assertSame( '0', Values::effective( 'online_only', '' ) );
		$this->assertSame( 'x', Values::effective( 'tagline', 'x' ) );
	}
}
