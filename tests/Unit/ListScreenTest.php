<?php
/**
 * The listings list: its title, its words and its messages.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Admin\ListScreen;
use FavrDirectory\Schema\Identifiers as ID;

final class ListScreenTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'esc_html__' => static fn( $text ) => $text,
				'esc_attr__' => static fn( $text ) => $text,
				'_n'         => static fn( $single, $plural, $number ) => 1 === (int) $number ? $single : $plural,
			)
		);
	}

	/**
	 * A stand-in for the registered post type on a people directory.
	 */
	private function peopleDirectory(): object {
		return (object) array(
			'labels' => (object) array(
				'name'          => 'Members',
				'singular_name' => 'Member',
				'menu_name'     => 'Directory',
			),
		);
	}

	/**
	 * Run the title hook as if on this screen.
	 *
	 * @param object|null $screen Current screen.
	 * @param object      $type   Registered post type.
	 */
	private function titleOn( ?object $screen, object $type ): void {
		Functions\when( 'get_current_screen' )->justReturn( $screen );
		Functions\when( 'get_post_type_object' )->alias( static fn( $name ) => ID::POST_TYPE === $name ? $type : null );
		( new ListScreen() )->titleScreen();
	}

	public function test_the_list_screen_is_titled_directory(): void {
		$type = $this->peopleDirectory();
		$this->titleOn(
			(object) array(
				'base'      => 'edit',
				'post_type' => ID::POST_TYPE,
			),
			$type
		);
		$this->assertSame( 'Directory', $type->labels->name, 'WordPress prints labels->name as the list screen heading and browser title.' );
		$this->assertSame( 'Member', $type->labels->singular_name, 'Nothing else changes.' );
	}

	/**
	 * @dataProvider otherScreens
	 *
	 * @param object|null $screen Current screen.
	 */
	public function test_every_other_screen_keeps_the_public_name( ?object $screen ): void {
		$type = $this->peopleDirectory();
		$this->titleOn( $screen, $type );
		$this->assertSame( 'Members', $type->labels->name );
	}

	/** @return array<string, array{0: object|null}> */
	public static function otherScreens(): array {
		$screen = static fn( string $base, string $post_type ): object => (object) array(
			'base'      => $base,
			'post_type' => $post_type,
		);
		return array(
			'no screen yet'              => array( null ),
			'the listing edit screen'    => array( $screen( 'post', ID::POST_TYPE ) ),
			'the categories screen'      => array( $screen( 'edit-tags', ID::POST_TYPE ) ),
			'the directory settings'     => array( $screen( ID::POST_TYPE . '_page_favr-directory-settings', ID::POST_TYPE ) ),
			'another post type\'s list'  => array( $screen( 'edit', 'page' ) ),
			'the Favr Members list'      => array( $screen( 'edit', 'favr_member' ) ),
		);
	}

	public function test_the_title_changes_on_the_list_request_only_never_at_registration(): void {
		$list = new ListScreen();
		$list->hook();
		$this->assertNotFalse( has_action( 'load-edit.php', array( $list, 'titleScreen' ) ) );
		// A label filter would run on every request: the front end, REST and nav menus would lose the public noun.
		$this->assertFalse( has_filter( 'post_type_labels_' . ID::POST_TYPE ) );
	}

	public function test_the_name_column_is_headed_listing(): void {
		$columns = ( new ListScreen() )->columns(
			array(
				'cb'    => '<input type="checkbox">',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);
		$this->assertSame( 'Listing', $columns['title'] );
	}

	public function test_the_e_mail_link_carries_the_whole_address_as_its_title(): void {
		Functions\stubs(
			array(
				'esc_attr' => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
				'esc_html' => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
			)
		);
		// The column cuts a long address off with "…": hovering the link shows all of it.
		$this->assertSame(
			'<a href="mailto:elena.marquez@marquezleetaxadvisors.example" title="elena.marquez@marquezleetaxadvisors.example">elena.marquez@marquezleetaxadvisors.example</a>',
			ListScreen::emailLink( 'elena.marquez@marquezleetaxadvisors.example' )
		);
		$this->assertStringContainsString( ' title="o&#039;neal&amp;sons@example.org"', ListScreen::emailLink( "o'neal&sons@example.org" ) );
	}

	/**
	 * Favr Members' Contact column prints the same person the same way (its
	 * Admin\ListScreen::contactHtml): the e-mail first, it is the link, then the phone, each
	 * with its small icon.
	 */
	public function test_the_contact_column_puts_the_e_mail_first_then_the_phone(): void {
		Functions\stubs(
			array(
				'esc_attr' => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
				'esc_html' => static fn( $text ) => htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ),
			)
		);

		$this->assertSame(
			'<div><span class="dashicons dashicons-email" aria-hidden="true"></span> <a href="mailto:rachel@example.org" title="rachel@example.org">rachel@example.org</a></div>'
			. '<div><span class="dashicons dashicons-phone" aria-hidden="true"></span> (813) 555-0119</div>',
			ListScreen::contactHtml( 'rachel@example.org', '(813) 555-0119' )
		);
		$this->assertSame( '<div><span class="dashicons dashicons-phone" aria-hidden="true"></span> (813) 555-0119</div>', ListScreen::contactHtml( '', '(813) 555-0119' ) );
		$this->assertStringNotContainsString( 'dashicons-phone', ListScreen::contactHtml( 'rachel@example.org', '' ) );
		$this->assertSame( '<span aria-hidden="true">—</span>', ListScreen::contactHtml( '', '' ) );
	}

	public function test_bulk_messages_say_listing(): void {
		$counts   = array(
			'updated'   => 1,
			'locked'    => 2,
			'deleted'   => 1,
			'trashed'   => 3,
			'untrashed' => 1,
		);
		$messages = ( new ListScreen() )->bulkMessages( array( 'post' => array( 'trashed' => '%s posts moved to the Trash.' ) ), $counts );

		$this->assertSame( '%s posts moved to the Trash.', $messages['post']['trashed'], 'Other post types keep their messages.' );
		$this->assertSame( '%s listing updated.', $messages[ ID::POST_TYPE ]['updated'] );
		$this->assertSame( '%s listings not updated, somebody is editing them.', $messages[ ID::POST_TYPE ]['locked'] );
		$this->assertSame( '%s listing permanently deleted.', $messages[ ID::POST_TYPE ]['deleted'] );
		$this->assertSame( '%s listings moved to the Trash.', $messages[ ID::POST_TYPE ]['trashed'] );
		$this->assertSame( '%s listing restored from the Trash.', $messages[ ID::POST_TYPE ]['untrashed'] );
	}
}
