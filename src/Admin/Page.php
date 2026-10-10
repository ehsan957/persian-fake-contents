<?php
/**
 * Tools > Persian Fake Contents screen.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents\Admin;

use Grizzly\PersianFakeContents\Cleanup;
use Grizzly\PersianFakeContents\Generator;
use Grizzly\PersianFakeContents\ImageProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Admin screen: generation form, list of fake content and bulk delete.
 */
class Page {

	const SLUG = 'persian-fake-contents';

	/**
	 * Most posts the admin form creates per request; WP-CLI has no limit.
	 */
	const MAX_PER_REQUEST = 50;

	/**
	 * How many fake posts the list shows.
	 */
	const LIST_LIMIT = 100;

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

		add_action( 'load-' . $this->hook, array( $this, 'handle' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Load the screen's stylesheet.
	 *
	 * @param string $hook Current screen hook.
	 */
	public function enqueue( $hook ) {
		if ( $hook === $this->hook ) {
			wp_enqueue_style( 'gfpc-admin', GFPC_URL . 'assets/admin.css', array(), GFPC_VERSION );
		}
	}

	/**
	 * Process form submissions, then redirect (post/redirect/get).
	 */
	public function handle() {
		if ( empty( $_POST['gfpc_action'] ) ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'persian-fake-contents' ), 403 );
		}

		$action = sanitize_key( wp_unslash( $_POST['gfpc_action'] ) );

		if ( 'generate' === $action ) {
			check_admin_referer( 'gfpc_generate' );
			$this->handle_generate();
		} elseif ( 'purge' === $action ) {
			check_admin_referer( 'gfpc_purge' );
			$deleted = ( new Cleanup() )->purge();
			$this->redirect( array( 'gfpc_deleted' => $deleted ) );
		}
	}

	/**
	 * Run the generator with the submitted options.
	 */
	private function handle_generate() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle().
		$options = array();
		foreach ( array_keys( Generator::defaults() ) as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$options[ $key ] = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
			}
		}
		// phpcs:enable

		$options['count'] = min( self::MAX_PER_REQUEST, absint( $options['count'] ?? 1 ) );

		// Remember the choices for next time.
		update_user_meta( get_current_user_id(), 'gfpc_last_options', $options );

		$generator = new Generator();
		$result    = $generator->generate( $options );

		if ( is_wp_error( $result ) ) {
			$this->redirect( array( 'gfpc_error' => rawurlencode( $result->get_error_message() ) ) );
		}

		$args = array( 'gfpc_created' => count( $result ) );
		if ( $generator->warnings ) {
			$args['gfpc_warnings'] = count( $generator->warnings );
		}
		$this->redirect( $args );
	}

	/**
	 * Redirect back to the screen and stop.
	 *
	 * @param array<string,mixed> $args Query args describing the result.
	 */
	private function redirect( $args ) {
		wp_safe_redirect( self::url( $args ) );
		exit;
	}

	/**
	 * Saved form values for the current user.
	 *
	 * @return array<string,mixed>
	 */
	private function form_values() {
		$saved = get_user_meta( get_current_user_id(), 'gfpc_last_options', true );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), Generator::defaults() );
	}

	/**
	 * Result notices after a redirect.
	 */
	private function notices() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		if ( isset( $_GET['gfpc_created'] ) ) {
			$count = absint( $_GET['gfpc_created'] );
			/* translators: %s: number of created items */
			$this->notice( sprintf( _n( '%s fake item created.', '%s fake items created.', $count, 'persian-fake-contents' ), number_format_i18n( $count ) ), 'success' );
		}
		if ( isset( $_GET['gfpc_warnings'] ) ) {
			$count = absint( $_GET['gfpc_warnings'] );
			/* translators: %s: number of problems */
			$this->notice( sprintf( _n( '%s problem occurred (for example a featured image could not be downloaded).', '%s problems occurred (for example featured images could not be downloaded).', $count, 'persian-fake-contents' ), number_format_i18n( $count ) ), 'warning' );
		}
		if ( isset( $_GET['gfpc_deleted'] ) ) {
			$count = absint( $_GET['gfpc_deleted'] );
			/* translators: %s: number of deleted items */
			$this->notice( sprintf( _n( '%s fake item deleted.', '%s fake items deleted.', $count, 'persian-fake-contents' ), number_format_i18n( $count ) ), 'success' );
		}
		if ( isset( $_GET['gfpc_error'] ) ) {
			$this->notice( sanitize_text_field( wp_unslash( $_GET['gfpc_error'] ) ), 'error' );
		}
		// phpcs:enable
	}

	/**
	 * Print an admin notice.
	 *
	 * @param string $message Message.
	 * @param string $type    success|warning|error.
	 */
	private function notice( $message, $type ) {
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $message ) );
	}

	/**
	 * Render the screen.
	 */
	public function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$v       = $this->form_values();
		$fakes   = ( new Cleanup() )->post_ids();
		$authors = get_users(
			array(
				'capability' => array( 'edit_posts' ),
				'number'     => 200,
			)
		);
		?>
		<div class="wrap gfpc">
			<h1><?php esc_html_e( 'Persian Fake Contents', 'persian-fake-contents' ); ?></h1>
			<?php $this->notices(); ?>

			<div class="gfpc-card">
				<h2><?php esc_html_e( 'Generate', 'persian-fake-contents' ); ?></h2>
				<form method="post">
					<input type="hidden" name="gfpc_action" value="generate">
					<?php wp_nonce_field( 'gfpc_generate' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="gfpc-count"><?php esc_html_e( 'How many', 'persian-fake-contents' ); ?></label></th>
							<td>
								<input type="number" id="gfpc-count" name="count" min="1" max="<?php echo esc_attr( self::MAX_PER_REQUEST ); ?>" value="<?php echo esc_attr( min( self::MAX_PER_REQUEST, absint( $v['count'] ) ) ); ?>" class="small-text">
								<p class="description">
									<?php
									/* translators: 1: maximum number, 2: WP-CLI command */
									printf( esc_html__( 'Up to %1$d at a time. For more, use %2$s.', 'persian-fake-contents' ), (int) self::MAX_PER_REQUEST, '<code>wp persian-fake generate</code>' );
									?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gfpc-type"><?php esc_html_e( 'Post type', 'persian-fake-contents' ); ?></label></th>
							<td><?php $this->select( 'post_type', 'gfpc-type', Generator::post_types(), $v['post_type'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="gfpc-status"><?php esc_html_e( 'Status', 'persian-fake-contents' ); ?></label></th>
							<td><?php $this->select( 'status', 'gfpc-status', Generator::statuses(), $v['status'] ); ?></td>
						</tr>
						<tr>
							<th scope="row"><label for="gfpc-author"><?php esc_html_e( 'Author', 'persian-fake-contents' ); ?></label></th>
							<td>
								<?php
								$author_options = array( 0 => __( 'Random', 'persian-fake-contents' ) );
								foreach ( $authors as $user ) {
									$author_options[ $user->ID ] = $user->display_name;
								}
								$this->select( 'author', 'gfpc-author', $author_options, $v['author'] );
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gfpc-category"><?php esc_html_e( 'Category', 'persian-fake-contents' ); ?></label></th>
							<td>
								<?php
								$category_options = array(
									0                 => __( 'Default', 'persian-fake-contents' ),
									Generator::RANDOM => __( 'Random', 'persian-fake-contents' ),
								);
								foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) {
									$category_options[ $category->term_id ] = $category->name;
								}
								$this->select( 'category', 'gfpc-category', $category_options, $v['category'] );
								?>
								<p class="description"><?php esc_html_e( 'Only used by post types that have categories.', 'persian-fake-contents' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Content', 'persian-fake-contents' ); ?></th>
							<td>
								<label>
									<?php esc_html_e( 'Paragraphs from', 'persian-fake-contents' ); ?>
									<input type="number" name="paragraphs_min" min="1" max="20" value="<?php echo esc_attr( $v['paragraphs_min'] ); ?>" class="small-text">
								</label>
								<label>
									<?php esc_html_e( 'to', 'persian-fake-contents' ); ?>
									<input type="number" name="paragraphs_max" min="1" max="20" value="<?php echo esc_attr( $v['paragraphs_max'] ); ?>" class="small-text">
								</label>
								<br>
								<?php
								$this->select(
									'format',
									'gfpc-format',
									array(
										'blocks'  => __( 'Block editor (headings, lists, quotes)', 'persian-fake-contents' ),
										'classic' => __( 'Classic HTML', 'persian-fake-contents' ),
									),
									$v['format']
								);
								?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Extras', 'persian-fake-contents' ); ?></th>
							<td>
								<label>
									<?php esc_html_e( 'Up to', 'persian-fake-contents' ); ?>
									<input type="number" name="tags" min="0" max="10" value="<?php echo esc_attr( $v['tags'] ); ?>" class="small-text">
									<?php esc_html_e( 'tags per post', 'persian-fake-contents' ); ?>
								</label>
								<br>
								<label>
									<?php esc_html_e( 'Up to', 'persian-fake-contents' ); ?>
									<input type="number" name="comments" min="0" max="20" value="<?php echo esc_attr( $v['comments'] ); ?>" class="small-text">
									<?php esc_html_e( 'comments per post', 'persian-fake-contents' ); ?>
								</label>
								<br>
								<label>
									<?php esc_html_e( 'Spread dates over the last', 'persian-fake-contents' ); ?>
									<input type="number" name="days" min="0" value="<?php echo esc_attr( $v['days'] ); ?>" class="small-text">
									<?php esc_html_e( 'days (0 = now)', 'persian-fake-contents' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="gfpc-image"><?php esc_html_e( 'Featured image', 'persian-fake-contents' ); ?></label></th>
							<td>
								<?php $this->select( 'image', 'gfpc-image', ImageProvider::sources(), $v['image'] ); ?>
								<p class="description"><?php esc_html_e( 'Downloading photos is slower; use fewer posts per run.', 'persian-fake-contents' ); ?></p>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Generate', 'persian-fake-contents' ) ); ?>
				</form>
			</div>

			<div class="gfpc-card">
				<h2>
					<?php
					/* translators: %s: number of fake items */
					echo esc_html( sprintf( __( 'Fake content (%s)', 'persian-fake-contents' ), number_format_i18n( count( $fakes ) ) ) );
					?>
				</h2>

				<?php if ( ! $fakes ) : ?>
					<p><?php esc_html_e( 'Nothing generated yet.', 'persian-fake-contents' ); ?></p>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Title', 'persian-fake-contents' ); ?></th>
								<th><?php esc_html_e( 'Type', 'persian-fake-contents' ); ?></th>
								<th><?php esc_html_e( 'Status', 'persian-fake-contents' ); ?></th>
								<th><?php esc_html_e( 'Date', 'persian-fake-contents' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( array_slice( $fakes, 0, self::LIST_LIMIT ) as $id ) : ?>
								<?php $type = get_post_type_object( get_post_type( $id ) ); ?>
								<tr>
									<td class="gfpc-title"><a href="<?php echo esc_url( (string) get_edit_post_link( $id ) ); ?>"><?php echo esc_html( get_the_title( $id ) ); ?></a></td>
									<td><?php echo esc_html( $type ? $type->labels->singular_name : get_post_type( $id ) ); ?></td>
									<td><?php echo esc_html( get_post_status_object( get_post_status( $id ) )->label ?? get_post_status( $id ) ); ?></td>
									<td><?php echo esc_html( get_the_date( '', $id ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php if ( count( $fakes ) > self::LIST_LIMIT ) : ?>
						<p class="description">
							<?php
							/* translators: %s: number of items */
							echo esc_html( sprintf( __( 'Showing the latest %s.', 'persian-fake-contents' ), number_format_i18n( self::LIST_LIMIT ) ) );
							?>
						</p>
					<?php endif; ?>

					<form method="post" class="gfpc-purge">
						<input type="hidden" name="gfpc_action" value="purge">
						<?php wp_nonce_field( 'gfpc_purge' ); ?>
						<?php
						submit_button(
							__( 'Delete all fake content', 'persian-fake-contents' ),
							'delete',
							'submit',
							false,
							array( 'onclick' => 'return confirm(' . wp_json_encode( __( 'Delete all fake posts, their featured images, comments and fake tags? This cannot be undone.', 'persian-fake-contents' ) ) . ');' )
						);
						?>
					</form>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Print a select box.
	 *
	 * @param string               $name     Field name.
	 * @param string               $id       Element ID.
	 * @param array<string,string> $options Value => label.
	 * @param mixed                $current  Selected value.
	 */
	private function select( $name, $id, $options, $current ) {
		printf( '<select name="%s" id="%s">', esc_attr( $name ), esc_attr( $id ) );
		foreach ( $options as $value => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $value ),
				selected( (string) $value, (string) $current, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}
}
