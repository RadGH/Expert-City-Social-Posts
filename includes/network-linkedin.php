<?php
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Returns a LinkedIn API object on success. Intended for anonymous calls. For authorized api use, try ecsp_get_linkedin_api.
 */
function ecsp_get_linkedin_api() {
	static $li = null;

	// Create a new linkedin object if it doesn't already exist, or if the oAuth Token or secret is provided.
	if ( $li === null ) {
		$creds = get_field( 'ecsp_linkedin', 'options' );

		if ( empty($creds) || empty($creds[0]['app_id']) || empty($creds[0]['secret']) ) {
			$li = false;
			return $li;
		}

		if ( !class_exists( 'LinkedIn\LinkedIn' ) ) {
			require_once ECSP_PATH . '/assets/linkedin/linkedin.php';
		}

		$li = new LinkedIn\LinkedIn(array(
			'api_key' => apply_filters( 'ecsp_linkedin_app_id', $creds[0]['app_id'] ),
			'api_secret' => apply_filters( 'ecsp_linkedin_app_secret', $creds[0]['secret'] ),
			'callback_url' => ecsp_linkedin_get_callback_url()
		));
	}

	return $li;
}

/**
 * Returns the callback url for the LinkedIn API, which has to be registered within the app. This cannot use a nonce.
 */
function ecsp_linkedin_get_callback_url() {
	return add_query_arg( array( 'ecsp' => 'linkedin' ), trailingslashit(site_url()) );
}

function ecsp_linkedin_oauth_cb() {
	$nonce = isset($_REQUEST['ecsp']) ? stripslashes($_REQUEST['ecsp']) : false;
	if ( !$nonce ) return;

	if ( wp_verify_nonce( $nonce, 'linkedin-deauthorize' ) ) {
		$access_token = get_user_meta( get_current_user_id(), 'ecsp-linkedin-access-token', true );

		$redirect = remove_query_arg( array('ecsp', 'oauth_token', 'oauth_verifier' ) );

		// LinkedIn does not let you deauthorize an app that uses oauth tokens, so just delete it.
		if ( $access_token ) {
			delete_user_meta( get_current_user_id(), 'ecsp-linkedin-access-token' );
			$redirect = add_query_arg( array( 'ecsp_message' => 'linkedin_disconnected' ), $redirect );
		}

		wp_redirect( $redirect );
		exit;
	}

	// Note: The linkedin login callback must be predefined and cannot use a nonce, so in this case it is a direct usage.
	if ( $nonce == 'linkedin' ) {
		$code = isset($_REQUEST['code']) ? stripslashes($_REQUEST['code']) : false;
		if ( !$code ) return;

		$li = ecsp_get_linkedin_api();
		$result = array(
			'_preliminary_code' => $code,
		);

		try {
			$result['access_token'] = $li->getAccessToken( $code );
			$result['expiration'] = time() + $li->getAccessTokenExpiration();
		} catch( Exception $e ) {
			$message = $e->getMessage();
			$message.= "\n\n";
			$message.= "code: <code>". esc_html( print_r( $code, true ) ) ."</code>\n\n";
			$message.= "li: <code>". esc_html( print_r( $li, true ) ) ."</code>\n\n";

			wp_die( wpautop($message), 'Error: ' . $e->getCode(), $e );
			exit;
		}

		if ( $result ) {
			update_user_meta( get_current_user_id(), 'ecsp-linkedin-access-token', $result ); // oauth_token, oauth_token_secret, user_id, screen_name, x_auth_expires
			delete_user_meta( get_current_user_id(), 'ecsp-linkedin-expired' );

			$redirect_url = site_url();
			if ( $v = get_field('ecsp_authorization_page', 'options', false) ) $redirect_url = get_permalink($v);

			$redirect = add_query_arg( array( 'ecsp_message' => 'linkedin_connected' ), $redirect_url );
			wp_redirect( $redirect );
			exit;
		}else{
			echo 'LinkedIn oAuth failed for retrieving long lived access token.';
			exit;
		}
	}

}
add_action( 'admin_init', 'ecsp_linkedin_oauth_cb' );
add_action( 'template_redirect', 'ecsp_linkedin_oauth_cb' );

function ecsp_linkedin_message_connected() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'linkedin_connected' ) {
		$access_token = get_user_meta( get_current_user_id(), 'ecsp-linkedin-access-token', true );

		if ( $access_token ) {
			?>
			<div class="updated eca-notice eca-success">
				<p>Your account is now authorized with LinkedIn. When writing an article, you will have the option to automatically post the article on LinkedIn when it gets published.</p>
			</div>
			<?php
		}
	}
}
add_action( 'ecsp_notices', 'ecsp_linkedin_message_connected' );

function ecsp_linkedin_message_disconnected() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'linkedin_disconnected' ) {
		?>
		<div class="updated eca-notice eca-success">
			<p>Your authorization with LinkedIn has been removed. Your articles will no longer be posted to LinkedIn when they are published.</p>
		</div>
		<?php
	}
}
add_action( 'ecsp_notices', 'ecsp_linkedin_message_disconnected' );

function ecsp_linkedin_message_reauthorized() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'linkedin_reauthorized' ) {
		?>
		<div class="updated eca-notice eca-success">
			<p>Your authorization with LinkedIn has been renewed.</p>
		</div>
		<?php
	}
}
add_action( 'ecsp_notices', 'ecsp_linkedin_message_reauthorized' );

function ecsp_check_linkedin_expiration() {
	if ( !is_user_logged_in() ) return;

	$access_token = get_user_meta( get_current_user_id(), 'ecsp-linkedin-access-token', true );
	if ( !$access_token ) return;

	if ( $access_token['expiration'] < time() ) {
		delete_user_meta( get_current_user_id(), 'ecsp-linkedin-access-token' );
		update_user_meta( get_current_user_id(), 'ecsp-linkedin-expired', 1);
	}
}
add_action( 'init', 'ecsp_check_linkedin_expiration', 9 );

function ecsp_display_linkedin_expired_notice() {
	if ( !is_user_logged_in() ) return;

	if ( get_user_meta( get_current_user_id(), 'ecsp-linkedin-expired', true ) ) {
		?>
		<div class="updated eca-notice eca-error">
			<p>Your authorization with LinkedIn has expired. Please connect with LinkedIn again.</p>
		</div>
		<?php
	}
}
add_action( 'ecsp_notices', 'ecsp_display_linkedin_expired_notice' );

function ecsp_display_linkedin_integration_button( $user ) {
	$li = ecsp_get_linkedin_api();
	if ( !$li ) return;

	$access_token = get_user_meta( $user->ID, 'ecsp-linkedin-access-token', true );
	?>
	<tr class="profile-integration linkedin-integration" id="ecsp-linkedin">
		<th>LinkedIn</th>
		<td>
			<?php
			if ( $access_token ) {
				// LinkedIn does not provide a URL to logout or invalidate a token, so we'll use the callback url to do that instead.
				$nonce = wp_create_nonce( 'linkedin-deauthorize' );
				$removeUrl = site_url( add_query_arg( array( 'ecsp' => $nonce ), $_SERVER["REQUEST_URI"] ) );
				?>
				<p><a href="<?php echo esc_attr($removeUrl); ?>" class="social-button social-button-disconnect">Remove LinkedIn Authorization</a> <small><em>&ndash; Expires in <?php echo human_time_diff( $access_token['expiration'] ); ?></em></small></p>
				<?php
			}else{
				$loginUrl = $li->getLoginUrl(array(
					LinkedIn\LinkedIn::SCOPE_WRITE_SHARE
				));
				?>
				<p><a href="<?php echo esc_attr($loginUrl); ?>" type="button" class="social-button social-button-linkedin">Connect with LinkedIn</a></p>
				<?php
			}
			?>
		</td>
	</tr>
	<?php
}
add_action( 'ecsp_do_social_integration_fields', 'ecsp_display_linkedin_integration_button', 10 );

function ecsp_display_linkedin_integration_admin_preview( $user ) {
	$access_token = get_user_meta( $user->ID, 'ecsp-linkedin-access-token', true );
	?>
	<tr class="profile-integration linkedin-integration profile-admin-preview" id="ecsp-linkedin">
		<th>LinkedIn</th>
		<td>
			<?php
			if ( $access_token ) {
				?>
				<p><span class="dashicons dashicons-yes"></span> This user's account is connected to LinkedIn.</p>
				<?php
			}else{
				?>
				<p><em>Not connected to LinkedIn.</em></p>
				<?php
			}
			?>
		</td>
	</tr>
	<?php
}
add_action( 'ecsp_do_social_integration_admin_preview', 'ecsp_display_linkedin_integration_admin_preview', 5 );

function ecsp_linkedin_publish_post( $post_id, $user_id ) {
	// get_field doesn't work here for non-admins, for some reason.
	$share_linkedin = get_post_meta($post_id, 'ecsp_share_linkedin', true );
	$share_all = get_post_meta($post_id, 'ecsp_share_all', true );

	// The user must have selected to share on LinkedIn, or share on All
	if ( !$share_linkedin && !$share_all ) {
		return;
	}

	// If the user does not have this account connected, abort. Might be due to "all" being checked.
	if ( get_user_meta( $user_id, 'ecsp-linkedin-access-token', true ) ) return;

	$li = ecsp_get_linkedin_api();
	if ( !$li ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'linkedin', 'Could not initialize the LinkedIn API' );
		return;
	}

	$access_token = get_user_meta( $user_id, 'ecsp-linkedin-access-token', true );
	if ( !$access_token ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'linkedin', 'Access token not set, or expired. Please connect with LinkedIn again.' );
		return;
	}

	$li->setAccessToken( $access_token['access_token'] );

	// Get the user's message. This will include a link to the article.
	$user_message = ecsp_get_share_message( $post_id, 5000, false );
	$message_excerpt = ecsp_get_share_message( $post_id, 250, false ); // For content description, which has a max length of 255.

	if ( !$user_message ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'linkedin', 'The sharing message was not provided.' );
		return;
	}

	try {
		$post = array(
			'comment' => $user_message,
			'content' => array(
				'title' => get_the_title( $post_id ),
				'description' => $message_excerpt, //Maxlen(255)
				'submitted_url' => get_permalink($post_id)
			),
			'visibility' => array(
				'code' => 'anyone'
			)
		);

		$result = $li->post('people/~/shares', $post);
	} catch( Exception $e ) {
		update_post_meta( $post_id, 'ecsp-linkedin-error', 'LinkedIn returned an error: ' . $e->getMessage() );
		delete_post_meta( $post_id, 'ecsp-linkedin-share-result' );
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'linkedin', 'LinkedIn returned an error: ' . $e->getMessage() );
		return;
	}

	update_post_meta( $post_id, 'ecsp-linkedin-share-result', $result );
	delete_post_meta( $post_id, 'ecsp-linkedin-error' );
}
add_action( 'ecsp_publish_post_once', 'ecsp_linkedin_publish_post', 10, 2 );