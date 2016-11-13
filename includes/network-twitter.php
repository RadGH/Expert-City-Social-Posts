<?php
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Returns the Twitter SDK object on success. If the AppID or Secret is not set in the settings, returns FALSE.
 *
 * @return bool|\Twitter\Twitter|null
 */
function ecsp_get_twitter_api() {
	static $fb = null;

	if ( $fb === null ) {
		$creds = get_field( 'ecsp_twitter', 'options' );

		if ( empty($creds) || empty($creds[0]['app_id']) || empty($creds[0]['secret']) ) {
			$fb = false;
			return $fb;
		}

		if ( !class_exists( 'Twitter/Twitter' ) ) {
			require_once ECSP_PATH . '/assets/twitter/autoload.php';
		}

		$fb = new Twitter\Twitter(array(
			'app_id' => apply_filters( 'ecsp_twitter_app_id', $creds[0]['app_id'] ),
			'app_secret' => apply_filters( 'ecsp_twitter_app_secret', $creds[0]['secret'] ),
			'default_graph_version' => 'v2.5',
		));
	}

	return $fb;
}

function ecsp_twitter_oauth_cb() {
	$nonce = isset($_REQUEST['ecsp']) ? stripslashes($_REQUEST['ecsp']) : false;
	if ( !$nonce ) return;

	$fb = ecsp_get_twitter_api();
	if ( !$fb ) return;

	if ( wp_verify_nonce( $nonce, 'twitter-deauthorize' ) ) {
		delete_user_meta( get_current_user_id(), 'ecsp-twitter-access-token' );

		$redirect = add_query_arg( array( 'ecsp_message' => 'twitter_disconnected' ), remove_query_arg( array('ecsp', 'code', 'state' ) ) );
		wp_redirect( $redirect );
		exit;
	}

	if ( wp_verify_nonce( $nonce, 'twitter-reauthorize' ) ) {
		$redirect = add_query_arg( array( 'ecsp_message' => 'twitter_reauthorized' ), remove_query_arg( array('ecsp', 'code', 'state' ) ) );
		wp_redirect( $redirect );
		exit;
	}

	if ( wp_verify_nonce( $nonce, 'twitter' ) ) {
		$helper = $fb->getRedirectLoginHelper();

		try {
			$shortLivedAccessToken = $helper->getAccessToken();
		} catch(Twitter\Exceptions\TwitterResponseException $e) {
			// When Graph returns an error
			echo 'Graph returned an error: ' . $e->getMessage();
			exit;
		} catch(Twitter\Exceptions\TwitterSDKException $e) {
			// When validation fails or other local issues
			echo 'Twitter SDK returned an error: ' . $e->getMessage();
			exit;
		}

		if (isset($shortLivedAccessToken)) {
			// OAuth 2.0 client handler
			$oAuth2Client = $fb->getOAuth2Client();

			// Exchanges a short-lived access token for a long-lived one
			$longLivedAccessToken = $oAuth2Client->getLongLivedAccessToken( $shortLivedAccessToken );

			if ( $longLivedAccessToken ) {
				update_user_meta( get_current_user_id(), 'ecsp-twitter-access-token', (string) $longLivedAccessToken );

				$redirect = add_query_arg( array( 'ecsp_message' => 'twitter_connected' ), remove_query_arg( array( 'ecsp', 'code', 'state' ) ) );
				wp_redirect( $redirect );
				exit;
			}else{
				echo 'Twitter oAuth failed for retrieving long lived access token.';
				exit;
			}
		}
	}

}
add_action( 'admin_init', 'ecsp_twitter_oauth_cb' );
add_action( 'template_redirect', 'ecsp_twitter_oauth_cb' );

function ecsp_twitter_message_connected() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'twitter_connected' ) {
		?>
		<div class="updated eca-notice eca-success">
			<p>Your account is now authorized with Twitter. When publishing an article, you will have the option to automatically publish on your Twitter timeline.</p>
		</div>
		<?php
	}
}
add_action( 'ecsp_notices', 'ecsp_twitter_message_connected' );

function ecsp_twitter_message_disconnected() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'twitter_disconnected' ) {
		?>
		<div class="updated eca-notice eca-success">
			<p>Your authorization with Twitter has been removed. Your articles will no longer be posted to your Twitter timeline when they are published.</p>
		</div>
		<?php
	}
}
add_action( 'ecsp_notices', 'ecsp_twitter_message_disconnected' );

function ecsp_twitter_message_reauthorized() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'twitter_reauthorized' ) {
		?>
		<div class="updated eca-notice eca-success">
			<p>Your authorization with Twitter has been renewed.</p>
		</div>
		<?php
	}
}
add_action( 'ecsp_notices', 'ecsp_twitter_message_reauthorized' );

function ecsp_get_twitter_authorization_url( $callback_url ) {
	$fb = ecsp_get_twitter_api();
	if ( !$fb ) return false;

	$helper = $fb->getRedirectLoginHelper();
	$permissions = array( 'publish_actions' );

	// Add support for pages in the future?
	// array( 'publish_pages', 'publish_actions', 'manage_pages' );

	$loginUrl = $helper->getLoginUrl( $callback_url, $permissions );

	return $loginUrl;
}

function ecsp_display_twitter_integration_button( $user ) {
	$fb = ecsp_get_twitter_api();
	if ( !$fb ) return;

	$access_token = get_user_meta( get_current_user_id(), 'ecsp-twitter-access-token', true );
	?>
	<tr class="profile-integration twitter-integration" id="ecsp-twitter">
		<th>Twitter</th>
		<td>
			<?php
			if ( $access_token ) {

				$helper = $fb->getRedirectLoginHelper();

				$nonce = wp_create_nonce( 'twitter-deauthorize' );
				$callback_url = site_url( add_query_arg( array( 'ecsp' => $nonce ), $_SERVER["REQUEST_URI"] ) ); // Make sure this is a full URL
				$removeUrl = $helper->getLogoutUrl( $access_token, $callback_url );
				?>
				<p><a href="<?php echo esc_attr($removeUrl); ?>" class="social-button social-button-disconnect">Remove Twitter Authorization</a></p>
				<p class="description">Your articles can automatically posted to Twitter when they are published.</p>
				<?php
			}else{
				$nonce = wp_create_nonce( 'twitter' );
				$callback_url = site_url( add_query_arg( array( 'ecsp' => $nonce ), $_SERVER["REQUEST_URI"] ) ); // Make sure this is a full URL

				$loginUrl = ecsp_get_twitter_authorization_url( $callback_url );
				?>
				<p><a href="<?php echo esc_attr($loginUrl); ?>" type="button" class="social-button social-button-twitter">Connect with Twitter</a></p>
				<p class="description">By connecting to Twitter your posts will automatically be posted on your timeline after they've been published.</p>
				<?php
			}
			?>
		</td>
	</tr>
	<?php
}
add_action( 'ecsp_do_social_integration_fields', 'ecsp_display_twitter_integration_button', 5 );

function ecsp_display_twitter_integration_admin_preview( $user ) {
	$access_token = get_user_meta( get_current_user_id(), 'ecsp-twitter-access-token', true );
	?>
	<tr class="profile-integration twitter-integration profile-admin-preview" id="ecsp-twitter">
		<th>Twitter</th>
		<td>
			<?php
			if ( $access_token ) {
				?>
				<p><span class="dashicons dashicons-yes"></span> This user's account is connected to Twitter.</p>
				<?php
			}else{
				?>
				<p><em>Not connected to Twitter.</em></p>
				<?php
			}
			?>
		</td>
	</tr>
	<?php
}
add_action( 'ecsp_do_social_integration_admin_preview', 'ecsp_display_twitter_integration_admin_preview', 5 );

function ecsp_twitter_publish_post( $post_id, $user_id ) {
	// get_field doesn't work here for non-admins, for some reason.
	$share_twitter = get_post_meta($post_id, 'ecsp_share_twitter', true );
	$share_all = get_post_meta($post_id, 'ecsp_share_all', true );

	// The user must have selected to share on Twitter, or share on All
	if ( !$share_twitter && !$share_all ) {
		return;
	}

	$fb = ecsp_get_twitter_api();
	if ( !$fb ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'twitter', 'Could not initialize the Twitter API' );
		return;
	}

	$access_token = get_user_meta( $user_id, 'ecsp-twitter-access-token', true );
	if ( !$access_token ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'twitter', 'No access token is set.' );
		return;
	}

	// Get the user's message. This will include a link to the article.
	$user_message = ecsp_get_share_message( $post_id, 5000, false );

	if ( !$user_message ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'twitter', 'The sharing message was not provided.' );
		return;
	}
	
	$linkData = [
		'link' => get_permalink( $post_id ),
		'message' => $user_message,
	];

	try {
		// Returns a `Twitter\TwitterResponse` object
		$response = $fb->post('/me/feed', $linkData, $access_token);
	} catch(Twitter\Exceptions\TwitterResponseException $e) {
		update_post_meta( $post_id, 'ecsp-twitter-graph-error', 'Graph returned an error: ' . $e->getMessage() );
		delete_post_meta( $post_id, 'ecsp-twitter-graph-node' );
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'twitter', 'Twitter Graph returned an error: ' . $e->getMessage() );
		return;
	} catch(Twitter\Exceptions\TwitterSDKException $e) {
		update_post_meta( $post_id, 'ecsp-twitter-graph-error', 'Twitter SDK returned an error: ' . $e->getMessage() );
		delete_post_meta( $post_id, 'ecsp-twitter-graph-node' );
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'twitter', 'Twitter SDK returned an error: ' . $e->getMessage() );
		return;
	}

	$graphNode = $response->getGraphNode();

	update_post_meta( $post_id, 'ecsp-twitter-graph-node', $graphNode );
	delete_post_meta( $post_id, 'ecsp-twitter-graph-error' );
}
add_action( 'ecsp_publish_post_once', 'ecsp_twitter_publish_post', 10, 2 );