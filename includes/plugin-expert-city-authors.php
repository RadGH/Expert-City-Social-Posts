<?php
if( !defined( 'ABSPATH' ) ) exit;

// When ECA is installed, always enqueue scripts on front-end for logged in users.
function ecsp_enqueue_scripts_for_eca() {
	if ( is_user_logged_in() ) {
		ecsp_enqueue_the_scripts();
	}
}
add_action( 'wp_enqueue_scripts', 'ecsp_enqueue_scripts_for_eca' );

// Add the social sharing field groups to the ACF form
function ecsp_add_field_group_to_eca_form( $args ) {
	add_filter( 'ecsp_editing_post_id', 'ecsp_use_eca_post_id' );
	$args['field_groups'][] = 'group_581c069089f23';

	return $args;
}
add_filter( 'eca-acf-form-args', 'ecsp_add_field_group_to_eca_form' );

function ecsp_use_eca_post_id( $post_id ) {
	return 'new_post';
}

// Add a button to write post on ECA page
function ecsp_add_submit_article_button_to_authorization_page() {
	$post_id = get_field( 'eca_submit_article_page', 'options', false );
	if ( !$post_id ) return;

	?>
	<p>When you're finished, please close this window and return to the Submit and Article Page.</p>
	<?php
}
add_action( 'ecsp_after_authorization_page', 'ecsp_add_submit_article_button_to_authorization_page' );


// Use ECA ids for autofill on front end
function ecsp_use_eca_autofill_ids() {
	if ( is_admin() ) return;

	?>
	<script type="text/javascript">
		window.ecsp_tmce_active_selector = '.acf-field.acf-field--post-content .acf-editor-wrap';
		window.ecsp_text_selector = '.acf-field.acf-field--post-content .wp-editor-area';
	</script>
	<?php
}
add_action( 'wp_print_footer_scripts', 'ecsp_use_eca_autofill_ids' );