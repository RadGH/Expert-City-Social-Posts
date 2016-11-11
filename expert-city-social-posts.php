<?php
/*
Plugin Name: Expert City Social Posts
Version:     1.1.2
Plugin URI:  http://radgh.com/
Description: Adds the ability for authors to tie their accounts to 
Author:      Radley Sustaire &lt;radley@radgh.com&gt;
Author URI:  mailto:radleygh@gmail.com
License:     Copyright (c) 2016 Jamie Stephens, All Rights Reserved.
*/

if( !defined( 'ABSPATH' ) ) exit;

define( 'ECSP_URL', untrailingslashit(plugin_dir_url( __FILE__ )) );
define( 'ECSP_PATH', dirname(__FILE__) );
define( 'ECSP_VERSION', '1.1.2' );

function ecsp_init_plugin() {
	if ( !class_exists( 'acf' ) ) {
		add_action( 'admin_notices', 'ecsp_acf_not_running' );
		return;
	}
	
	include_once( ECSP_PATH . '/includes/usermeta.php' ); // Handles custom user metadata.
	include_once( ECSP_PATH . '/includes/session.php' ); // Start/stop a PHP session when users log in and out. Necessary for Facebook SDK.
	include_once( ECSP_PATH . '/includes/enqueue.php' ); // Enqueue CSS and JS for the plugin.
	include_once( ECSP_PATH . '/includes/options.php' ); // Options page to set up API settings.
	include_once( ECSP_PATH . '/includes/post.php' ); // Post publishing handlers.
	include_once( ECSP_PATH . '/includes/fields.php' ); // Customization for ACF fields.
	include_once( ECSP_PATH . '/includes/page.php' ); // Displays authorization fields on a custom page

	// Fields
	include_once( ECSP_PATH . '/fields/article-sharing.php' );
	include_once( ECSP_PATH . '/fields/social-posts-integration.php' );

	// Facebook integration relies on the mbstring PHP extension
	if ( function_exists('mb_strstr') ) {
		include_once( ECSP_PATH . '/includes/network-facebook.php' );
	}else{
		add_action( 'admin_notices', 'ecsp_mbstring_not_installed' );
	}

	// Expert City Authors integration
	if ( defined('ECA_VERSION') ) {
		include_once( ECSP_PATH . '/includes/plugin-expert-city-authors.php' );
	}
}
add_action( 'plugins_loaded', 'ecsp_init_plugin', 12 );

function ecsp_acf_not_running() {
	// If Expert City Authors is running, it will already be nagging to the user.
	if ( !defined('ECA_VERSION') ) {
		?>
		<div class="error">
			<p><strong>Expert City Social Posts: Error</strong></p>
			<p>The required plugin <strong>Advanced Custom Fields Pro</strong> is not running. Please activate this required plugin, or disable Expert City Social Posts.</p>
		</div>
		<?php
	}
}

function ecsp_activate_plugin() {
	flush_rewrite_rules();
}
function ecsp_deactivate_plugin() {
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ecsp_activate_plugin' );
register_deactivation_hook( __FILE__, 'ecsp_deactivate_plugin' );

function ecsp_mbstring_not_installed() {
	?>
	<div class="notices">
		<p><strong>Expert City Social Posts: Notice</strong></p>
		<p>The PHP extension <a href="http://php.net/manual/en/book.mbstring.php" target="_blank">mbstring</a> is not installed on your server. This extension is required for Facebook integration to function. Ask your host to install this extension for you, otherwise you will not have the option to post to Facebook.</p>
	</div>
	<?php
}