<?php
/**
 * The listing edit screen: staff wording, with the public wording left alone.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Admin\EditScreen;
use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Schema\Identifiers as ID;

final class EditScreenWordsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'esc_html__' => static fn( $text ) => $text,
				'esc_url'    => static fn( $url ) => $url,
			)
		);
		FieldRegistry::reset();
	}

	protected function tearDown(): void {
		FieldRegistry::reset();
		parent::tearDown();
	}

	public function test_save_messages_say_listing(): void {
		Functions\when( 'get_post' )->justReturn( null );
		$messages = ( new EditScreen() )->messages( array( 'post' => array( 1 => 'Post updated.' ) ) )[ ID::POST_TYPE ];

		$this->assertSame( 'Listing updated.', $messages[1] );
		$this->assertSame( 'Listing updated.', $messages[4] );
		$this->assertSame( 'Listing published.', $messages[6] );
		$this->assertSame( 'Listing saved.', $messages[7] );
		$this->assertSame( 'Listing submitted.', $messages[8] );
		$this->assertSame( 'Listing scheduled.', $messages[9] );
		$this->assertSame( 'Listing draft updated.', $messages[10] );
	}

	public function test_save_messages_link_to_the_listing(): void {
		Functions\when( 'get_post' )->justReturn( (object) array( 'ID' => 20 ) );
		Functions\when( 'get_permalink' )->justReturn( 'https://example.org/directory/elena-marquez/' );
		$messages = ( new EditScreen() )->messages( array() )[ ID::POST_TYPE ];

		$this->assertSame( 'Listing published. <a href="https://example.org/directory/elena-marquez/">View listing</a>', $messages[6] );
	}

	/**
	 * WordPress keeps the `post_name` field inside its Slug box, and "Edit" on the permalink under
	 * the title saves through that field: without the box an Administrator's new address was
	 * dropped on Update. (Favr Sites takes the box away for Editors and prints the field itself.)
	 */
	public function test_the_slug_box_stays_so_the_permalink_under_the_title_saves(): void {
		$this->assertNotContains( 'slugdiv', EditScreen::REMOVED_BOXES );
		$this->assertSame( array( 'postexcerpt', 'postcustom', 'authordiv' ), EditScreen::REMOVED_BOXES );
	}

	public function test_the_title_field_asks_for_the_listing_name(): void {
		$screen = new EditScreen();
		$this->assertSame( 'Listing name', $screen->missingLabels()['title'] );
	}

	public function test_staff_never_read_business_on_a_field_or_tab(): void {
		foreach ( FieldRegistry::all() as $id => $field ) {
			$staff = FieldRegistry::forStaff( $field );
			$this->assertDoesNotMatchRegularExpression( '/business/i', (string) $staff['label'], "$id label" );
			$this->assertDoesNotMatchRegularExpression( '/business/i', (string) $staff['description'], "$id help" );
			$this->assertDoesNotMatchRegularExpression( '/business/i', (string) $staff['placeholder'], "$id placeholder" );
		}
		foreach ( FieldRegistry::tabs() as $id => $tab ) {
			$label = (string) FieldRegistry::forStaff( $tab )['label'];
			$this->assertDoesNotMatchRegularExpression( '/business/i', $label, "$id tab" );
			$this->assertDoesNotMatchRegularExpression( '/\s\p{Lu}\p{Ll}/u', $label, "$id tab is not sentence case" );
		}
	}

	public function test_staff_wording_replaces_only_what_it_names(): void {
		$this->assertSame( 'Online-only listing (no public address)', FieldRegistry::forStaff( FieldRegistry::get( 'online_only' ) )['label'] );
		$this->assertSame( 'Hides the address and map on the listing.', FieldRegistry::forStaff( FieldRegistry::get( 'online_only' ) )['description'] );
		$this->assertSame( 'Listing highlights', FieldRegistry::forStaff( FieldRegistry::get( 'highlights' ) )['label'] );
		$this->assertSame( 'Phone', FieldRegistry::forStaff( FieldRegistry::get( 'phone' ) )['label'] );
		$this->assertSame( 'Photos & video', FieldRegistry::forStaff( FieldRegistry::tabs()['media'] )['label'] );
		$this->assertSame( 'Deals & extras', FieldRegistry::forStaff( FieldRegistry::tabs()['extras'] )['label'] );
	}

	public function test_the_public_wording_does_not_change(): void {
		// The same definitions label the front-end "My Listing" form, REST and the page builders.
		$this->assertSame( 'Online-only business (no public address)', FieldRegistry::get( 'online_only' )['label'] );
		$this->assertSame( 'Business highlights', FieldRegistry::get( 'highlights' )['label'] );
		$this->assertSame( 'Optional. Leave blank for local businesses.', FieldRegistry::get( 'country' )['description'] );
		$this->assertSame( 'Photos & Video', FieldRegistry::tabs()['media']['label'] );
		$this->assertSame( 'Deals & Extras', FieldRegistry::tabs()['extras']['label'] );
	}
}
