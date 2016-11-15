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
				ecsp_display_user_error_log( $profileuser->ID );
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
				ecsp_display_user_error_log( $profileuser->ID );
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

function ecsp_display_user_error_log( $user_id ) {
	$log = get_user_meta( $user_id, 'ecsp_sharing_log', true );

	?>
	<tr class="ecsp-log">
		<th>Sharing Log
			<?php if ( $log ) { ?>
			<br><a href="<?php echo esc_attr(add_query_arg( array( 'ecsp-remove-log-item' => 'all' ) )); ?>" class="ecsp-remove-log-error">Clear all</a>
			<?php } ?>
		</th>
		<td>
			<?php
			if ( $log ) {
				$log = array_reverse($log);
				foreach( $log as $line ) {
					$time = date( 'Y-m-d H:i.s', $line['time'] );
					$time_ago = human_time_diff( $line['time'] );
					$post_title = get_the_title($line['post_id']);
					$edit_url = get_edit_post_link( $line['post_id'] );
					$network = ucwords($line['network']);
					$message = $line['message'];

					$id = isset($line['error_id']) ? $line['error_id'] : '-1';
					$remove_url = add_query_arg( array( 'ecsp-remove-log-item' => $id ) );

					?>
					<div class="ecsp-error-item">
						<p><strong><abbr title="<?php echo esc_attr($time); ?>"><?php echo $time_ago; ?> ago</abbr> &ndash; <?php echo $network; ?> error</strong> &ndash; <a href="<?php echo esc_attr($remove_url); ?>" class="ecsp-remove-log-error">Delete this log item</a></p>

						<p><strong><?php echo esc_html($post_title); ?> (<a href="<?php echo esc_attr($edit_url); ?>">Edit post</a>)</strong>:</p>

						<?php echo wpautop($message); ?>
					</div>
					<?php
				}
			}else{
				echo '<p class="description">No problems to report.</p>';
			}
			?>
		</td>
	</tr>
	<?php
}


function ecsp_delete_user_error_log_item() {
	$target_entry_id = isset($_REQUEST['ecsp-remove-log-item']) ? stripslashes($_REQUEST['ecsp-remove-log-item']) : false;
	if ( $target_entry_id === false ) return;

	if ( is_user_logged_in() ) {
		$log = get_user_meta( get_current_user_id(), 'ecsp_sharing_log', true );

		if ( $log && is_array($log) ) {
			if ( $target_entry_id == "all" ) {
				$log = array();
			}else{
				foreach( $log as $k => $entry ) {
					$entry_id = isset($entry['error_id']) ? $entry['error_id'] : '-1';

					if ( $target_entry_id == $entry_id ) unset($log[$k]);
				}

				// Reset array indexes
				$log = array_values($log);
			}
		}

		update_user_meta( get_current_user_id(), 'ecsp_sharing_log', $log );
	}

	wp_redirect( remove_query_arg( 'ecsp-remove-log-item' ) );
	exit;
}
add_action( 'template_redirect', 'ecsp_delete_user_error_log_item' );


// Record a log of warnings why a link wasn't shared, etc.
// Also records the timestamp of the latest log as a separate key.
function ecsp_log_sharing_error_for_user( $user_id, $post_id, $network, $message ) {
	$u = get_user_by('id', $user_id);
	if ( !$u || is_wp_error($u) ) return;

	$p = get_post( $post_id );
	if ( !$p || $p->post_type != 'post' ) return;

	// $time = current_time( 'Y-m-d H:i.s'); // 2016-11-04 05:15.03

	$entry = array(
		'error_id' => uniqid(),
		'time' => time(),
		'post_id' => $post_id,
		'network' => $network,
		'message' => $message
	);

	$user_log = get_user_meta( $user_id, 'ecsp_sharing_log', true );
	if ( !$user_log ) $user_log = array();

	$user_log[] = $entry;

	update_user_meta( $user_id, 'ecsp_sharing_log', $user_log );
	update_user_meta( $user_id, 'ecsp_sharing_last_log', time() );
}