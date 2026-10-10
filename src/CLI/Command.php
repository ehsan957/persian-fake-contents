<?php
/**
 * WP-CLI commands.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents\CLI;

use Grizzly\PersianFakeContents\Cleanup;
use Grizzly\PersianFakeContents\Generator;
use WP_CLI;
use WP_CLI\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Generate and remove Persian fake content.
 *
 * ## EXAMPLES
 *
 *     # Create 20 posts with placeholder featured images, spread over the last 90 days.
 *     $ wp persian-fake generate --count=20 --image=placeholder --days=90
 *
 *     # Delete everything the plugin created.
 *     $ wp persian-fake purge --yes
 */
class Command {

	/**
	 * Generate fake posts.
	 *
	 * ## OPTIONS
	 *
	 * [--count=<number>]
	 * : How many posts to create.
	 * ---
	 * default: 5
	 * ---
	 *
	 * [--post_type=<type>]
	 * : Post type.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--status=<status>]
	 * : Post status.
	 * ---
	 * default: publish
	 * options:
	 *   - publish
	 *   - draft
	 *   - pending
	 *   - private
	 * ---
	 *
	 * [--author=<user>]
	 * : Author ID, login or email. Random author when omitted.
	 *
	 * [--category=<id|random>]
	 * : Category ID, or "random" for a random category per post.
	 *
	 * [--tags=<number>]
	 * : Up to this many random tags per post.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--comments=<number>]
	 * : Up to this many comments per post.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--image=<source>]
	 * : Featured image source.
	 * ---
	 * default: none
	 * options:
	 *   - none
	 *   - placeholder
	 *   - picsum
	 * ---
	 *
	 * [--format=<format>]
	 * : Content markup.
	 * ---
	 * default: blocks
	 * options:
	 *   - blocks
	 *   - classic
	 * ---
	 *
	 * [--paragraphs=<min-max>]
	 * : Paragraphs per post, e.g. 3 or 2-7.
	 * ---
	 * default: 2-7
	 * ---
	 *
	 * [--days=<number>]
	 * : Spread publish dates over the last N days. 0 means now.
	 * ---
	 * default: 0
	 * ---
	 *
	 * [--porcelain]
	 * : Output only the new post IDs.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp persian-fake generate --count=10 --post_type=page --status=draft
	 *     $ wp persian-fake generate --count=50 --category=random --tags=3 --comments=5 --image=placeholder
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function generate( $args, $assoc_args ) {
		$paragraphs = explode( '-', Utils\get_flag_value( $assoc_args, 'paragraphs', '2-7' ) );
		$category   = Utils\get_flag_value( $assoc_args, 'category', 0 );

		$options = array(
			'count'          => Utils\get_flag_value( $assoc_args, 'count', 5 ),
			'post_type'      => Utils\get_flag_value( $assoc_args, 'post_type', 'post' ),
			'status'         => Utils\get_flag_value( $assoc_args, 'status', 'publish' ),
			'author'         => 0,
			'category'       => 'random' === $category ? Generator::RANDOM : (int) $category,
			'tags'           => Utils\get_flag_value( $assoc_args, 'tags', 0 ),
			'comments'       => Utils\get_flag_value( $assoc_args, 'comments', 0 ),
			'image'          => Utils\get_flag_value( $assoc_args, 'image', 'none' ),
			'format'         => Utils\get_flag_value( $assoc_args, 'format', 'blocks' ),
			'paragraphs_min' => (int) $paragraphs[0],
			'paragraphs_max' => (int) ( $paragraphs[1] ?? $paragraphs[0] ),
			'days'           => Utils\get_flag_value( $assoc_args, 'days', 0 ),
		);

		$author = Utils\get_flag_value( $assoc_args, 'author' );
		if ( $author ) {
			$user = is_numeric( $author ) ? get_user_by( 'id', $author ) : ( get_user_by( 'login', $author ) ? get_user_by( 'login', $author ) : get_user_by( 'email', $author ) );
			if ( ! $user ) {
				WP_CLI::error( "Unknown author: {$author}" );
			}
			$options['author'] = $user->ID;
		}

		$generator = new Generator();
		$normal    = $generator->normalize( $options );
		if ( is_wp_error( $normal ) ) {
			WP_CLI::error( $normal );
		}

		$porcelain = Utils\get_flag_value( $assoc_args, 'porcelain', false );
		$bar       = $porcelain ? null : Utils\make_progress_bar( 'Generating', $normal['count'] );

		$ids = $generator->generate(
			$normal,
			static function () use ( $bar ) {
				if ( $bar ) {
					$bar->tick();
				}
			}
		);

		if ( $bar ) {
			$bar->finish();
		}

		foreach ( $generator->warnings as $warning ) {
			WP_CLI::warning( $warning );
		}

		if ( $porcelain ) {
			WP_CLI::line( implode( ' ', $ids ) );
			return;
		}
		WP_CLI::success( sprintf( 'Created %d %s item(s).', count( $ids ), $normal['post_type'] ) );
	}

	/**
	 * List fake posts.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - ids
	 *   - count
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function list_( $args, $assoc_args ) {
		$ids    = ( new Cleanup() )->post_ids();
		$format = Utils\get_flag_value( $assoc_args, 'format', 'table' );

		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', $ids ) );
			return;
		}
		if ( 'count' === $format ) {
			WP_CLI::line( (string) count( $ids ) );
			return;
		}

		$rows = array_map(
			static function ( $id ) {
				return array(
					'ID'          => $id,
					'post_title'  => get_the_title( $id ),
					'post_type'   => get_post_type( $id ),
					'post_status' => get_post_status( $id ),
					'post_date'   => get_post_field( 'post_date', $id ),
				);
			},
			$ids
		);
		Utils\format_items( $format, $rows, array( 'ID', 'post_title', 'post_type', 'post_status', 'post_date' ) );
	}

	/**
	 * Delete all fake posts, their featured images, comments and fake tags.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * @param string[]             $args       Positional args.
	 * @param array<string,string> $assoc_args Options.
	 */
	public function purge( $args, $assoc_args ) {
		$cleanup = new Cleanup();
		$total   = count( $cleanup->post_ids() );
		if ( ! $total ) {
			WP_CLI::success( 'No fake content found.' );
			return;
		}

		WP_CLI::confirm( sprintf( 'Delete %d fake item(s) and their images?', $total ), $assoc_args );

		$bar     = Utils\make_progress_bar( 'Deleting', $total );
		$deleted = $cleanup->purge( array( $bar, 'tick' ) );
		$bar->finish();

		WP_CLI::success( sprintf( 'Deleted %d item(s).', $deleted ) );
	}
}
