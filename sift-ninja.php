<?php
/*
Plugin Name: Sift Ninja
Plugin URI: http://www.siftninja.com
Description: A WordPress Plugin to use Sift Ninja
Version: 1.0
Author: Community Sift
Author URI: http://www.siftninja.com
License: MIT
*/
/*
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
*/

if(!class_exists('Sift_Ninja'))
{
    class Sift_Ninja
    {
		const base_url = 'siftninja.com';
		
        /**
         * Construct the plugin object
         */
        public function __construct()
        {
			// Initialize Settings
			require_once(sprintf("%s/settings.php", dirname(__FILE__)));
			$Sift_Ninja_Settings = new Sift_Ninja_Settings();

			$plugin = plugin_basename(__FILE__);
			add_filter("plugin_action_links_$plugin", array( $this, 'plugin_settings_link' ));


			// Add a filter to be run on comment approval.  Set it to a high priority so it is one of
			// the last to run, in case earlier filters clean it up
            add_filter('pre_comment_approved', array( $this, 'filter_pre_comment_approved' ), 99, 2);

        } // END public function __construct

        /**
         * Activate the plugin
         */
        public static function activate()
        {
            // Add filter to pre_comment_approved
			//add_filter('pre_comment_approved', array( self::$instance, 'filter_pre_comment_approved' ));
        } // END public static function activate

        /**
         * Deactivate the plugin
         */
        public static function deactivate()
        {
            // Remove filters
			//remove_filter('pre_comment_approved', array( self::$instance, 'filter_pre_comment_approved' ));
        } // END public static function deactivate
		

		// Add the settings link to the plugins page
		function plugin_settings_link($links)
		{
			$settings_link = '<a href="options-general.php?page=sift_ninja">Settings</a>';
			array_unshift($links, $settings_link);
			return $links;
		}
		
		//
		// Filter hooks
		//
		
		// pre_comment_approved
		function filter_pre_comment_approved($approved, $commentdata)
		{
			return $this->get_sift_comment_response($commentdata, $approved);
		}
		
		//
		// Sift functions
		//
		
		// Get url to call sift.  This will return <account_name>.siftninja.com
		// TODO: should this be the option instead of just account name?  what if
		// siftninja.com changes to something else?
		
		function get_sift_url() {
			return sprintf('http://%s.%s', $this->get_sift_account_name(), $this::base_url);
		}
		
		// Comments endpoint
		function get_sift_endpoint() {
			return sprintf('%s/api/v1/channel/comments/sifted_data', $this->get_sift_url());
		}
		
		// API Key
		function get_sift_api_key() {
			return get_option('sift_ninja_api_key');
		}
		
		// Account Name
		function get_sift_account_name() {
			return get_option('sift_ninja_account_name');
		}

		// Account Name
		function get_sift_channel() {
			return get_option('sift_ninja_channel');
		}

		// Get a Sift response to a comment
		// Returns true/false based on Sift Ninja's response to the comment text
		function get_sift_comment_response($commentdata, $approved) {


            //error_log(print_r($commentdata, true));


			try {
				$url = $this->get_sift_endpoint();
				//error_log("url: $url");
			    $api_key = $this->get_sift_api_key();
				//error_log("api_key: $api_key");
			    $account_name = $this->get_sift_account_name();
				//error_log("account: $account_name");

				$user_id = sprintf('%s-%s', $account_name, $commentdata['user_id']);
				//error_log("user_id: $user_id");

                $auth_token = base64_encode(":$api_key");
                $headers = array(
                    'Authorization' => "Basic $auth_token"
                );
				//error_log("headers:  ");
				//error_log(print_r($headers, true));

                $comment_post_ID = $commentdata['comment_post_ID'];
                $comment_date = $commentdata['comment_date_gmt'];
                $context = "$comment_post_ID-$comment_date";
                $body = wp_json_encode(array(
                            'text' => $commentdata['comment_content'],
                            'user_id' => $user_id,
                            'user_display_name' => $commentdata['comment_author'],
                            'content_id' => "$context"
                        ));
				//error_log("body:  ");
				//error_log(print_r($body, true));

				$response = wp_remote_post( $url, array(
				        'headers' => $headers,
						'body' => $body
					)
				);
				//error_log("response: ");
				//error_log(print_r($response, true));



                // Check for WPError
                if ( is_wp_error( $response ) ) {
                    $error_message = $response->get_error_message();
    				//error_log("SiftNinja: Something went wrong: $error_message");
                    echo "Something went wrong: $error_message";
                } else {
                    // Check response from Sift Ninja
                    if ($response['response']['code'] != '200') {
                        // Got an error code from Sift
                        error_log("Got error from Sift:");
                        error_log(print_r($response['response'], true));
                    } else {

                        $sift_response = json_decode($response['body']);
                        //error_log("sift_response: ");
                        if ($sift_response != '') {
                            //error_log(print_r($sift_response, true));
                            $check_response = $sift_response->response;
                            if ($check_response === 1 or $check_response === true) {
                                // If Sift allowed it, then return the previous result
                                return $approved;
                            } else {
                                // If Sift did not allow it, then set it moderate
                                // Check if the user wants to moderate or trash
                                if ( get_option('sift_ninja_trash_on_response') === '1' ) {
                                    return 'trash';
                                } else {
                                    return 0;
                                }
                            }
                        } else {
                            // Something went wrong with the response from Sift
                            return $approved;
                        };
                    };
                };


			} catch (Exception $ex) {
				// TODO: what to do on error?
				return $approved;
				
			}




		}
		
    } // END class Sift_Ninja
} // END if(!class_exists('Sift_Ninja'))

if(class_exists('Sift_Ninja'))
{
	// Installation and uninstallation hooks
	register_activation_hook(__FILE__, array('Sift_Ninja', 'activate'));
	register_deactivation_hook(__FILE__, array('Sift_Ninja', 'deactivate'));
	// instantiate the plugin class
	$sift_ninja = new Sift_Ninja();
}

?>