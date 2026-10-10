<?php
/**
 * Tools > Persian Fake Contents screen.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen. The UI itself is the React app in js/admin, talking to the REST API.
 */
class Page {

	const SLUG = 'persian-fake-contents';

	/**
	 * Hook suffix returned by add_management_page().
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * URL of the screen.
	 *
	 * @param array<string,mixed> $args Extra query args.
	 * @return string
	 */
	public static function url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'tools.php' ) );
	}

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}

	/**
	 * Add the Tools submenu.
	 */
	public function add_menu() {
		$this->hook = (string) add_management_page(
			__( 'Persian Fake Contents', 'persian-fake-contents' ),
			__( 'Persian Fake Contents', 'persian-fake-contents' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Load the React app on this screen only.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}

		$asset_file = GFPC_DIR . 'build/admin.asset.php';
		if ( ! is_readable( $asset_file ) ) {
			return;
		}
		$asset = require $asset_file;

		wp_enqueue_script( 'gfpc-admin', GFPC_URL . 'build/admin.js', $asset['dependencies'], $asset['version'], true );
		wp_set_script_translations( 'gfpc-admin', 'persian-fake-contents' );
		wp_enqueue_style( 'gfpc-admin', GFPC_URL . 'build/style-admin.css', array( 'wp-components' ), $asset['version'] );
		wp_style_add_data( 'gfpc-admin', 'rtl', 'replace' );
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Persian Fake Contents', 'persian-fake-contents' ); ?></h1>
			<?php if ( ! is_readable( GFPC_DIR . 'build/admin.asset.php' ) ) : ?>
				<div class="notice notice-error"><p>
					<?php
					/* translators: %s: build command */
					printf( esc_html__( 'The admin app is not built. Run %s in the plugin folder, or install the plugin from WordPress.org.', 'persian-fake-contents' ), '<code>npm install &amp;&amp; npm run build</code>' );
					?>
				</p></div>
			<?php endif; ?>
			<div id="gfpc-app"></div>
		</div>
		<?php
	}
}
