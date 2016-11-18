<?php
if( !defined( 'ABSPATH' ) ) exit;

use Abraham\TwitterOAuth\TwitterOAuth;

/**
 * Returns a Twitter API object on success. Intended for anonymous calls. For authorized api use, try ecsp_get_twitter_auth_api.
 */
function ecsp_get_twitter_api() {
	static $tw = null;

	// Create a new twitter object if it doesn't already exist, or if the oAuth Token or secret is provided.
	if ( $tw === null ) {
		$creds = get_field( 'ecsp_twitter', 'options' );

		if ( empty($creds) || empty($creds[0]['app_id']) || empty($creds[0]['secret']) ) {
			$tw = false;
			return $tw;
		}

		if ( !class_exists( 'Twitter/Twitter' ) ) {
			require_once ECSP_PATH . '/assets/twitter/autoload.php';
		}


		$tw = new TwitterOAuth(
			apply_filters( 'ecsp_twitter_app_id', $creds[0]['app_id'] ),
			apply_filters( 'ecsp_twitter_app_secret', $creds[0]['secret'] )
		);
	}

	return $tw;
}

/**
 * Returns the Twitter API object on success. If the AppID or Secret is not set in the settings, returns FALSE.
 * @param null $oauthToken
 * @param null $oauthTokenSecret
 * @return TwitterOAuth|bool|null
 */
function ecsp_get_twitter_auth_api( $oauthToken = null, $oauthTokenSecret = null) {
	static $tw = null;

	// If token and secret are not explicitly provided, use the credentials for the current user.
	if ( $oauthToken === null && $oauthTokenSecret === null ) {
		$access_token = get_user_meta( get_current_user_id(), 'ecsp-twitter-access-token', true );

		if ( $access_token ) {
			$oauthToken = $access_token['oauth_token'];
			$oauthTokenSecret = $access_token['oauth_token_secret'];
		}
	}

	// User cannot authenticate with Twitter.
	if ( !$oauthToken ) return false;

	// Create a new twitter object if it doesn't already exist, or if the oAuth Token or secret is provided.
	if ( $tw === null) {
		$creds = get_field( 'ecsp_twitter', 'options' );

		if ( empty($creds) || empty($creds[0]['app_id']) || empty($creds[0]['secret']) ) {
			$tw = false;
			return $tw;
		}

		if ( !class_exists( 'Twitter/Twitter' ) ) {
			require_once ECSP_PATH . '/assets/twitter/autoload.php';
		}


		$tw = new TwitterOAuth(
			apply_filters( 'ecsp_twitter_app_id', $creds[0]['app_id'] ),
			apply_filters( 'ecsp_twitter_app_secret', $creds[0]['secret'] ),
			$oauthToken,
			$oauthTokenSecret
		);
	}

	return $tw;
}

function ecsp_twitter_oauth_cb() {
	$nonce = isset($_REQUEST['ecsp']) ? stripslashes($_REQUEST['ecsp']) : false;
	if ( !$nonce ) return;

	if ( wp_verify_nonce( $nonce, 'twitter-deauthorize' ) ) {
		$access_token = get_user_meta( get_current_user_id(), 'ecsp-twitter-access-token', true );

		$redirect = remove_query_arg( array('ecsp', 'oauth_token', 'oauth_verifier' ) );

		// Twitter does not let you deauthorize an app that uses oauth tokens, so just delete it.
		if ( $access_token ) {
			delete_user_meta( get_current_user_id(), 'ecsp-twitter-access-token' );
			$redirect = add_query_arg( array( 'ecsp_message' => 'twitter_disconnected' ), $redirect );
		}

		wp_redirect( $redirect );
		exit;
	}

	if ( wp_verify_nonce( $nonce, 'twitter' ) ) {
		$oauth_token = isset($_REQUEST['oauth_token']) ? stripslashes($_REQUEST['oauth_token']) : false;
		$oauth_verifier = isset($_REQUEST['oauth_verifier']) ? stripslashes($_REQUEST['oauth_verifier']) : false;

		$twAuth = ecsp_get_twitter_auth_api( $oauth_token, $oauth_verifier );
		if ( !$twAuth ) return;

		try {
			// Returns a `Facebook\FacebookResponse` object
			$result = $twAuth->oauth( 'oauth/access_token', array( 'oauth_verifier' => $oauth_verifier ) );
		} catch( Abraham\TwitterOAuth\TwitterOAuthException $e ) {
			$message = $e->getMessage();
			$message.= "\n\n";
			$message.= "oauth_token: <code>". esc_html( print_r( $oauth_token, true ) ) ."</code>\n\n";
			$message.= "oauth_verifier: <code>". esc_html( print_r( $oauth_verifier, true ) ) ."</code>\n\n";
			$message.= "tw: <code>". esc_html( print_r( $twAuth, true ) ) ."</code>\n\n";

			wp_die( wpautop($message), 'Error: ' . $e->getCode(), $e );
			exit;
		}

		if ( $result && !empty($result['oauth_token']) ) {
			$result['_preliminary_oauth_token'] = $oauth_token;
			$result['_preliminary_oauth_verifier'] = $oauth_verifier;
			update_user_meta( get_current_user_id(), 'ecsp-twitter-access-token', $result ); // oauth_token, oauth_token_secret, user_id, screen_name, x_auth_expires

			$redirect = add_query_arg( array( 'ecsp_message' => 'twitter_connected' ), remove_query_arg( array( 'ecsp', 'oauth_token', 'oauth_verifier' ) ) );
			wp_redirect( $redirect );
			exit;
		}else{
			echo 'Twitter oAuth failed for retrieving long lived access token.';
			exit;
		}
	}

}
add_action( 'admin_init', 'ecsp_twitter_oauth_cb' );
add_action( 'template_redirect', 'ecsp_twitter_oauth_cb' );

function ecsp_twitter_message_connected() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'twitter_connected' ) {
		$access_token = get_user_meta( get_current_user_id(), 'ecsp-twitter-access-token', true );

		if ( $access_token ) {
			?>
			<div class="updated eca-notice eca-success">
				<p>Hello @<?php echo esc_html($access_token['screen_name']); ?>! Your account is now authorized with Twitter. When writing an article, you will have the option to automatically post the article on Twitter when it gets published.</p>
			</div>
			<?php
		}
	}
}
add_action( 'ecsp_notices', 'ecsp_twitter_message_connected' );

function ecsp_twitter_message_disconnected() {
	if ( isset($_REQUEST['ecsp_message']) && $_REQUEST['ecsp_message'] == 'twitter_disconnected' ) {
		?>
		<div class="updated eca-notice eca-success">
			<p>Your authorization with Twitter has been removed. Your articles will no longer be posted to Twitter when they are published.</p>
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
	$tw = ecsp_get_twitter_api();
	if ( !$tw ) return false;

	// Gets a request token from Twitter, with our callback url
	$request_token = $tw->oauth( "oauth/request_token", array( 'oauth_callback' => $callback_url ) );

	// Produces a URL that includes the provided request token
	$url = $tw->url( "oauth/authorize", $request_token );

	return $url;
}

function ecsp_display_twitter_integration_button( $user ) {
	$tw = ecsp_get_twitter_api();
	if ( !$tw ) return;

	$access_token = get_user_meta( $user->ID, 'ecsp-twitter-access-token', true );
	?>
	<tr class="profile-integration twitter-integration" id="ecsp-twitter">
		<th>Twitter</th>
		<td>
			<?php
			if ( $access_token ) {
				// Twitter does not provide a URL to logout or invalidate a token, so we'll use the callback url to do that instead.
				$nonce = wp_create_nonce( 'twitter-deauthorize' );
				$removeUrl = site_url( add_query_arg( array( 'ecsp' => $nonce ), $_SERVER["REQUEST_URI"] ) );
				?>
				<p><a href="<?php echo esc_attr($removeUrl); ?>" class="social-button social-button-disconnect">Remove Twitter Authorization</a></p>
				<?php
			}else{
				$nonce = wp_create_nonce( 'twitter' );
				$callback_url = site_url( add_query_arg( array( 'ecsp' => $nonce ), $_SERVER["REQUEST_URI"] ) ); // Make sure this is a full URL

				$loginUrl = ecsp_get_twitter_authorization_url( $callback_url );
				?>
				<p><a href="<?php echo esc_attr($loginUrl); ?>" type="button" class="social-button social-button-twitter">Connect with Twitter</a></p>
				<?php
			}
			?>
		</td>
	</tr>
	<?php
}
add_action( 'ecsp_do_social_integration_fields', 'ecsp_display_twitter_integration_button', 10 );

function ecsp_display_twitter_integration_admin_preview( $user ) {
	$access_token = get_user_meta( $user->ID, 'ecsp-twitter-access-token', true );
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

	$twAuth = ecsp_get_twitter_auth_api();
	if ( !$twAuth ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'twitter', 'Could not initialize the Twitter API' );
		return;
	}

	// Get the user's message. This will include a link to the article.
	$user_message = ecsp_get_share_message( $post_id, 140, true );

	if ( !$user_message ) {
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'twitter', 'The sharing message was not provided.' );
		return;
	}

	try {
		$result = $twAuth->post( 'statuses/update', array( 'status' => $user_message ) );
	} catch( Abraham\TwitterOAuth\TwitterOAuthException $e ) {
		update_post_meta( $post_id, 'ecsp-twitter-error', 'Twitter returned an error: ' . $e->getMessage() );
		delete_post_meta( $post_id, 'ecsp-twitter-share-result' );
		ecsp_log_sharing_error_for_user( $user_id, $post_id, 'twitter', 'Twitter returned an error: ' . $e->getMessage() );
		return;
	}

	update_post_meta( $post_id, 'ecsp-twitter-share-result', $result );
	delete_post_meta( $post_id, 'ecsp-twitter-error' );
}
add_action( 'ecsp_publish_post_once', 'ecsp_twitter_publish_post', 10, 2 );