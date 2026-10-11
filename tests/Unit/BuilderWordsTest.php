<?php
/**
 * The words staff read in the page builders: the Elementor widgets and tags, and the block
 * templates. "Listing", in sentence case, whatever the directory lists. Names that pages and
 * saved designs depend on never change.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Tests\Unit;

use Brain\Monkey\Functions;
use FavrDirectory\Fields\FieldRegistry;
use FavrDirectory\Frontend\BlockTemplates;
use FavrDirectory\Integration\Elementor\DirectoryWidget;
use FavrDirectory\Integration\Elementor\FieldWidget;
use FavrDirectory\Integration\Elementor\MyListingWidget;
use FavrDirectory\Integration\Elementor\ProfileWidget;
use FavrDirectory\Integration\Elementor\Tags\ImageTag;
use FavrDirectory\Integration\Elementor\Tags\TextTag;
use FavrDirectory\Integration\Elementor\Tags\UrlTag;
use FavrDirectory\Integration\FieldValues;

final class BuilderWordsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\stubs(
			array(
				'esc_html__' => static fn( $text ) => $text,
				'esc_html'   => static fn( $text ) => $text,
				'get_terms'  => static fn() => array(),
				'get_option' => static fn( $name, $fallback = false ) => $fallback,
			)
		);
		FieldRegistry::reset();
	}

	protected function tearDown(): void {
		FieldRegistry::reset();
		parent::tearDown();
	}

	/**
	 * A widget's controls, as it describes them.
	 *
	 * @param object $widget Widget.
	 * @return array<string, array<string, mixed>>
	 */
	private function controls( object $widget ): array {
		$settings = new \ReflectionMethod( $widget, 'settings' );
		$settings->setAccessible( true );
		return $settings->invoke( $widget );
	}

	/**
	 * Every word a widget shows in the editor panel: its title, and each control's label, help
	 * and choices.
	 *
	 * @param object $widget Widget.
	 * @return list<string>
	 */
	private function words( object $widget ): array {
		$words = array( $widget->get_title() );
		foreach ( $this->controls( $widget ) as $control ) {
			$words[] = (string) ( $control['label'] ?? '' );
			$words[] = (string) ( $control['description'] ?? '' );
			foreach ( (array) ( $control['options'] ?? array() ) as $option ) {
				$words[] = (string) $option;
			}
		}
		return $words;
	}

	public function test_the_widgets_are_named_in_listing_words(): void {
		$this->assertSame( 'Directory', ( new DirectoryWidget() )->get_title() );
		$this->assertSame( 'Listing profile', ( new ProfileWidget() )->get_title() );
		$this->assertSame( 'Listing field', ( new FieldWidget() )->get_title() );
		$this->assertSame( 'My listing (edit form)', ( new MyListingWidget() )->get_title() );
	}

	public function test_the_names_pages_were_built_with_stay(): void {
		// Elementor stores a widget in a page by this name; a renamed widget disappears from every page.
		$this->assertSame( 'favr-directory', ( new DirectoryWidget() )->get_name() );
		$this->assertSame( 'favr-business-profile', ( new ProfileWidget() )->get_name() );
		$this->assertSame( 'favr-business-field', ( new FieldWidget() )->get_name() );
		$this->assertSame( 'favr-my-listing', ( new MyListingWidget() )->get_name() );
		// And each chosen setting by its key.
		$this->assertArrayHasKey( 'business_id', $this->controls( new ProfileWidget() ) );
		$this->assertArrayHasKey( 'business_id', $this->controls( new FieldWidget() ) );
		$this->assertArrayHasKey( 'featured', $this->controls( new DirectoryWidget() ) );
		$this->assertArrayHasKey( 'per_page', $this->controls( new DirectoryWidget() ) );
	}

	public function test_the_controls_say_listing(): void {
		$this->assertSame( 'Listing ID', $this->controls( new ProfileWidget() )['business_id']['label'] );
		$this->assertSame( 'Listing ID', $this->controls( new FieldWidget() )['business_id']['label'] );
		$this->assertSame( 'Featured listings only', $this->controls( new DirectoryWidget() )['featured']['label'] );
		$this->assertSame( 'Listings per page', $this->controls( new DirectoryWidget() )['per_page']['label'] );
	}

	public function test_nobody_reads_business_in_a_widget_panel(): void {
		foreach ( array( new DirectoryWidget(), new ProfileWidget(), new FieldWidget(), new MyListingWidget() ) as $widget ) {
			foreach ( $this->words( $widget ) as $text ) {
				$this->assertDoesNotMatchRegularExpression( '/business/i', $text, get_class( $widget ) );
			}
		}
	}

	public function test_the_dynamic_tags_are_named_in_listing_words_and_keep_their_names(): void {
		$this->assertSame( 'Listing field', ( new TextTag() )->get_title() );
		$this->assertSame( 'Listing link', ( new UrlTag() )->get_title() );
		$this->assertSame( 'Listing image', ( new ImageTag() )->get_title() );
		$this->assertSame( 'favr-business-text', ( new TextTag() )->get_name() );
		$this->assertSame( 'favr-business-url', ( new UrlTag() )->get_name() );
		$this->assertSame( 'favr-business-image', ( new ImageTag() )->get_name() );
	}

	public function test_what_a_builder_can_show_is_offered_in_staff_words_under_the_same_keys(): void {
		$text = FieldValues::textOptions();
		$this->assertSame( 'Listing name', $text['name'] );
		$this->assertSame( 'Listing highlights', $text['highlights'] );
		$this->assertSame( 'Phone', $text['phone'] );
		$this->assertSame( 'Listing page', FieldValues::urlOptions()['profile'] );
		foreach ( array_merge( $text, FieldValues::urlOptions(), FieldValues::imageOptions() ) as $label ) {
			$this->assertDoesNotMatchRegularExpression( '/business/i', (string) $label );
		}
	}

	public function test_the_block_templates_are_named_in_listing_words(): void {
		$registered = array();
		Functions\when( 'register_block_template' )->alias(
			static function ( $name, $args ) use ( &$registered ) {
				$registered[ $name ] = $args;
			}
		);
		( new BlockTemplates() )->register();

		// The names a theme or the Site Editor finds them by do not change.
		$this->assertSame(
			array( 'favr-directory//single-favr_business', 'favr-directory//archive-favr_business', 'favr-directory//taxonomy-favr_business_cat' ),
			array_keys( $registered )
		);
		$this->assertSame( 'Single listing', $registered['favr-directory//single-favr_business']['title'] );
		$this->assertSame( 'Directory', $registered['favr-directory//archive-favr_business']['title'] );
		$this->assertSame( 'Directory category', $registered['favr-directory//taxonomy-favr_business_cat']['title'] );
		foreach ( $registered as $name => $template ) {
			$this->assertDoesNotMatchRegularExpression( '/business/i', $template['title'] . ' ' . $template['description'], $name );
			$this->assertDoesNotMatchRegularExpression( '/\s\p{Lu}\p{Ll}/u', $template['title'], "$name is not sentence case" );
		}
	}
}
