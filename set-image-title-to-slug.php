<?php
/**
 * Set Image Title to Slug
 *
 * @package set-image-title-to-slug
 * @author Andy Fragen <andy@thefragens.com>
 * @license MIT
 * @link https://github.com/afragen/set-image-title-to-slug
 */

/**
 * Plugin Name: Set Image Title to Slug
 * Description: Automatically sets the Media Library image slug to the the image title. This creates a new image URL based upon new image title.
 * Author: Andy Fragen
 * License: MIT
 * Version: 0.0.1
 * Requires at least: 4.6
 * Requires PHP: 5.6
 * GitHub Plugin URI: https://github.com/afragen/set-image-title-to-slug
 */

namespace Fragen\Image_Title_To_Slug;

/**
 * Change image slug to title.
 *
 * @return void
 */
function set_image_title_to_slug() {
	$args         = [
		'post_type'      => 'attachment',
		'post_mime_type' => 'image',
		'post_status'    => 'inherit',
		'posts_per_page' => 5,
	];
	$query_images = new \WP_Query( $args );
	foreach ( $query_images->posts as $post ) {
		$new_slug = sanitize_title( $post->post_title );
		if ( $post->post_name !== $new_slug ) {
			wp_update_post(
				[
					'ID'        => $post->ID,
					'post_name' => $new_slug,
				]
			);
		}
	}
}

set_image_title_to_slug();
