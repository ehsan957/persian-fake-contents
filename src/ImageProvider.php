<?php
/**
 * Featured image sources.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates featured images, either drawn locally with GD or downloaded from picsum.photos.
 */
class ImageProvider {

	const WIDTH  = 1200;
	const HEIGHT = 628;

	/**
	 * Available sources and their labels.
	 *
	 * @return array<string,string>
	 */
	public static function sources() {
		$sources = array( 'none' => __( 'No image', 'persian-fake-contents' ) );
		if ( self::gd_available() ) {
			$sources['placeholder'] = __( 'Placeholder (generated locally)', 'persian-fake-contents' );
		}
		$sources['picsum'] = __( 'Random photo (downloaded from picsum.photos)', 'persian-fake-contents' );
		return $sources;
	}

	/**
	 * Whether GD can draw placeholders.
	 *
	 * @return bool
	 */
	public static function gd_available() {
		return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagejpeg' );
	}

	/**
	 * Create an image, attach it to the post and set it as the featured image.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $source  'placeholder' or 'picsum'.
	 * @return int|WP_Error Attachment ID.
	 */
	public function attach( $post_id, $source ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = 'picsum' === $source ? $this->download() : $this->draw();
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file = array(
			'name'     => 'persian-fake-' . $post_id . '.jpg',
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post_id );
		if ( file_exists( $tmp ) ) {
			wp_delete_file( $tmp );
		}
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		add_post_meta( $attachment_id, Plugin::META_KEY, Plugin::META_VALUE, true );
		set_post_thumbnail( $post_id, $attachment_id );
		return $attachment_id;
	}

	/**
	 * Download a random photo to a temp file.
	 *
	 * @return string|WP_Error Temp file path.
	 */
	private function download() {
		$url = sprintf( 'https://picsum.photos/%d/%d.jpg', self::WIDTH, self::HEIGHT );
		return download_url( $url, 30 );
	}

	/**
	 * Draw a coloured placeholder to a temp file.
	 *
	 * @return string|WP_Error Temp file path.
	 */
	private function draw() {
		if ( ! self::gd_available() ) {
			return new WP_Error( 'gfpc_no_gd', __( 'The GD extension is not available.', 'persian-fake-contents' ) );
		}

		$image = imagecreatetruecolor( self::WIDTH, self::HEIGHT );
		$hue   = wp_rand( 0, 359 );

		// Vertical gradient between two shades of a random hue.
		list( $r1, $g1, $b1 ) = $this->hsl_to_rgb( $hue, 0.55, 0.55 );
		list( $r2, $g2, $b2 ) = $this->hsl_to_rgb( ( $hue + 40 ) % 360, 0.55, 0.35 );
		for ( $y = 0; $y < self::HEIGHT; $y++ ) {
			$t     = $y / self::HEIGHT;
			$color = imagecolorallocate(
				$image,
				(int) ( $r1 + ( $r2 - $r1 ) * $t ),
				(int) ( $g1 + ( $g2 - $g1 ) * $t ),
				(int) ( $b1 + ( $b2 - $b1 ) * $t )
			);
			imageline( $image, 0, $y, self::WIDTH, $y, $color );
		}

		$label = self::WIDTH . ' x ' . self::HEIGHT;
		$white = imagecolorallocate( $image, 255, 255, 255 );
		$font  = 5;
		imagestring(
			$image,
			$font,
			(int) ( ( self::WIDTH - imagefontwidth( $font ) * strlen( $label ) ) / 2 ),
			(int) ( ( self::HEIGHT - imagefontheight( $font ) ) / 2 ),
			$label,
			$white
		);

		$tmp = wp_tempnam( 'persian-fake.jpg' );
		$ok  = imagejpeg( $image, $tmp, 85 );

		if ( ! $ok ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'gfpc_draw_failed', __( 'Could not create the placeholder image.', 'persian-fake-contents' ) );
		}
		return $tmp;
	}

	/**
	 * Convert HSL to RGB.
	 *
	 * @param int   $h Hue 0-359.
	 * @param float $s Saturation 0-1.
	 * @param float $l Lightness 0-1.
	 * @return int[]
	 */
	private function hsl_to_rgb( $h, $s, $l ) {
		$c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
		$x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
		$m = $l - $c / 2;

		$sector = intdiv( $h, 60 );
		$rgb    = array(
			array( $c, $x, 0 ),
			array( $x, $c, 0 ),
			array( 0, $c, $x ),
			array( 0, $x, $c ),
			array( $x, 0, $c ),
			array( $c, 0, $x ),
		)[ $sector ];

		return array_map(
			static function ( $v ) use ( $m ) {
				return (int) round( ( $v + $m ) * 255 );
			},
			$rgb
		);
	}
}
