<?php
declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use FavrDirectory\Editing\Claims;

/**
 * The claim form's hidden robot trap. Named "favr_website_check", password managers could fill
 * it, and the person was told their claim was sent while it was dropped.
 */
final class ClaimTrapTest extends TestCase {

	public function test_the_hidden_field_has_a_name_no_autofill_or_password_manager_recognises(): void {
		foreach ( array( 'web', 'site', 'url', 'link', 'home', 'mail', 'name', 'nick', 'phone', 'tel', 'org', 'company', 'business', 'address', 'user', 'login', 'pass', 'title' ) as $word ) {
			$this->assertStringNotContainsString( $word, substr( Claims::TRAP, strlen( 'favr_' ) ) );
		}
	}

	public function test_the_claim_form_tells_password_managers_to_skip_the_hidden_field(): void {
		\Brain\Monkey\Functions\stubs( array( 'esc_html__' => static fn( $v ) => $v, 'esc_attr' => static fn( $v ) => $v ) );
		$field = Claims::trapField();
		$this->assertStringContainsString( 'name="' . Claims::TRAP . '"', $field );
		foreach ( array( 'autocomplete="off"', 'tabindex="-1"', 'data-1p-ignore', 'data-lpignore="true"', 'data-bwignore', 'data-protonpass-ignore', 'data-form-type="other"', 'aria-hidden="true"' ) as $attribute ) {
			$this->assertStringContainsString( $attribute, $field );
		}
	}

	public function test_only_the_hidden_field_marks_a_robot(): void {
		$this->assertTrue( Claims::trapped( array( Claims::TRAP => 'x' ) ) );
		$this->assertFalse( Claims::trapped( array( Claims::TRAP => '' ) ) );
		$this->assertFalse( Claims::trapped( array( Claims::TRAP => ' ' ) ) );
		$this->assertFalse( Claims::trapped( array( 'favr_website_check' => 'https://26am.com' ) ), 'the old name is an ordinary field now' );
	}
}
