<?php
/**
 * Persian word source.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents;

defined( 'ABSPATH' ) || exit;

/**
 * Picks Persian words, favouring frequent ones so text reads more naturally.
 */
class Dictionary {

	/**
	 * How many of the most frequent words count as "common".
	 */
	const COMMON_SIZE = 300;

	/**
	 * Chance (0-100) that a picked word comes from the common set.
	 */
	const COMMON_CHANCE = 35;

	/**
	 * All words, most frequent first.
	 *
	 * @var string[]
	 */
	private $words;

	/**
	 * Index of the last word.
	 *
	 * @var int
	 */
	private $last;

	/**
	 * Load the bundled word list.
	 *
	 * @param string[]|null $words Optional custom list, most frequent first.
	 */
	public function __construct( $words = null ) {
		if ( null === $words ) {
			$words = require GFPC_DIR . 'data/words.php';
		}

		/**
		 * Filters the Persian word list used to build fake text.
		 *
		 * @param string[] $words Words, most frequent first.
		 */
		$this->words = array_values( (array) apply_filters( 'gfpc_words', $words ) );
		$this->last  = count( $this->words ) - 1;
	}

	/**
	 * A single random word.
	 *
	 * @return string
	 */
	public function word() {
		if ( wp_rand( 1, 100 ) <= self::COMMON_CHANCE ) {
			return $this->words[ wp_rand( 0, min( self::COMMON_SIZE, $this->last ) ) ];
		}
		return $this->words[ wp_rand( 0, $this->last ) ];
	}

	/**
	 * Several random words.
	 *
	 * @param int $count Number of words.
	 * @return string[]
	 */
	public function words( $count ) {
		$out = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$out[] = $this->word();
		}
		return $out;
	}
}
