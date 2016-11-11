<?php
if( ! defined( 'ABSPATH' ) ) exit;

function ecsp_register_settings_menu() {
	if ( !defined('ECA_VERSION') ) {
		// This options page is only added when Expert City Authors is not running.
		acf_add_options_sub_page(array(
			'parent_slug' => 'options-general.php',
			'page_title' => 'Expert City Social Posts',
			'menu_title' => 'Expert City Social Posts',
			'autoload' => false,
		));
	}
}
add_action( 'admin_menu', 'ecsp_register_settings_menu' );


function ecsp_after_install_setup_notice() {
	if ( !is_admin() ) return;
	if ( get_option('ecsp_installed') ) return; // This option is autoloaded and won't slow down every admin menu request

	if ( get_field('ecsp_facebook', 'options') != null ) { // User has entered a value, remember that to not ask again
		update_option('ecsp_installed', '1', true);
		return;
	}

	$screen = get_current_screen();
	if ( $screen->id == "settings_page_acf-options-expert-city-authors" ) return;
	if ( $screen->id == "settings_page_acf-options-expert-city-social-posts" ) return;

	$settings_url = admin_url('options-general.php?page=acf-options-expert-city-social-posts');

	// If Expert City Authors is running, the settings will appear at the bottom of that settings page instead.
	if ( defined('ECA_VERSION') ) $settings_url = admin_url('options-general.php?page=acf-options-expert-city-authors');

	?>
	<div class="notice notice-info">
		<p><strong>Expert City Social Posts: Install Successful</strong></p>
		<p>To get started with Expert City Social Posts, head over to the <a href="<?php echo esc_attr($settings_url); ?>">settings page</a>.</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'ecsp_after_install_setup_notice' );