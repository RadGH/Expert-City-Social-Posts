<?php
if( !defined( 'ABSPATH' ) ) exit;

function ecsp_display_user_integration_fields( $profileuser ) {
	if ( is_numeric($profileuser) ) $profileuser = get_user_by( 'id', $profileuser );

	if ( $profileuser && $profileuser->ID == get_current_user_id() ) {
		// This is the current user. They should get an "Authenicate with _____" button.

		// Section heading on the backend user profile screen. Omitted if used as "Authorization page" in settings.
		if ( is_admin() ) { ?>
			<h2><?php _e( 'Social Post Integration' ); ?></h2>

			<p class="description">Authenticate with any of the following networks and whenever one of your posts is published, it will automatically be shared on your account.</p>
		<?php }else{ ?>
			<?php do_action( 'ecsp_notices' ); ?>
		<?php } ?>

		<table class="form-table">

			<?php
			do_action( 'ecsp_do_social_integration_fields', $profileuser );

			// Example markup:
			/*
			<tr class="profile-integration my-network-integration">
				<th>My Network</th>
				<td>
					<p><button type="button" class="button button-secondary">Authenticate with My Network</button></p>
					<p class="description">Descriptive text here if you want.</p>
				</td>
			</tr>
			*/

			if ( $profileuser ) {
				$log = get_post_meta( $profileuser->ID, 'ecsp_sharing_log', true );

				if ( $log ) {
					?>
					<tr class="ecsp-log">
						<th>Sharing Error Log</th>
						<td>
							<pre style="max-height: 300px; overflow: auto;" class="code"><?php echo esc_attr(print_r($log)); ?></pre>
						</td>
					</tr>
					<?php
				}
			}
			?>

		</table>
		<?php
	}else if ( current_user_can('edit_users') && is_admin() ) {
		// For admins, display a field to show the user's connected networks, or clear their authentication.
		// Only appears on the backend, as they should see their own profile on the front end anyway.
		?>
		<h2><?php _e( 'Social Post Integration' ); ?></h2>

		<p class="description">You can review this user's social network authorizations below, however, you cannot modify authorization for them.</p>

		<table class="form-table">

			<?php
			do_action( 'ecsp_do_social_integration_admin_preview', $profileuser );

			// Example markup to use:
			/*
			<tr class="profile-integration my-network-integration">
				<th>My Network</th>
				<td>
					<p><button type="button" class="button button-secondary">Authenticate with My Network</button></p>
					<p class="description">Descriptive text here if you want.</p>
				</td>
			</tr>
			*/

			if ( $profileuser ) {
				$log = get_post_meta( $profileuser->ID, 'ecsp_sharing_log', true );

				if ( $log ) {
					?>
					<tr class="ecsp-log">
						<th>Sharing Error Log</th>
						<td>
							<pre style="max-height: 300px; overflow: auto;" class="code"><?php echo esc_attr(print_r($log)); ?></pre>
						</td>
					</tr>
					<?php
				}
			}
			?>

		</table>
		<?php
	}
}
add_action( 'show_user_profile', 'ecsp_display_user_integration_fields', 30 );
add_action( 'edit_user_profile', 'ecsp_display_user_integration_fields', 30 );

// Display ECSP notices in the admin. For custom integrations of ECSP, you can call the action "ecsp_notices" yourself.
function ecsp_display_notices_in_admin() {
	do_action( 'ecsp_notices' );
}
add_action( 'admin_notices', 'ecsp_display_notices_in_admin' );


// Record a log of warnings why a link wasn't shared, etc.
// Also records the timestamp of the latest log as a separate key.
function ecsp_log_sharing_error_for_user( $user_id, $post_id, $network, $message ) {
	$u = get_user_by('id', $user_id);
	if ( !$u || is_wp_error($u) ) return;

	$p = get_post( $post_id );
	if ( !$p || $p->post_type != 'post' ) return;

	// $time = current_time( 'Y-m-d H:i.s'); // 2016-11-04 05:15.03

	$entry = array(
		'time' => time(),
		'post_id' => $post_id,
		'network' => $network,
		'message' => $message
	);

	$user_log = get_post_meta( $user_id, 'ecsp_sharing_log', true );
	if ( !$user_log ) $user_log = array();

	$user_log[] = $entry;

	update_user_meta( $user_id, 'ecsp_sharing_log', $user_log );
	update_user_meta( $user_id, 'ecsp_sharing_last_log', time() );
}