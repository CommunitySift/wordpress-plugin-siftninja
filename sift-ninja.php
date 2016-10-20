<?php
/**
Plugin Name: Sift Ninja
Plugin URI: http://www.siftninja.com
Description: A WordPress Plugin to use Sift Ninja
Version: 1.1
Author: Community Sift
Author URI: http://www.siftninja.com
License: MIT
 **/

/**
Copyright (c) 2016  Commnunity Sift  (email : siftninja@commnunitysift.com)

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated
documentation files (the "Software"), to deal in the Software without restriction, including without limitation the
rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit
persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial
portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE
WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR
OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE
 **/

if ( ! class_exists( 'Sift_Ninja' ) ) {
	class Sift_Ninja
		{
		const base_url = 'siftninja.com';

				/**
				 * Construct the plugin object
				 */
		public function __construct() {
			// Initialize Settings.
			require_once( sprintf( '%s/settings.php', dirname( __FILE__ ) ) );
			$Sift_Ninja_Settings = new Sift_Ninja_Settings();

			$plugin = plugin_basename( __FILE__ );
			add_filter( "plugin_action_links_$plugin", array( $this, 'plugin_settings_link' ) );

			// Add a filter to be run on comment approval.  Set it to a high priority so it is one of
			// the last to run, in case earlier filters clean it up.
			add_filter( 'pre_comment_approved', array( $this, 'filter_pre_comment_approved' ), 99, 2 );

		} // END public function __construct

				/**
				 * Activate the plugin
				 */
		public static function activate() {
			// Add filter to pre_comment_approved.
			// add_filter( 'pre_comment_approved', array( self::$instance, 'filter_pre_comment_approved' ));
		} // END public static function activate.

				/**
				 * Deactivate the plugin.
				 */
		public static function deactivate() {
			// Remove filters.
			// remove_filter( 'pre_comment_approved', array( self::$instance, 'filter_pre_comment_approved' ));
		} // END public static function deactivate


		/** Add the settings link to the plugins page. **/
		function plugin_settings_link( $links ) {
			$settings_link = '<a href="options-general.php?page=sift_ninja">Settings</a>';
			array_unshift( $links, $settings_link );
			return $links;
		}

		// Filter hooks.
		/** Function pre_comment_approved **/
		function filter_pre_comment_approved( $approved, $commentdata ) {
			return $this->get_sift_comment_response( $commentdata, $approved );
		}

		// Sift functions.
		/** Get url to call sift.  This will return <account_name>.siftninja.com
				TODO: should this be the option instead of just account name?  what if
				siftninja.com changes to something else? **/
		function get_sift_url() {
			return sprintf( 'https://%s.%s', $this->get_sift_account_name(), $this::base_url );
		}

		/** Comments endpoint. **/
		function get_sift_endpoint() {
			$channel = $this->get_sift_channel();
			$api_url = $this->get_sift_url();
			return sprintf( '%s/api/v1/channel/%s/sifted_data', $api_url, $channel );
		}

		/** API Key **/
		function get_sift_api_key() {
			return get_option( 'sift_ninja_api_key' );
		}

		/** Account Name **/
		function get_sift_account_name() {
			return get_option( 'sift_ninja_account_name' );
		}

		/** Account Name **/
		function get_sift_channel() {
			return get_option( 'sift_ninja_channel' );
		}

		// Get a Sift response to a comment.
		/** Returns true/false based on Sift Ninja's response to the comment text **/
		function get_sift_comment_response( $commentdata, $approved ) {
			//error_log("@@@ Enter get_sift_comment_response");
			//error_log(print_r($approved, true));
			//error_log(print_r($commentdata, true));
			//error_log("@@@ After param debug");
			
			//
			// Check if we need to even send to Sift Ninja
			//
			
			// If it is already marked as spam or trash, just return that
			if ( ($approved === 'spam') or ($approved === 'trash') ) {
				return $approved;
			};
			
			try {
				$url = $this->get_sift_endpoint();
				$api_key = $this->get_sift_api_key();
				$account_name = $this->get_sift_account_name();

				$user_id = sprintf( '%s-%s', $account_name, $commentdata['user_id'] );

				$auth_token = base64_encode( ":$api_key" );
				$headers = array(
						'Authorization' => "Basic $auth_token",
				);

				$comment_post_ID = $commentdata['comment_post_ID'];
				$comment_date = $commentdata['comment_date_gmt'];
				$context = "$comment_post_ID-$comment_date";
				$body = wp_json_encode(array(
										'text' => $commentdata['comment_content'],
										'user_id' => $user_id,
										'user_display_name' => $commentdata['comment_author'],
										'content_id' => "$context",
				));

				//error_log("SiftNinja:   url: $url");

				$response = wp_remote_post( $url, array(
					'headers' => $headers,
					'body' => $body,
					)
				);
				// Check for WPError.
				if ( is_wp_error( $response ) ) {
					$error_message = $response->get_error_message();
					error_log("SiftNinja: Got WP_Error: ");
					error_log("SiftNinja:   WP_Error message: $error_message");
					error_log(print_r($response, true));
					echo esc_html( "Something went wrong: $error_message" );
				} else {
					// Check response from Sift Ninja.
					$response_code = $response['response']['code'];
					// Check if the code is not 200 or '200'
					if ( !( ($response_code === 200) or ($response_code === '200')) ) {
						// Got an error code from Sift.
						error_log( "SiftNinja: Got error from Sift: response_code = $response_code" );
						error_log( print_r( $response['response'], true ) );
					} else {

						$sift_response = json_decode( $response['body'] );
						//error_log( "SiftNinja: sift_ninja response = ");
						//error_log( print_r( $sift_response, true ) );
						
						if ( '' !== $sift_response ) {
							//error_log( "SiftNinja: Checking response");
							$check_response = $sift_response->response;
							if ( 1 === $check_response or true === $check_response ) {
								// If Sift allowed it, then return the previous result.
								//error_log( "SiftNinja: Sift Ninja said okay");
								return $approved;
							} else {
								// If Sift did not allow it, then set it moderate.
								//error_log( "SiftNinja: Sift Ninja said not okay");
								// Check if the user wants to moderate or trash.
								if ( get_option( 'sift_ninja_trash_on_response' ) === '1' ) {
									return 'trash';
								} else {
									return 0;
								}
							}
						} else {
							// Something went wrong with the response from Sift.
							error_log( "SiftNinja: Not checking response. Sift response = ");
							error_log( print_r( $sift_response, true ) );
							return $approved;
						};
					};
				};

			} catch (Exception $ex) {
				// TODO: what to do on error?
				error_log( 'SiftNinja: Got exception:' );
				error_log( print_r( $response['response'], true ) );
				return $approved;

			}
			// Should not get here, but going to return previous value just in case
			error_log( 'SiftNinja: Got to end of funtion' );
			return $approved;
		}
	} // END class Sift_Ninja.
} // END if(!class_exists( 'Sift_Ninja'))

if ( class_exists( 'Sift_Ninja' ) ) {
	// Installation and uninstallation hooks.
	register_activation_hook( __FILE__, array( 'Sift_Ninja', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'Sift_Ninja', 'deactivate' ) );
	// Instantiate the plugin class.
	$sift_ninja = new Sift_Ninja();
}
