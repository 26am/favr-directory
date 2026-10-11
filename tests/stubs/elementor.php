<?php
/**
 * Stand-ins for the Elementor classes the widgets and dynamic tags extend, so their words can be
 * read in a unit test. Elementor itself is never loaded.
 *
 * @package FavrDirectory
 */

// phpcs:ignoreFile -- test stubs.

namespace Elementor {
	if ( ! class_exists( Widget_Base::class ) ) {
		abstract class Widget_Base {}
	}
	if ( ! class_exists( Controls_Manager::class ) ) {
		class Controls_Manager {
			public const TAB_CONTENT = 'content';
			public const SELECT      = 'select';
			public const TEXT        = 'text';
			public const TEXTAREA    = 'textarea';
			public const NUMBER      = 'number';
			public const SWITCHER    = 'switcher';
		}
	}
}

namespace Elementor\Core\DynamicTags {
	if ( ! class_exists( Tag::class ) ) {
		abstract class Tag {}
	}
	if ( ! class_exists( Data_Tag::class ) ) {
		abstract class Data_Tag {}
	}
}

namespace Elementor\Modules\DynamicTags {
	if ( ! class_exists( Module::class ) ) {
		class Module {
			public const TEXT_CATEGORY  = 'text';
			public const URL_CATEGORY   = 'url';
			public const IMAGE_CATEGORY = 'image';
		}
	}
}
