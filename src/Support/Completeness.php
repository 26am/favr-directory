<?php
/**
 * Profile completeness scoring.
 *
 * @package FavrDirectory
 */

declare(strict_types=1);

namespace FavrDirectory\Support;

/**
 * Scores how complete a listing is from field weights, so staff can see at a glance which
 * businesses need attention. Pure: values in, integer percent out.
 */
final class Completeness {

	/** Weights for things that are not registry fields. */
	public const CORE_WEIGHTS = array(
		'title'       => 10,
		'description' => 10,
		'category'    => 10,
		'cover'       => 6,
	);

	/**
	 * Score 0–100.
	 *
	 * @param array<string, mixed>                $values Field id => stored value.
	 * @param array<string, array<string, mixed>> $fields Field definitions.
	 * @param array<string, bool>                 $core   CORE_WEIGHTS key => present.
	 */
	public static function score( array $values, array $fields, array $core ): int {
		$total  = 0;
		$earned = 0;

		foreach ( self::CORE_WEIGHTS as $key => $weight ) {
			$total += $weight;
			if ( ! empty( $core[ $key ] ) ) {
				$earned += $weight;
			}
		}

		foreach ( $fields as $id => $field ) {
			$weight = (int) ( $field['weight'] ?? 0 );
			if ( $weight <= 0 ) {
				continue;
			}
			$total += $weight;
			if ( self::filled( $values[ $id ] ?? null ) ) {
				$earned += $weight;
			}
		}

		return 0 === $total ? 0 : (int) round( $earned / $total * 100 );
	}

	/**
	 * Missing weighted items, heaviest first (for "what to add next" hints).
	 *
	 * @param array<string, mixed>                $values Field id => stored value.
	 * @param array<string, array<string, mixed>> $fields Field definitions.
	 * @param array<string, bool>                 $core   CORE_WEIGHTS key => present.
	 * @return list<string> Field ids / core keys.
	 */
	public static function missing( array $values, array $fields, array $core ): array {
		$missing = array();
		foreach ( self::CORE_WEIGHTS as $key => $weight ) {
			if ( empty( $core[ $key ] ) ) {
				$missing[ $key ] = $weight;
			}
		}
		foreach ( $fields as $id => $field ) {
			$weight = (int) ( $field['weight'] ?? 0 );
			if ( $weight > 0 && ! self::filled( $values[ $id ] ?? null ) ) {
				$missing[ $id ] = $weight;
			}
		}
		arsort( $missing );
		return array_map( 'strval', array_keys( $missing ) );
	}

	/**
	 * Whether a stored value counts as filled in.
	 *
	 * @param mixed $value Value.
	 */
	private static function filled( $value ): bool {
		if ( is_array( $value ) ) {
			return array() !== $value;
		}
		return null !== $value && '' !== $value && 0 !== $value && '0' !== $value;
	}
}
