<?php
/**
 * Tier detection. Phase 1 stub — replace with Freemius is_plan() in Phase 3.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class PV_Tier {

	public static function current(): string {
		// Phase 3: replace with actual Freemius check.
		// e.g. if ( pv_fs()->is_plan('platinum') ) return 'platinum';
		//      if ( pv_fs()->is_plan('gold') )     return 'gold';
		return 'gold'; // DEV: bumped for UI testing; revert before launch
	}

	public static function meets( string $required ): bool {
		$order = [ 'silver' => 1, 'gold' => 2, 'platinum' => 3 ];
		return ( $order[ self::current() ] ?? 0 ) >= ( $order[ $required ] ?? 0 );
	}

	public static function is_gold(): bool {
		return self::meets( 'gold' );
	}

	public static function is_platinum(): bool {
		return self::meets( 'platinum' );
	}

	public static function get_video_limit(): int {
		return match ( self::current() ) {
			'platinum' => PHP_INT_MAX,
			'gold'     => 100,
			default    => 6,
		};
	}
}
