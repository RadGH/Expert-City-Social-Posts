<?php
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Returns the Facebook SDK object on success. If the AppID or Secret is not set in the settings, returns FALSE.
 *
 * @return bool|\Facebook\Facebook|null
 */
function ecsp_get_facebook_api() {
	static $fb = null;

	if ( $fb === null ) {
		$creds = get_field( 'ecsp_facebook', 'options' );

		if ( empty($creds) || empty($creds[0]['app_id']) || empty($creds[0]['secret']) ) {
			$fb = false;
			return $fb;
		}

		if ( !class_exists( 'Facebook/Facebook' ) ) {
			require_once ECSP_PATH . '/assets/facebook/autoload.php';
		}

		$fb = new Facebook\Facebook(array(
			'app_id' => apply_filters( 'ecsp_facebook_app_id', $creds[0]['app_id'] ),
			'app_secret' => apply_filters( 'ecsp_facebook_app_secret', $creds[0]['secret'] ),
			'default_graph_version' => 'v2.5',
		));
	}

	return $fb;
}

function ecsp_facebook_oauth_cb() {
	$nonce = isset($_REQUEST['ecsp']) ? stripslashes($_REQUEST['ecsp']) : false;
	if ( !$nonce ) return;

	$fb = ecsp_get_facebook_api();
	if ( !$fb ) return;

	if ( wp_verify_nonce( $nonce, 'facebook-deauthorize' ) ) {
		delete_user_meta( get_current_user_id(), 'ecsp-facebook-access-token' );

		$redirect = add_query_arg( array( 'ecsp_message' => 'facebook_disconnected' ), remove_query_arg( array('ecsp', 'code', 'state' ) ) );
		wp_redirect( $redirect );
		exit;
	}

	if ( wp_verify_nonce( $nonce, 'facebook-reauthorize' ) ) {
		$redirect = add_query_arg( array( 'ecsp_message' => 'facebook_reauthorized' ), remove_query_arg( array('ecsp', 'code', 'state' ) ) );
		wp_redirect( $redirect );
		exit;
	}

	if ( wp_verify_nonce( $nonce, 'facebook' ) ) {
		$helper = $fb->getRedirectLoginHelper();

		try {
			$shortLivedAccessToken = $helper->getAccessToken();
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			// When Graph returns an error
			echo 'Graph returned an error: ' . $e->getMessage();
			exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			// When validation fails or other local issues
			echo 'Facebook SDK returned an error: ' . $e->getMessage();
			exit;
		}

		if (isset($shortLivedAccessToken)) {
			// OAuth 2.0 client handler
			$oAuth2Client = $fb->getOAuth2Client();

			// Exchanges a short-lived access token for a long-lived one
			$longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken( $shortLivedAccessToken );

			if ( $longLivedAccessToken ) {
				update_user_meta( get_current_user_id(), 'ecsp-facebook-access-token', (string) $longLivedAccessToken );

				$redirect = add_query_arg( array( 'ecsp_message' => 'facebook_connected' ), remove_query_arg( array( 'ecsp', 'code', 'state' ) ) );
				wp_redirect( $redirect );
				exit;
			}else{
				echo 'Facebook oAuth failed for retrieving long lived access token.';
				exit;
			}
		}
	}

}
add_action( 'admin_init', 'ecsp_facebook_oauth_cb' );
add_action( 'template_redirect', 'ecsp_facebook_oauth_cb' );

function ecsp_facebook_message_connected() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'facebook_connected' ) {
		$access_token = get_user_meta( get_current_user_id(), 'ecsp-facebook-access-token', true );
		
		if ( $access_token ) {
			?>
			<div class="updated eca-notice eca-success">
				<p>Your account is now authorized with Facebook. When writing an article, you will have the option to automatically post the article on your Facebook timeline.</p>
			</div>
			<?php
		}
	}
}
add_action( 'ecsp_notices', 'ecsp_facebook_message_connected' );

function ecsp_facebook_message_disconnected() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'facebook_disconnected' ) {
		?>
		<div class="updated eca-notice eca-success">
			<p>Your authorization with Facebook has been removed. Your articles will no longer be posted to your Facebook timeline when they are published.</p>
		</div>
		<?php
	}
}
add_action( 'ecsp_notices', 'ecsp_facebook_message_disconnected' );

function ecsp_facebook_message_reauthorized() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'facebook_reauthorized' ) {
		?>
		<div class="updated eca-notice eca-success">
			<p>Your authorization with Facebook has been renewed.</p>
		</div>
		<?php
	}
}
add_action( 'ecsp_notices', 'ecsp_facebook_message_reauthorized' );

function ecsp_get_facebook_authorization_url( $callback_url ) {
	$fb = ecsp_get_facebook_api();
	if ( !$fb ) return false;

	$helper = $fb->getRedirectLoginHelper();
	$permissions = array( 'publish_actions' );

	// Add support for pages in the future?
	// array( 'publish_pages', 'publish_actions', 'manage_pages' );

	$loginUrl = $helper->getLoginUrl( $callback_url, $permissions );

	return $loginUrl;
}

function ecsp_display_facebook_integration_button( $user ) {
	$fb = ecsp_get_facebook_api();
	if ( !$fb ) return;

	$access_token = get_user_meta( get_current_user_id(), 'ecsp-facebook-access-token', true );
	?>
	<tr class="profile-integration facebook-integration" id="ecsp-facebook">
		<th>Facebook</th>
		<td>
			<?php
			if ( $access_token ) {

				$helper = $fb->getRedirectLoginHelper();

				$nonce = wp_create_nonce( 'facebook-deauthorize' );
				$callback_url = site_url( add_query_arg( array( 'ecsp' => $nonce ), $_SERVER["REQUEST_URI"] ) ); // Make sure this is a full URL
				$removeUrl = $helper->getLogoutUrl( $access_token, $callback_url );
				?>
				<p><a href="<?php echo esc_attr($removeUrl); ?>" class="social-button social-button-disconnect">Remove Facebook Authorization</a></p>
				<?php
			}else{
				$nonce = wp_create_nonce( 'facebook' );
				$callback_url = site_url( add_query_arg( array( 'ecsp' => $nonce ), $_SERVER["REQUEST_URI"] ) ); // Make sure this is a full URL

				$loginUrl = ecsp_get_facebook_authorization_url( $callback_url );
				?>
				<p><a href="<?php echo esc_attr($loginUrl); ?>" type="button" class="social-button social-button-facebook">Connect with Facebook</a></p>
				<?php
			}
			?>
		</td>
	</tr>
	<?php
}
add_action( 'ecsp_do_social_integration_fields', 'ecsp_display_facebook_integration_button', 5 );

function ecsp_display_facebook_integration_admin_preview( $user ) {
	$access_token = get_user_meta( get_current_user_id(), 'ecsp-facebook-access-token', true );
	?>
	<tr class="profile-integration facebook-integration profile-admin-preview" id="ecsp-facebook">
		<th>Facebook</th>
		<td>
			<?php
			if ( $access_token ) {
				?>
				<p><span class="dashicons dashicons-yes"></span> This user's account is connected to Facebook.</p>
				<?php
			}else{
				?>
				<p><em>Not connected to Facebook.</em></p>
				<?php
			}
			?>
		</td>
	</tr>
	<?php
}
add_action( 'ecsp_do_social_integration_admin_preview', 'ecsp_display_facebook_integration_admin_preview', 5 );

function ecsp_facebook_publish_post( $post_id, $user_id ) {
	// get_field doesn't work here for non-admins, for some reason.
	$share_facebook = get_post_meta($post_id, 'ecsp_share_facebook', true );
	$share_all = get_post_meta($post_id, 'ecsp_share_all', true );

	// The user must have selected to share on Facebook, or share on All
	if ( !$share_facebook && !$share_all ) {
		return;
	}

	$fb = ecsp_get_facebook_api();
	if ( !$fb ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'facebook', 'Could not initialize the Facebook API' );
		return;
	}

	$access_token = get_user_meta( $user_id, 'ecsp-facebook-access-token', true );
	if ( !$access_token ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'facebook', 'No access token is set.' );
		return;
	}

	// Get the user's message. This will include a link to the article.
	$user_message = ecsp_get_share_message( $post_id, 5000, false );

	if ( !$user_message ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'facebook', 'The sharing message was not provided.' );
		return;
	}
	
	$linkData = [
		'link' => get_permalink( $post_id ),
		'message' => $user_message,
	];

	try {
		// Returns a `Facebook\FacebookResponse` object
		$response = $fb->post('/me/feed', $linkData, $access_token);
	} catch(Facebook\Exceptions\FacebookResponseException $e) {
		update_post_meta( $post_id, 'ecsp-facebook-graph-error', 'Graph returned an error: ' . $e->getMessage() );
		delete_post_meta( $post_id, 'ecsp-facebook-graph-node' );
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'facebook', 'Facebook Graph returned an error: ' . $e->getMessage() );
		return;
	} catch(Facebook\Exceptions\FacebookSDKException $e) {
		update_post_meta( $post_id, 'ecsp-facebook-graph-error', 'Facebook SDK returned an error: ' . $e->getMessage() );
		delete_post_meta( $post_id, 'ecsp-facebook-graph-node' );
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'facebook', 'Facebook SDK returned an error: ' . $e->getMessage() );
		return;
	}

	$graphNode = $response->getGraphNode();

	update_post_meta( $post_id, 'ecsp-facebook-graph-node', $graphNode );
	delete_post_meta( $post_id, 'ecsp-facebook-graph-error' );
}
add_action( 'ecsp_publish_post_once', 'ecsp_facebook_publish_post', 10, 2 );