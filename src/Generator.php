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
				'taxonomy'   => self::category_taxonomy( $o['post_type'] ),
				'hide_empty' => false,
				'fields'     => 'ids',
			)
		) : array();
		$categories     = is_wp_error( $categories ) ? array() : array_map( 'intval', $categories );
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
		$type       = $o['post_type'];
		$is_product = self::is_product( $type );
		$postarr    = array(
			'post_title'     => $this->text->title(),
			// WooCommerce's product screen uses the classic editor.
			'post_content'   => $this->text->content( wp_rand( $o['paragraphs_min'], $o['paragraphs_max'] ), $is_product ? 'classic' : $o['format'] ),
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

		$category_taxonomy = self::category_taxonomy( $type );
		$category_id       = 0;
		if ( is_object_in_taxonomy( $type, $category_taxonomy ) ) {
			if ( $o['category'] > 0 && 'category' === $category_taxonomy ) {
				$category_id = $o['category'];
			} elseif ( $categories ) {
				$category_id = $categories[ array_rand( $categories ) ];
			}
		}
		if ( $category_id && 'category' === $category_taxonomy ) {
			$postarr['post_category'] = array( $category_id );
		}

		$post_id = wp_insert_post( wp_slash( $postarr ), true );
		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		if ( $category_id && 'category' !== $category_taxonomy ) {
			wp_set_object_terms( $post_id, $category_id, $category_taxonomy );
		}

		$tag_taxonomy = $is_product ? 'product_tag' : 'post_tag';
		if ( $o['tags'] && is_object_in_taxonomy( $type, $tag_taxonomy ) ) {
			wp_set_object_terms( $post_id, $this->tag_ids( wp_rand( 1, $o['tags'] ), $tag_taxonomy ), $tag_taxonomy );
		}

		if ( $is_product ) {
			$this->setup_product( $post_id );
		}

		if ( $o['comments'] && post_type_supports( $type, 'comments' ) ) {
			$this->add_comments( $post_id, wp_rand( 0, $o['comments'] ), $postarr['post_date_gmt'] ?? null, $is_product );
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
	 * Whether the post type is a WooCommerce product we can fill with product data.
	 *
	 * @param string $type Post type.
	 * @return bool
	 */
	public static function is_product( $type ) {
		return 'product' === $type && function_exists( 'wc_get_product' );
	}

	/**
	 * Taxonomy used for the "Category" option of a post type.
	 *
	 * @param string $type Post type.
	 * @return string
	 */
	public static function category_taxonomy( $type ) {
		return self::is_product( $type ) ? 'product_cat' : 'category';
	}

	/**
	 * Give a WooCommerce product a price, SKU and stock. The excerpt is already its short description.
	 *
	 * @param int $post_id Product ID.
	 */
	private function setup_product( $post_id ) {
		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		// Round numbers in the range shops in Iran typically use (Toman).
		$price = wp_rand( 10, 500 ) * 10000;
		$product->set_regular_price( (string) $price );
		if ( wp_rand( 1, 100 ) <= 30 ) {
			$product->set_sale_price( (string) ( round( $price * wp_rand( 60, 90 ) / 100 / 1000 ) * 1000 ) );
		}
		$product->set_sku( 'PFC-' . $post_id );
		$product->set_manage_stock( true );
		$product->set_stock_quantity( wp_rand( 0, 100 ) );
		$product->save();
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
	 * @param int    $count    Number of tags.
	 * @param string $taxonomy Tag taxonomy.
	 * @return int[]
	 */
	private function tag_ids( $count, $taxonomy = 'post_tag' ) {
		$ids = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$name     = $this->text->phrase( 1, 2 );
			$existing = term_exists( $name, $taxonomy );
			if ( $existing ) {
				$ids[] = (int) $existing['term_id'];
				continue;
			}
			$term = wp_insert_term( $name, $taxonomy );
			if ( ! is_wp_error( $term ) ) {
				add_term_meta( $term['term_id'], Plugin::META_KEY, Plugin::META_VALUE, true );
				$ids[] = (int) $term['term_id'];
			}
		}
		return array_unique( $ids );
	}

	/**
	 * Add approved comments (or rated product reviews) from fake Persian names.
	 *
	 * @param int         $post_id   Post ID.
	 * @param int         $count     Number of comments.
	 * @param string|null $after_gmt Comments are dated after this GMT date, if given.
	 * @param bool        $reviews   Create WooCommerce reviews with star ratings.
	 */
	private function add_comments( $post_id, $count, $after_gmt, $reviews = false ) {
		$first = array( 'علی', 'زهرا', 'محمد', 'فاطمه', 'حسین', 'مریم', 'رضا', 'سارا', 'امیر', 'نگار', 'مهدی', 'الهام', 'سینا', 'پریسا', 'کاوه', 'شیرین' );
		$last  = array( 'احمدی', 'محمدی', 'حسینی', 'رضایی', 'کریمی', 'موسوی', 'جعفری', 'رحیمی', 'صادقی', 'نوری', 'کاظمی', 'هاشمی' );
		$start = $after_gmt ? strtotime( $after_gmt . ' UTC' ) : time();

		for ( $i = 0; $i < $count; $i++ ) {
			$timestamp  = wp_rand( $start, max( $start, time() ) );
			$comment_id = wp_insert_comment(
				array(
					'comment_type'         => $reviews ? 'review' : 'comment',
					'comment_post_ID'      => $post_id,
					'comment_author'       => $first[ array_rand( $first ) ] . ' ' . $last[ array_rand( $last ) ],
					'comment_author_email' => 'fake' . wp_rand( 1000, 9999 ) . '@example.com',
					'comment_content'      => $this->text->paragraph( 1, 3 ),
					'comment_approved'     => 1,
					'comment_date'         => wp_date( 'Y-m-d H:i:s', $timestamp ),
					'comment_date_gmt'     => gmdate( 'Y-m-d H:i:s', $timestamp ),
				)
			);
			if ( $reviews && $comment_id ) {
				// Mostly good ratings, like a real shop.
				add_comment_meta( $comment_id, 'rating', wp_rand( 1, 100 ) <= 70 ? wp_rand( 4, 5 ) : wp_rand( 1, 3 ) );
			}
		}

		if ( $reviews && $count && class_exists( 'WC_Comments' ) ) {
			\WC_Comments::clear_transients( $post_id );
		}
	}
}
