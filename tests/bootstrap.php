<?php
/**
 * Unit test bootstrap: pure logic only, WordPress functions are stubbed with Brain Monkey.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/vendor-prefixed/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', sys_get_temp_dir() . '/' );
}

if ( ! class_exists( 'WP_Query' ) ) {
	/** Minimal stand-in for tests: query vars and the main-query flag. */
	class WP_Query {
		/** @var array<string, mixed> */
		public $query_vars;
		/** @var bool */
		public $main;
		public function __construct( array $query_vars = array(), bool $main = true ) {
			$this->query_vars = $query_vars;
			$this->main       = $main;
		}
		/** @return mixed */
		public function get( string $key, $fallback = '' ) {
			return $this->query_vars[ $key ] ?? $fallback;
		}
		public function is_main_query(): bool {
			return $this->main;
		}
	}
}

if ( ! class_exists( 'wpdb' ) ) {
	/** Minimal stand-in for tests: table names, LIKE escaping and %s placeholders. */
	class wpdb {
		/** @var string */
		public $posts = 'wp_posts';
		/** @var string */
		public $postmeta = 'wp_postmeta';
		public function esc_like( string $text ): string {
			return addcslashes( $text, '_%\\' );
		}
		/** @param mixed ...$args Values, or one array of values. */
		public function prepare( string $query, ...$args ): string {
			if ( isset( $args[0] ) && is_array( $args[0] ) ) {
				$args = $args[0];
			}
			$quoted = array_map( static fn( $value ) => "'" . addslashes( (string) $value ) . "'", $args );
			return vsprintf( $query, $quoted );
		}
	}
}

require_once __DIR__ . '/stubs/elementor.php';
