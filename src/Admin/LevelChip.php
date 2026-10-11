<?php
/**
 * A membership level as a chip.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Admin;

/**
 * The level's own colour as a dot, then its name: the chip Favr Members prints in its Level
 * column (`span.favr-level-chip`). Here the chip is the link WordPress made for the Directory
 * list's Level column, so it still opens the list filtered by that level. Favr Sites draws the
 * markup as a pill for Editors; for everyone else it is the link with the level's colour beside it.
 */
final class LevelChip {

	/**
	 * Turn the link WordPress printed for a level into the chip. The address and the name stay
	 * exactly as WordPress wrote (and escaped) them; only the class and the dot are added.
	 *
	 * @param string $link  The link: `<a href="…">Name</a>`.
	 * @param string $color The level's colour (term meta `favr_level_color`).
	 * @return string The chip, or the link untouched when it is not that plain link any more
	 *                (another plugin gave it a class, wrapped it or replaced it).
	 */
	public static function link( string $link, string $color ): string {
		if ( 1 !== preg_match( '~^<a\s+([^<>]+)>(.*)</a>$~s', $link, $parts ) ) {
			return $link;
		}
		if ( 1 === preg_match( '~(^|\s)class\s*=~i', $parts[1] ) || 1 === preg_match( '~</?a[\s>]~i', $parts[2] ) ) {
			return $link;
		}
		return '<a class="favr-level-chip" ' . $parts[1] . '>' . self::swatch( $color ) . $parts[2] . '</a>';
	}

	/**
	 * The colour dot. A level without a colour, or with something that is not a hex colour, gets none.
	 *
	 * @param string $color Stored colour.
	 */
	public static function swatch( string $color ): string {
		$hex = (string) sanitize_hex_color( $color );
		return '' !== $hex ? sprintf( '<span class="favr-level__swatch" style="background:%s"></span>', esc_attr( $hex ) ) : '';
	}
}
