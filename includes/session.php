<?php
if( !defined( 'ABSPATH' ) ) exit;

// Start a new session, only if we do not already have a php session id.
function ecsp_start_session() {
	if ( !session_id() ) session_start();
}
add_action( 'init', 'ecsp_start_session', 1 );

// End a session on login or logout. Session data shouldn't carry over between users.
function ecsp_end_session() {
	session_destroy();
}
add_action( 'wp_logout', 'ecsp_end_session' );
add_action( 'wp_login', 'ecsp_end_session' );