<?php
if( ! defined( 'ABSPATH' ) ) exit;

function ecsp_show_fields_on_authorization_page( $content ) {
	$post_id = get_field( 'ecsp_authorization_page', 'options', false );
	if ( !$post_id ) return $content;
	if ( ((int) get_the_ID() !== (int) $post_id) ) return $content;

	$user_id = get_current_user_id();
	if ( !$user_id ) return $content . "\n<p>You must be logged in to authorize social networks.</p>";

	ob_start();

	do_action( 'ecsp_before_authorization_page', $post_id, $user_id );

	echo '<div class="ecsp-authorization-page">';
		ecsp_display_user_integration_fields( $user_id );
	echo '</div>';
	
	do_action( 'ecsp_after_authorization_page', $post_id, $user_id );
	
	return $content . ob_get_clean();
}
add_filter( 'the_content', 'ecsp_show_fields_on_authorization_page', 25 );


function ecsp_change_profile_link_to_authorization_page( $field ) {
	if ( is_admin() ) return $field;

	$post_id = get_field( 'ecsp_authorization_page', 'options', false );
	if ( !$post_id ) return $field;

	// Replace profile url with permalink
	$field['message'] = str_replace( '/wp-admin/profile.php', esc_attr(get_permalink($post_id)), $field['message'] );

	return $field;
}
add_action( 'acf/load_field/key=field_581c40ba26aba', 'ecsp_change_profile_link_to_authorization_page', 25, 1 ); // Message for: Configure Sharing Accounts