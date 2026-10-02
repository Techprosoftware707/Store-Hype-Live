<?php
/**
 * Seeded pseudo-random number generator.
 *
 * @package AlwaysFinal\LiveHype
 */

namespace AlwaysFinal\LiveHype;

defined( 'ABSPATH' ) || exit;

/**
 * Small seeded PRNG (xorshift32) so a visitor seed yields a stable sequence
 * without touching PHP's global random state.
 */
final class Rng {

	/**
	 * State.
	 *
	 * @var int
	 */
	private $state;

	/**
	 * Constructor.
	 *
	 * @param int $seed Seed.
	 */
	public function __construct( int $seed ) {
		$seed        = $seed & 0xFFFFFFFF;
		$this->state = 0 === $seed ? 2463534242 : $seed;
	}

	/**
	 * Next float in [0,1).
	 *
	 * @return float
	 */
	public function next(): float {
		$x           = $this->state;
		$x          ^= ( $x << 13 ) & 0xFFFFFFFF;
		$x          ^= $x >> 17;
		$x          ^= ( $x << 5 ) & 0xFFFFFFFF;
		$this->state = $x & 0xFFFFFFFF;
		return $this->state / 4294967296;
	}

	/**
	 * Weighted choice.
	 *
	 * @param array $options key => positive weight.
	 * @return int|string|null Chosen key or null when nothing is selectable.
	 */
	public function weighted( array $options ) {
		$total = 0.0;
		foreach ( $options as $weight ) {
			$total += max( 0, (float) $weight );
		}
		if ( $total <= 0 ) {
			return null;
		}
		$roll = $this->next() * $total;
		foreach ( $options as $key => $weight ) {
			$roll -= max( 0, (float) $weight );
			if ( $roll < 0 ) {
				return $key;
			}
		}
		$keys = array_keys( $options );
		return end( $keys );
	}
}
