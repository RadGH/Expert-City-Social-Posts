<?php
if( ! defined( 'ABSPATH' ) ) exit;

/**
 * When a post is published for the first time (as far as this plugin is aware), trigger the "ecsp_publish_post_once" hook. This will trigger social media events.
 *
 * @param $post_id
 */
function ecsp_published_post( $post_id ) {
	$post = get_post($post_id);

	if ( !$post ) return;
	if ( $post->post_type != "post" ) return;
	if ( $post->post_status != "publish" ) return;

	if ( get_post_meta( $post_id, 'ecsp-published-once', true ) ) {
		return;
	}
	
	update_post_meta( $post_id, 'ecsp-published-once', 1 );

	$user_id = $post->post_author;

	do_action( 'ecsp_publish_post_once', $post_id, $user_id );
}
add_action( 'save_post', 'ecsp_published_post', 80, 2 );