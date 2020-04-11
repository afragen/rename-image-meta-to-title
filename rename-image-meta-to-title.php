<?php
/**
 * Rename Image Meta to Title
 *
 * @package rename-image-meta-to-title
 * @author Andy Fragen <andy@thefragens.com>
 * @license MIT
 * @link https://github.com/afragen/rename-image-meta-to-title
 */

/**
 * Plugin Name: Rename Image Meta to Title
 * Description: Automatically sets the Media Library image slug, file, and URL to the the image title on save.
 * Author: Andy Fragen
 * License: MIT
 * Version: 0.1.0
 * Domain Path: /languages
 * Text Domain: rename-image-meta-to-title
 * Requires at least: 4.8
 * Requires PHP: 5.6
 * GitHub Plugin URI: https://github.com/afragen/rename-image-meta-to-title
 */

namespace Fragen\Rename_Image_Meta_To_Title;

add_action( 'init', [ new Rename_Image_Meta_To_Title(), 'load_hooks' ] );

/**
 * Class Rename_Image_Meta_To_Title
 */
class Rename_Image_Meta_To_Title {
	/**
	 * Load hooks.
	 */
	public function load_hooks() {
		add_filter( 'wp_insert_attachment_data', [ $this, 'change_post_slug' ], 50, 1 );
		add_filter( 'attachment_fields_to_save', [ $this, 'rename_media_files_attachment_fields_on_save' ], 11, 1 );
	}

	/**
	 * Change post slug.
	 *
	 * @param \WP_Post $post Current post.
	 *
	 * @return \WP_Post
	 */
	public function change_post_slug( $post ) {
		if ( $post['post_name'] !== $post['post_title'] ) {
			$post['post_name'] = sanitize_title( $post['post_title'] );
		}

		return $post;
	}

	/**
	 * Rename image files and URL from post title.
	 *
	 * @param \WP_Post $post Current post.
	 *
	 * @return \WP_Post
	 */
	public function rename_media_files_attachment_fields_on_save( $post ) {
		if ( 'attachment' === $post['post_type'] && 'editpost' === $post['action'] ) {
			// Proceed only if slug has changed.
			if ( $post['post_name'] !== $post['post_title'] ) {
				/* Get original filename */
				$orig_file     = get_attached_file( $post['ID'] );
				$orig_filename = basename( $orig_file );

				/* Get original path of file */
				$orig_dir_path = substr( $orig_file, 0, ( strrpos( $orig_file, '/' ) ) );

				/* Get image sizes */
				$image_sizes = array_merge( get_intermediate_image_sizes(), [ 'full' ] );

				/* If image, get URLs to original sizes */
				if ( wp_attachment_is_image( $post['ID'] ) ) {
					$orig_image_urls = [];

					foreach ( $image_sizes as $image_size ) {
						$orig_image_data                = wp_get_attachment_image_src( $post['ID'], $image_size );
						$orig_image_urls[ $image_size ] = $orig_image_data[0];
					}
					/* Otherwise, get URL to original file */
				} else {
					$orig_attachment_url = wp_get_attachment_url( $post['ID'] );
				}

				$new_slug     = sanitize_title( $post['post_title'] );
				$extension    = pathinfo( $orig_filename, PATHINFO_EXTENSION );
				$new_filename = "{$new_slug}.{$extension}";
				$new_file     = "{$orig_dir_path}/{$new_filename}";

				$orig_metadata = wp_create_image_subsizes( $orig_file, $post['ID'] );
				if ( isset( $orig_metadata['sizes'] ) ) {
					foreach ( $orig_metadata['sizes'] as $size ) {
						unlink( "{$orig_dir_path}/{$size['file']}" );
					}
				}

				rename( $orig_file, $new_file );

				/* Update file location in database */
				update_attached_file( $post['ID'], $new_file );

				/* Update guid for attachment */
				$post_for_guid = get_post( $post['ID'] );
				$guid          = str_replace( $orig_filename, $new_filename, $post_for_guid->guid );

				wp_update_post(
					[
						'ID'   => $post['ID'],
						'guid' => $guid,
					]
				);

				/* Update attachment's metadata */
				wp_update_attachment_metadata( $post['ID'], wp_generate_attachment_metadata( $post['ID'], $new_file ) );

				/* Load global so that we can save to the database */
				global $wpdb;

				/* If image, get URLs to new sizes and update posts with old URLs */
				if ( wp_attachment_is_image( $post['ID'] ) ) {
					foreach ( $image_sizes as $image_size ) {
						$orig_image_url = $orig_image_urls[ $image_size ];
						$new_image_data = wp_get_attachment_image_src( $post['ID'], $image_size );
						$new_image_url  = $new_image_data[0];

						// phpcs:ignore WordPress.DB.DirectDatabaseQuery
						$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, %s, %s);", $orig_image_url, $new_image_url ) );
					}
					/* Otherwise, get URL to new file and update posts with old URL */
				} else {
					$new_attachment_url = wp_get_attachment_url( $post['ID'] );

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery
					$wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET post_content = REPLACE(post_content, %s, %s);", $orig_attachment_url, $new_attachment_url ) );
				}
			}
		}

		return $post;
	}
}
