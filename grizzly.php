<?php
/**
 * Plugin Name:       Persian Fake Contents
 * Plugin URI:        https://wordpress.org/plugins/persian-fake-contents/
 * Description:       Generate Persian/Farsi dummy posts, pages and custom post types for theme and plugin development.
 * Version:           2.0.0
 * Requires at least: 6.3
 * Requires PHP:      8.0
 * Author:            Ehsan Shariaty
 * Author URI:        https://grizzly.ir
 * Text Domain:       persian-fake-contents
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package PersianFakeContents
 */

// Main file keeps its original name so existing installs stay active after updating.

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GFPC_VERSION', '2.0.0' );
define( 'GFPC_FILE', __FILE__ );
define( 'GFPC_DIR', plugin_dir_path( __FILE__ ) );
define( 'GFPC_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'Grizzly\\PersianFakeContents\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$file = GFPC_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

Grizzly\PersianFakeContents\Plugin::init();
