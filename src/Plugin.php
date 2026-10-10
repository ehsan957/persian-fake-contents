<?php
/**
 * Plugin bootstrap.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin into WordPress.
 */
final class Plugin {

	/**
	 * Meta key that marks everything this plugin creates (kept from 1.x so old fakes can still be purged).
	 */
	const META_KEY = '_grizzly';

	/**
	 * Meta value used together with META_KEY.
	 */
	const META_VALUE = 'fake';

	/**
	 * Register hooks.
	 */
	public static function init() {
		( new Rest\Controller() )->register();
		( new Block() )->register();

		if ( is_admin() ) {
			( new Admin\Page() )->register();
			add_filter( 'plugin_action_links_' . plugin_basename( GFPC_FILE ), array( __CLASS__, 'action_links' ) );
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'persian-fake', CLI\Command::class );
		}
	}

	/**
	 * Add a shortcut to the generator on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift(
			$links,
			sprintf( '<a href="%s">%s</a>', esc_url( Admin\Page::url() ), esc_html__( 'Generate', 'persian-fake-contents' ) )
		);
		return $links;
	}
}
