<?php
/**
 * Fake content generator.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Creates fake posts (with optional terms, comments and featured images) from validated options.
 */
class Generator {

	/**
	 * Category value meaning "pick a random category for each post".
	 */
	const RANDOM = -1;

	/**
	 * Text builder.
	 *
	 * @var TextGenerator
	 */
	private $text;

	/**
	 * Image builder.
	 *
	 * @var ImageProvider
	 */
	private $images;

	/**
	 * Non-fatal problems from the last run (e.g. failed image downloads).
	 *
	 * @var string[]
	 */
	public $warnings = array();

	/**
	 * Constructor.
	 *
	 * @param TextGenerator|null $text   Text builder.
	 * @param ImageProvider|null $images Image builder.
	 */
	public function __construct( $text = null, $images = null ) {
		$this->text   = $text ? $text : new TextGenerator( new Dictionary() );
		$this->images = $images ? $images : new ImageProvider();
	}

	/**
	 * Default options.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'count'          => 5,
			'post_type'      => 'post',
			'status'         => 'publish',
			'author'         => 0,
			'category'       => 0,
			'tags'           => 0,
			'comments'       => 0,
			'image'          => 'none',
			'format'         => 'blocks',
			'paragraphs_min' => 2,
			'paragraphs_max' => 7,
			'days'           => 0,
		);
	}

	/**
	 * Statuses a fake post can have.
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			'publish' => __( 'Published', 'persian-fake-contents' ),
			'draft'   => __( 'Draft', 'persian-fake-contents' ),
			'pending' => __( 'Pending Review', 'persian-fake-contents' ),
			'private' => __( 'Private', 'persian-fake-contents' ),
		);
	}

	/**
	 * Post types fake content can be created for.
	 *
	 * @return array<string,string> Name => label.
	 */
	public static function post_types() {
		$types = array();
		foreach ( get_post_types( array( 'show_ui' => true ), 'objects' ) as $type ) {
			if ( in_array( $type->name, array( 'attachment', 'wp_block', 'wp_navigation' ), true ) ) {
				continue;
			}
			if ( $type->public || in_array( $type->name, array( 'post', 'page' ), true ) ) {
				$types[ $type->name ] = $type->labels->singular_name;
			}
		}
		return $types;
	}

	/**
	 * Validate and fill in options.
	 *
	 * @param array<string,mixed> $args Raw options.
	 * @return array<string,mixed>|WP_Error
	 */
	public function normalize( array $args ) {
		$o = wp_parse_args( $args, self::defaults() );

		foreach ( array( 'count', 'tags', 'comments', 'paragraphs_min', 'paragraphs_max', 'days' ) as $key ) {
			$o[ $key ] = absint( $o[ $key ] );
		}
		$o['author']   = absint( $o['author'] );
		$o['category'] = (int) $o['category'];

		if ( $o['count'] < 1 ) {
			return new WP_Error( 'gfpc_count', __( 'Count must be at least 1.', 'persian-fake-contents' ) );
		}
		if ( ! array_key_exists( $o['post_type'], self::post_types() ) ) {
			return new WP_Error( 'gfpc_post_type', __( 'Unknown post type.', 'persian-fake-contents' ) );
		}
		if ( ! array_key_exists( $o['status'], self::statuses() ) ) {
			return new WP_Error( 'gfpc_status', __( 'Unknown status.', 'persian-fake-contents' ) );
		}
		if ( $o['author'] && ! get_userdata( $o['author'] ) ) {
			return new WP_Error( 'gfpc_author', __( 'Unknown author.', 'persian-fake-contents' ) );
		}
		if ( $o['category'] > 0 && ! term_exists( $o['category'], 'category' ) ) {
			return new WP_Error( 'gfpc_category', __( 'Unknown category.', 'persian-fake-contents' ) );
		}
		if ( ! array_key_exists( $o['image'], ImageProvider::sources() ) ) {
			return new WP_Error( 'gfpc_image', __( 'Unknown image source.', 'persian-fake-contents' ) );
		}
		if ( ! in_array( $o['format'], array( 'blocks', 'classic' ), true ) ) {
			return new WP_Error( 'gfpc_format', __( 'Format must be "blocks" or "classic".', 'persian-fake-contents' ) );
		}

		$o['paragraphs_min'] = max( 1, min( 20, $o['paragraphs_min'] ) );
		$o['paragraphs_max'] = max( $o['paragraphs_min'], min( 20, $o['paragraphs_max'] ) );
		$o['tags']           = min( 10, $o['tags'] );
		$o['comments']       = min( 20, $o['comments'] );

		return $o;
	}

	/**
	 * Create fake posts.
	 *
	 * @param array<string,mixed> $args     Options (see defaults()).
	 * @param callable|null       $progress Called after each post with the new post ID.
	 * @return int[]|WP_Error Created post IDs.
	 */
	public function generate( array $args, $progress = null ) {
		$o = $this->normalize( $args );
		if ( is_wp_error( $o ) ) {
			return $o;
		}

		$this->warnings = array();
		$authors        = $o['author'] ? array( $o['author'] ) : $this->author_ids();
		$categories     = self::RANDOM === $o['category'] ? get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		) : array();
		$ids            = array();

		for ( $i = 0; $i < $o['count']; $i++ ) {
			$post_id = $this->create_post( $o, $authors, $categories );
			if ( is_wp_error( $post_id ) ) {
				$this->warnings[] = $post_id->get_error_message();
				continue;
			}
			$ids[] = $post_id;
			if ( $progress ) {
				call_user_func( $progress, $post_id );
			}
		}

		return $ids;
	}

	/**
	 * Create one post with its extras.
	 *
	 * @param array<string,mixed> $o          Normalized options.
	 * @param int[]               $authors    Candidate author IDs.
	 * @param int[]               $categories Candidate category IDs for random mode.
	 * @return int|WP_Error
	 */
	private function create_post( array $o, array $authors, array $categories ) {
		$type    = $o['post_type'];
		$postarr = array(
			'post_title'     => $this->text->title(),
			'post_content'   => $this->text->content( wp_rand( $o['paragraphs_min'], $o['paragraphs_max'] ), $o['format'] ),
			'post_type'      => $type,
			'post_status'    => $o['status'],
			'post_author'    => $authors ? $authors[ array_rand( $authors ) ] : get_current_user_id(),
			'comment_status' => 'open',
			'meta_input'     => array( Plugin::META_KEY => Plugin::META_VALUE ),
		);

		if ( post_type_supports( $type, 'excerpt' ) ) {
			$postarr['post_excerpt'] = $this->text->excerpt();
		}

		if ( $o['days'] ) {
			$timestamp                = time() - wp_rand( 0, $o['days'] * DAY_IN_SECONDS );
			$postarr['post_date']     = wp_date( 'Y-m-d H:i:s', $timestamp );
			$postarr['post_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp );
		}

		if ( is_object_in_taxonomy( $type, 'category' ) ) {
			if ( $o['category'] > 0 ) {
				$postarr['post_category'] = array( $o['category'] );
			} elseif ( $categories ) {
				$postarr['post_category'] = array( $categories[ array_rand( $categories ) ] );
			}
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( $o['tags'] && is_object_in_taxonomy( $type, 'post_tag' ) ) {
			wp_set_post_terms( $post_id, $this->tag_ids( wp_rand( 1, $o['tags'] ) ), 'post_tag' );
		}

		if ( $o['comments'] && post_type_supports( $type, 'comments' ) ) {
			$this->add_comments( $post_id, wp_rand( 0, $o['comments'] ), $postarr['post_date_gmt'] ?? null );
		}

		if ( 'none' !== $o['image'] && post_type_supports( $type, 'thumbnail' ) ) {
			$result = $this->images->attach( $post_id, $o['image'] );
			if ( is_wp_error( $result ) ) {
				$this->warnings[] = $result->get_error_message();
			}
		}

		return $post_id;
	}

	/**
	 * IDs of users who can write posts.
	 *
	 * @return int[]
	 */
	private function author_ids() {
		return array_map(
			'intval',
			get_users(
				array(
					'capability' => array( 'edit_posts' ),
					'fields'     => 'ID',
					'number'     => 50,
				)
			)
		);
	}

	/**
	 * Get or create tags named with random words. Newly created tags are marked as fake.
	 *
	 * @param int $count Number of tags.
	 * @return int[]
	 */
	private function tag_ids( $count ) {
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$name     = $this->text->phrase( 1, 2 );
			$existing = term_exists( $name, 'post_tag' );
			if ( $existing ) {
				$ids[] = (int) $existing['term_id'];
				continue;
			}
			$term = wp_insert_term( $name, 'post_tag' );
			if ( ! is_wp_error( $term ) ) {
				add_term_meta( $term['term_id'], Plugin::META_KEY, Plugin::META_VALUE, true );
				$ids[] = (int) $term['term_id'];
			}
		}
		return array_unique( $ids );
	}

	/**
	 * Add approved comments from fake Persian names.
	 *
	 * @param int         $post_id  Post ID.
	 * @param int         $count    Number of comments.
	 * @param string|null $after_gmt Comments are dated after this GMT date, if given.
	 */
	private function add_comments( $post_id, $count, $after_gmt ) {
		$first = array( 'علی', 'زهرا', 'محمد', 'فاطمه', 'حسین', 'مریم', 'رضا', 'سارا', 'امیر', 'نگار', 'مهدی', 'الهام', 'سینا', 'پریسا', 'کاوه', 'شیرین' );
		$last  = array( 'احمدی', 'محمدی', 'حسینی', 'رضایی', 'کریمی', 'موسوی', 'جعفری', 'رحیمی', 'صادقی', 'نوری', 'کاظمی', 'هاشمی' );
		$start = $after_gmt ? strtotime( $after_gmt . ' UTC' ) : time();

		for ( $i = 0; $i < $count; $i++ ) {
			$timestamp = wp_rand( $start, max( $start, time() ) );
			wp_insert_comment(
				array(
					'comment_post_ID'      => $post_id,
					'comment_author'       => $first[ array_rand( $first ) ] . ' ' . $last[ array_rand( $last ) ],
					'comment_author_email' => 'fake' . wp_rand( 1000, 9999 ) . '@example.com',
					'comment_content'      => $this->text->paragraph( 1, 3 ),
					'comment_approved'     => 1,
					'comment_date'         => wp_date( 'Y-m-d H:i:s', $timestamp ),
					'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $timestamp ),
				)
			);
		}
	}
}
