<?php
if( ! defined( 'ABSPATH' ) ) exit;

function ecsp_get_share_message( $post_id, $character_limit = 512, $shortlink = true ) {
	$message = get_field( 'ecsp_share_message', $post_id, false );
	if ( !$message ) $message = get_the_excerpt($post_id); // get the specified excerpt. not always available, even if there is content.
	if ( !$message ) $message = ecsp_get_excerpt_of_content($post_id); // creates an excerpt from post content
	if ( !stripos( $message, '%post_url%' ) ) $message .= ($message ? ' ' : '') . '%post_url%'; // don't add the space to an empty string

	$url = $shortlink ? wp_get_shortlink( $post_id ) : get_permalink( $post_id );

	$url_length_diff = strlen($url) - strlen('%post_url%');

	// If the message plus full URL doesn't exceed character limit, simply return the message here.
	if ( (strlen($message) + $url_length_diff) <= $character_limit ) {
		return ecsp_replace_first_string( '%post_url%', $url, $message );
	}

	// We need to shorten the message so that $message plus $url_length_diff does not exceed the $character_limit.
	// However, we want to keep %post_url%. If that gets removed, we want to put the URL at the end instead.
	$m = substr( $message, 0, $character_limit - $url_length_diff  - 3 ); // Extra characters to add a "..."

	if ( stripos( $m, '%post_url%' ) ) {
		// if the last character is not a space, cut it off. it is likely to be a word that was not finished.
		$m = preg_replace( '/ [^ ]+$/', '', $m );

		// After trimming text, the post url is still in there. Put the URL in and return this value.
		return trim(ecsp_replace_first_string( '%post_url%', $url, $m )) . '...';
	}else{
		// %post_url% got cut off when we shortened the message. Let's remove the shortcode and just add it at the end instead.
		$m = ecsp_replace_first_string( '%post_url%', '', $message ); // remove shortcode

		$trim_length = $character_limit - strlen($url) - 3; // Three added for "..."
		$m = substr( $m, 0, $trim_length ); // trim the text enough to fit the URL.

		// if the last character is not a space, cut it off. it is likely to be a word that was not finished.
		$m = preg_replace( '/ [^ ]+$/', '', $m );

		return trim($m) . '... ' . $url;
	}
}

function ecsp_get_excerpt_of_content( $post_id, $words = 55 ) {
	$p = get_post($post_id);
	if ( !$p ) return '';

	$content = strip_tags($p->post_content);
	$content = wp_trim_words( $content, $words, '...');
	return $content;
}

function ecsp_replace_first_string( $search, $replace, $message ) {
	$pos = stripos($message, $search);

	if ($pos !== false) {
		$message = substr_replace($message, $replace, $pos, strlen($search));
	}

	return $message;
}


/**
 * Show error messages and hide regular fields when the field should not be editable.
 *
 * Scenario 1) When you are editing the post that does not belong to you (You can only share your own posts)
 * Scenario 2) The post has already been published
 *
 * @param $field
 * @return mixed
 */
function ecsp_disable_unhooked_sharing_fields( $field ) {
	global $post;

	// Get the ID of the post being edited.
	$post_id = empty($post) ? false : $post->ID;
	$post_id = apply_filters( 'ecsp_editing_post_id', $post_id );
	if ( !$post_id ) return $field;

	// Get the post object being edited, unless we are making a new post
	$p = false;
	if ( $post_id && $post_id !== 'new_post' ) {
		$p = get_post($post_id);
		if ( !$p ) return $field;
		if ( $p->post_type != 'post' ) return $field;
	}

	// Get the post author ID, which is the current user for a new post
	$post_author = false;
	if ( $post_id == "new_post" ) $post_author = get_current_user_id();
	else if ( $p ) $post_author = $p->post_author;
	if ( !$post_author ) return $field;

	if ( get_post_meta( $post_id, 'ecsp-published-once', true ) ) {
		// 1) Display Message: Post has already been published
		if ( $field['key'] == 'field_581c12c5fca54' ) {
			$field['conditional_logic'] = 0;
		}else{
			// HIDE all other fields
			$field['conditional_logic'] = array(
				array( 'field' => 'field_581c10adffd8b', 'operator' => '==', 'value' => '1' ),
				array( 'field' => 'field_581c10adffd8b', 'operator' => '!=', 'value' => '1' ),
			);
		}
	}elseif ( get_current_user_id() != $post_author && $post_id != 'new_post' ) {
		// 2) Show Message: Post is owned by another author
		if ( $field['key'] == 'field_581c10adffd8b' ) {
			// SHOW the "post author only" message
			$field['conditional_logic'] = 0;
		}else{
			// HIDE all other fields
			$field['conditional_logic'] = array(
				array( 'field' => 'field_581c10adffd8b', 'operator' => '==', 'value' => '1' ),
				array( 'field' => 'field_581c10adffd8b', 'operator' => '!=', 'value' => '1' ),
			);
		}
	}else{
		// 3) Configure social networks, hide network if no access token is given
		$facebook_token = get_user_meta( $post_author, 'ecsp-facebook-access-token', true );
		$twitter_token = get_user_meta( $post_author, 'ecsp-twitter-access-token', true );
		$linkedin_token = get_user_meta( $post_author, 'ecsp-linkedin-access-token', true );

		// Show message to configure access token
		if ( $field['key'] == 'field_581c40ba26aba' ) {
			// If a network is not yet connected, show the message.
			if ( !$facebook_token || !$twitter_token || !$linkedin_token ) {
				$field['conditional_logic'] = 0;

				// Get an array of unconnected networks
				$networks = array();
				if ( !$facebook_token ) $networks[] = 'Facebook';
				if ( !$twitter_token ) $networks[] = 'Twitter';
				if ( !$linkedin_token ) $networks[] = 'LinkedIn';

				// If some networks are connected, modify the message to explain specifically which networks are not connected.
				if ( count($networks) < 3 ) {
					// Create a string of unconnected networks e.g.: Twitter and LinkedIn
					$str = '';
					foreach( $networks as $i => $name ) {
						$str.= $name;
						if ( $i < count($networks) - 1 ) $str.= " and ";
					}

					// Replace the verbiage
					$search = 'Connect your social networking accounts';
					$replace = 'Connect your '. $str .' account';
					if ( count($networks) > 1 ) $replace .= 's'; // Make plural is needed
					$field['message'] = str_replace( $search, $replace, $field['message'] );
				}
			}
		}

		// Show the social media checkboxes, if the user has an access token.
		if ( $field['key'] == 'field_581c06a74bad8' ) {
			$valid_tokens = array_filter( array( $facebook_token, $twitter_token, $linkedin_token ) );

			// If less than two tokens are supplied, do not show the "Share on all" checkbox.
			if ( count($valid_tokens) < 2 ) {
				$field['conditional_logic'] = array(
					array( 'field' => 'field_581c10adffd8b', 'operator' => '==', 'value' => '1' ),
					array( 'field' => 'field_581c10adffd8b', 'operator' => '!=', 'value' => '1' ),
				);
			}
		}

		// Facebook checkbox
		if ( $field['key'] == 'field_581c06c64bad9' ) {
			$facebook_token = get_user_meta( $post_author, 'ecsp-facebook-access-token', true );

			// If less than two tokens are supplied, do not show the "Share on all" checkbox.
			if ( !$facebook_token ) {
				$field['conditional_logic'] = array(
					array( 'field' => 'field_581c10adffd8b', 'operator' => '==', 'value' => '1' ),
					array( 'field' => 'field_581c10adffd8b', 'operator' => '!=', 'value' => '1' ),
				);
			}
		}

		// Twitter checkbox
		if ( $field['key'] == 'field_581c06d94badb' ) {
			$twitter_token = get_user_meta( $post_author, 'ecsp-twitter-access-token', true );

			// If less than two tokens are supplied, do not show the "Share on all" checkbox.
			if ( !$twitter_token ) {
				$field['conditional_logic'] = array(
					array( 'field' => 'field_581c10adffd8b', 'operator' => '==', 'value' => '1' ),
					array( 'field' => 'field_581c10adffd8b', 'operator' => '!=', 'value' => '1' ),
				);
			}
		}

		// Linkedin checkbox
		if ( $field['key'] == 'field_581c06e24badc' ) {
			$linkedin_token = get_user_meta( $post_author, 'ecsp-linkedin-access-token', true );

			// If less than two tokens are supplied, do not show the "Share on all" checkbox.
			if ( !$linkedin_token ) {
				$field['conditional_logic'] = array(
					array( 'field' => 'field_581c10adffd8b', 'operator' => '==', 'value' => '1' ),
					array( 'field' => 'field_581c10adffd8b', 'operator' => '!=', 'value' => '1' ),
				);
			}
		}
	}

	return $field;
}
add_action( 'acf/load_field/key=field_581c10adffd8b', 'ecsp_disable_unhooked_sharing_fields', 20, 1 ); // Message for: Post Authors Only message
add_action( 'acf/load_field/key=field_581c12c5fca54', 'ecsp_disable_unhooked_sharing_fields', 20, 1 ); // Message for: Post already published
add_action( 'acf/load_field/key=field_581c40ba26aba', 'ecsp_disable_unhooked_sharing_fields', 20, 1 ); // Message for: Configure Sharing Accounts
add_action( 'acf/load_field/key=field_581c06a74bad8', 'ecsp_disable_unhooked_sharing_fields', 20, 1 ); // Share all
add_action( 'acf/load_field/key=field_581c06c64bad9', 'ecsp_disable_unhooked_sharing_fields', 20, 1 ); // Share Facebook
add_action( 'acf/load_field/key=field_581c06d94badb', 'ecsp_disable_unhooked_sharing_fields', 20, 1 ); // Share Twitter
add_action( 'acf/load_field/key=field_581c06e24badc', 'ecsp_disable_unhooked_sharing_fields', 20, 1 ); // Share Linkedin
add_action( 'acf/load_field/key=field_581c06ed4badd', 'ecsp_disable_unhooked_sharing_fields', 20, 1 ); // Sharing Message


function ecsp_add_linkedin_callback_url_to_field( $field ) {
	if ( !is_admin() ) return $field;
	if ( !function_exists('get_current_screen') ) return $field; // Not defined on some screens, like login pages.

	$screen = get_current_screen();
	if ( $screen->id != 'settings_page_acf-options-expert-city-authors' ) return $field;

	$field['instructions'] .= ' You must also list this callback URL in your app:<br><br><input type="text" value="' . esc_attr(ecsp_linkedin_get_callback_url()) . '" />';

	return $field;
}
add_action( 'acf/load_field/key=field_581a97979a205', 'ecsp_add_linkedin_callback_url_to_field', 20, 1 ); // Sharing Message


function ecsp_render_form_for_ajax() {
	$action = isset($_REQUEST['ecsp-ajax']) ? stripslashes($_REQUEST['ecsp-ajax']) : false;
	if ( $action !== 'update-acf-form' ) return;

	$facebook = !empty(get_user_meta( get_current_user_id(), 'ecsp-facebook-access-token', true ));
	$twitter = !empty(get_user_meta( get_current_user_id(), 'ecsp-twitter-access-token', true ));
	$linkedin = !empty(get_user_meta( get_current_user_id(), 'ecsp-linkedin-access-token', true ));

	@ob_clean();
	echo json_encode(array(
		'facebook' => $facebook ? 1 : 0,
		'twitter' => $twitter ? 1 : 0,
		'linkedin' => $linkedin ? 1 : 0
	));
	exit;
}
add_action( 'init', 'ecsp_render_form_for_ajax' );