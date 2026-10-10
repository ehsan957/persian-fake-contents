<?php
/**
 * "Persian Lorem" editor block.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the block. It only exists in the editor: it replaces itself with
 * core paragraph/heading blocks, so nothing of it is ever saved to the post.
 */
class Block {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'register_block' ) );
	}

	/**
	 * Register the script and the block type.
	 */
	public function register_block() {
		$asset_file = GFPC_DIR . 'build/lorem.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_register_script( 'gfpc-lorem', GFPC_URL . 'build/lorem.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'gfpc-lorem', 'persian-fake-contents' );

		register_block_type( GFPC_DIR . 'blocks/lorem', array( 'editor_script' => 'gfpc-lorem' ) );
	}
}
