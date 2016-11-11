<?php
if( ! defined( 'ABSPATH' ) ) exit;

function ecsp_add_enqueued_scripts() {
	ecsp_enqueue_the_scripts();
}
add_action( 'admin_enqueue_scripts', 'ecsp_add_enqueued_scripts' );

function ecsp_enqueue_the_scripts() {
	wp_enqueue_style( 'ecsp', ECSP_URL . '/assets/ecsp.css', array(), ECSP_VERSION );
	wp_enqueue_script( 'ecsp', ECSP_URL . '/assets/ecsp.js', array( 'jquery' ), ECSP_VERSION );
}