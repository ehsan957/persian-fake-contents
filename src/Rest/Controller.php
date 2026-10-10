<?php
/**
 * REST API used by the admin screen and the editor block.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents\Rest;

use Grizzly\PersianFakeContents\Cleanup;
use Grizzly\PersianFakeContents\Dictionary;
use Grizzly\PersianFakeContents\Generator;
use Grizzly\PersianFakeContents\ImageProvider;
use Grizzly\PersianFakeContents\TextGenerator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Routes under /persian-fake/v1.
 */
class Controller {

	const NAMESPACE = 'persian-fake/v1';

	/**
	 * Most posts one /generate request creates; the admin screen loops for more.
	 */
	const GENERATE_BATCH = 5;

	/**
	 * Most items one DELETE /items request removes.
	 */
	const PURGE_BATCH = 25;

	/**
	 * User meta key for the last used options.
	 */
	const SAVED_OPTIONS = 'gfpc_last_options';

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * Register routes.
	 */
	public function routes() {
		$admin = static function () {
			return current_user_can( 'manage_options' );
		};

		register_rest_route(
			self::NAMESPACE,
			'/config',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'config' ),
				'permission_callback' => $admin,
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/generate',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'generate' ),
				'permission_callback' => $admin,
				'args'                => array(
					'options' => array(
						'type'     => 'object',
						'required' => true,
					),
					'count'   => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => self::GENERATE_BATCH,
						'default' => 1,
					),
					'save'    => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/items',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'items' ),
					'permission_callback' => $admin,
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'minimum' => 1,
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'minimum' => 1,
							'maximum' => 100,
							'default' => 20,
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'purge' ),
					'permission_callback' => $admin,
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/text',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'text' ),
				'permission_callback' => static function () {
					return current_user_can( 'edit_posts' );
				},
				'args'                => array(
					'paragraphs' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 20,
						'default' => 3,
					),
					'headings'   => array(
						'type'    => 'boolean',
						'default' => false,
					),
				),
			)
		);
	}

	/**
	 * Everything the admin form needs to render.
	 *
	 * @return WP_REST_Response
	 */
	public function config() {
		$post_types = array();
		foreach ( Generator::post_types() as $name => $label ) {
			$post_types[] = array(
				'value'      => $name,
				'label'      => $label,
				'product'    => Generator::is_product( $name ),
				'categories' => is_object_in_taxonomy( $name, Generator::category_taxonomy( $name ) ),
				'tags'       => is_object_in_taxonomy( $name, Generator::is_product( $name ) ? 'product_tag' : 'post_tag' ),
				'comments'   => post_type_supports( $name, 'comments' ),
				'thumbnail'  => post_type_supports( $name, 'thumbnail' ),
			);
		}

		$authors = array();
		foreach ( get_users(
			array(
				'capability' => array( 'edit_posts' ),
				'number'     => 200,
			)
		) as $user ) {
			$authors[] = array(
				'value' => $user->ID,
				'label' => $user->display_name,
			);
		}

		$categories = array();
		foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) {
			$categories[] = array(
				'value' => $category->term_id,
				'label' => $category->name,
			);
		}

		$saved = get_user_meta( get_current_user_id(), self::SAVED_OPTIONS, true );

		return rest_ensure_response(
			array(
				'postTypes'    => $post_types,
				'statuses'     => self::pairs( Generator::statuses() ),
				'authors'      => $authors,
				'categories'   => $categories,
				'imageSources' => self::pairs( ImageProvider::sources() ),
				'random'       => Generator::RANDOM,
				'options'      => wp_parse_args( is_array( $saved ) ? $saved : array(), Generator::defaults() ),
				'batch'        => self::GENERATE_BATCH,
			)
		);
	}

	/**
	 * Create one batch of posts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|\WP_Error
	 */
	public function generate( WP_REST_Request $request ) {
		$generator = new Generator();
		$options   = $generator->normalize( (array) $request['options'] );
		if ( is_wp_error( $options ) ) {
			$options->add_data( array( 'status' => 400 ) );
			return $options;
		}

		if ( $request['save'] ) {
			update_user_meta( get_current_user_id(), self::SAVED_OPTIONS, $options );
		}

		$options['count'] = (int) $request['count'];
		$ids              = $generator->generate( $options );

		return rest_ensure_response(
			array(
				'ids'      => $ids,
				'warnings' => $generator->warnings,
			)
		);
	}

	/**
	 * A page of fake posts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function items( WP_REST_Request $request ) {
		$all      = ( new Cleanup() )->post_ids();
		$per_page = (int) $request['per_page'];
		$ids      = array_slice( $all, ( (int) $request['page'] - 1 ) * $per_page, $per_page );

		$items = array();
		foreach ( $ids as $id ) {
			$type   = get_post_type_object( get_post_type( $id ) );
			$status = get_post_status_object( get_post_status( $id ) );

			$items[] = array(
				'id'       => $id,
				'title'    => get_the_title( $id ),
				'type'     => $type ? $type->labels->singular_name : get_post_type( $id ),
				'status'   => $status ? $status->label : get_post_status( $id ),
				'date'     => get_the_date( '', $id ),
				'editLink' => get_edit_post_link( $id, 'raw' ),
				'viewLink' => get_permalink( $id ),
			);
		}

		return rest_ensure_response(
			array(
				'items' => $items,
				'total' => count( $all ),
			)
		);
	}

	/**
	 * Delete one batch of fake content.
	 *
	 * @return WP_REST_Response
	 */
	public function purge() {
		$cleanup = new Cleanup();
		$deleted = $cleanup->purge( null, self::PURGE_BATCH );

		return rest_ensure_response(
			array(
				'deleted'   => $deleted,
				'remaining' => count( $cleanup->post_ids( true ) ),
			)
		);
	}

	/**
	 * Persian paragraphs for the editor block.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function text( WP_REST_Request $request ) {
		$text   = new TextGenerator( new Dictionary() );
		$blocks = array();

		for ( $i = 1; $i <= (int) $request['paragraphs']; $i++ ) {
			if ( $request['headings'] && $i > 1 && 0 === ( $i - 1 ) % 3 ) {
				$blocks[] = array(
					'type' => 'heading',
					'text' => $text->phrase( 2, 6 ),
				);
			}
			$blocks[] = array(
				'type' => 'paragraph',
				'text' => $text->paragraph(),
			);
		}

		return rest_ensure_response( array( 'blocks' => $blocks ) );
	}

	/**
	 * Turn value => label into a list of {value, label}.
	 *
	 * @param array<string,string> $map Map.
	 * @return array<int,array<string,string>>
	 */
	private static function pairs( $map ) {
		$out = array();
		foreach ( $map as $value => $label ) {
			$out[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $out;
	}
}
