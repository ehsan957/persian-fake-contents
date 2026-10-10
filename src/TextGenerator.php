<?php
/**
 * Fake Persian text builder.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents;

defined( 'ABSPATH' ) || exit;

/**
 * Builds titles, sentences and post bodies (block or classic markup).
 */
class TextGenerator {

	/**
	 * Word source.
	 *
	 * @var Dictionary
	 */
	private $dictionary;

	/**
	 * Constructor.
	 *
	 * @param Dictionary $dictionary Word source.
	 */
	public function __construct( Dictionary $dictionary ) {
		$this->dictionary = $dictionary;
	}

	/**
	 * Words joined with spaces, without punctuation.
	 *
	 * @param int $min Minimum words.
	 * @param int $max Maximum words.
	 * @return string
	 */
	public function phrase( $min, $max ) {
		return implode( ' ', $this->dictionary->words( wp_rand( $min, $max ) ) );
	}

	/**
	 * A post title.
	 *
	 * @return string
	 */
	public function title() {
		return $this->phrase( 3, 8 );
	}

	/**
	 * One sentence with Persian punctuation.
	 *
	 * @return string
	 */
	public function sentence() {
		$words = $this->dictionary->words( wp_rand( 6, 16 ) );

		if ( count( $words ) > 8 && wp_rand( 1, 100 ) <= 40 ) {
			$at            = wp_rand( 3, count( $words ) - 4 );
			$words[ $at ] .= '،';
		}

		$end = wp_rand( 1, 100 );
		if ( $end <= 8 ) {
			$mark = '؟';
		} elseif ( $end <= 12 ) {
			$mark = '!';
		} else {
			$mark = '.';
		}

		return implode( ' ', $words ) . $mark;
	}

	/**
	 * A paragraph of several sentences.
	 *
	 * @param int $min Minimum sentences.
	 * @param int $max Maximum sentences.
	 * @return string
	 */
	public function paragraph( $min = 3, $max = 7 ) {
		$sentences = array();
		for ( $i = wp_rand( $min, $max ); $i > 0; $i-- ) {
			$sentences[] = $this->sentence();
		}
		return implode( ' ', $sentences );
	}

	/**
	 * A short excerpt.
	 *
	 * @return string
	 */
	public function excerpt() {
		return $this->paragraph( 1, 2 );
	}

	/**
	 * A full post body.
	 *
	 * @param int    $paragraphs Number of paragraphs.
	 * @param string $format     'blocks' or 'classic'.
	 * @return string
	 */
	public function content( $paragraphs, $format = 'blocks' ) {
		$parts = array();

		for ( $i = 1; $i <= $paragraphs; $i++ ) {
			// A heading before every few paragraphs, never before the first one.
			if ( $i > 1 && 0 === ( $i - 1 ) % 3 ) {
				$parts[] = $this->heading( $this->phrase( 2, 6 ), $format );
			}

			$parts[] = $this->paragraph_markup( $this->paragraph(), $format );

			if ( $i < $paragraphs && wp_rand( 1, 100 ) <= 15 ) {
				$parts[] = $this->list_markup( $format );
			} elseif ( $i < $paragraphs && wp_rand( 1, 100 ) <= 10 ) {
				$parts[] = $this->quote( $this->sentence(), $format );
			}
		}

		return implode( "\n\n", $parts );
	}

	/**
	 * Paragraph markup.
	 *
	 * @param string $text   Text.
	 * @param string $format 'blocks' or 'classic'.
	 * @return string
	 */
	private function paragraph_markup( $text, $format ) {
		$html = '<p>' . esc_html( $text ) . '</p>';
		return 'blocks' === $format ? "<!-- wp:paragraph -->\n{$html}\n<!-- /wp:paragraph -->" : $html;
	}

	/**
	 * Heading markup.
	 *
	 * @param string $text   Text.
	 * @param string $format 'blocks' or 'classic'.
	 * @return string
	 */
	private function heading( $text, $format ) {
		if ( 'blocks' === $format ) {
			return "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $text ) . "</h2>\n<!-- /wp:heading -->";
		}
		return '<h2>' . esc_html( $text ) . '</h2>';
	}

	/**
	 * Unordered list markup.
	 *
	 * @param string $format 'blocks' or 'classic'.
	 * @return string
	 */
	private function list_markup( $format ) {
		$items = array();
		for ( $i = wp_rand( 3, 6 ); $i > 0; $i-- ) {
			$li      = '<li>' . esc_html( $this->phrase( 2, 6 ) ) . '</li>';
			$items[] = 'blocks' === $format ? "<!-- wp:list-item -->\n{$li}\n<!-- /wp:list-item -->" : $li;
		}

		if ( 'blocks' === $format ) {
			return "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode( "\n", $items ) . "</ul>\n<!-- /wp:list -->";
		}
		return "<ul>\n" . implode( "\n", $items ) . "\n</ul>";
	}

	/**
	 * Quote markup.
	 *
	 * @param string $text   Text.
	 * @param string $format 'blocks' or 'classic'.
	 * @return string
	 */
	private function quote( $text, $format ) {
		if ( 'blocks' === $format ) {
			return "<!-- wp:quote -->\n<blockquote class=\"wp-block-quote\">"
				. $this->paragraph_markup( $text, 'blocks' )
				. "</blockquote>\n<!-- /wp:quote -->";
		}
		return '<blockquote><p>' . esc_html( $text ) . '</p></blockquote>';
	}
}
