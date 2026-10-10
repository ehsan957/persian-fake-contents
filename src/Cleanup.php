<?php
/**
 * Finding and removing fake content.
 *
 * @package PersianFakeContents
 */

namespace Grizzly\PersianFakeContents;

defined( 'ABSPATH' ) || exit;

/**
 * Lists and deletes everything marked as fake, including content made by 1.x.
 */
class Cleanup {

	/**
	 * IDs of fake posts (any type, any status).
	 *
	 * @param bool $include_attachments Also return fake featured images.
	 * @return int[]
	 */
	public function post_ids( $include_attachments = false ) {
		$types = array_keys( get_post_types() );
		if ( ! $include_attachments ) {
			$types = array_diff( $types, array( 'attachment' ) );
		}

		$query = new \WP_Query(
			array(
				'post_type'              => array_values( $types ),
				'post_status'            => array_keys( get_post_stati() ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin/CLI only.
				'meta_query'             => array(
					array(
						'key'   => Plugin::META_KEY,
						'value' => Plugin::META_VALUE,
					),
				),
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'nopaging'               => true,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		return array_map( 'intval', $query->posts );
	}

	/**
	 * Delete all fake posts, their images, comments and fake tags.
	 *
	 * @param callable|null $progress Called after each deleted post.
	 * @return int Number of deleted posts (not counting images).
	 */
	public function purge( $progress = null ) {
		$deleted = 0;

		foreach ( $this->post_ids( true ) as $id ) {
			if ( 'attachment' === get_post_type( $id ) ) {
				wp_delete_attachment( $id, true );
				continue;
			}
			if ( wp_delete_post( $id, true ) ) {
				++$deleted;
			}
			if ( $progress ) {
				call_user_func( $progress, $id );
			}
		}

		$this->delete_fake_terms();

		return $deleted;
	}

	/**
	 * Remove fake tags that no longer have posts.
	 */
	private function delete_fake_terms() {
		$terms = get_terms(
			array(
				'taxonomy'   => 'post_tag',
				'hide_empty' => false,
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin/CLI only.
				'meta_query' => array(
					array(
						'key'   => Plugin::META_KEY,
						'value' => Plugin::META_VALUE,
					),
				),
			)
		);

		if ( is_wp_error( $terms ) ) {
			return;
		}
		foreach ( $terms as $term_id ) {
			$term = get_term( $term_id, 'post_tag' );
			if ( $term && ! is_wp_error( $term ) && 0 === (int) $term->count ) {
				wp_delete_term( $term_id, 'post_tag' );
			}
		}
	}
}
