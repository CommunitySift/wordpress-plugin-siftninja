<?php

if ( ! class_exists( 'Sift_Ninja_Settings' ) ) {
	class Sift_Ninja_Settings {
		/**
		 * Construct the plugin object.
		 */
		public function __construct() {
			// Register actions.
				add_action( 'admin_init', array( &$this, 'admin_init' ) );
			add_action( 'admin_menu', array( &$this, 'add_menu' ) );
		} // END public function __construct

		/**
		 * Hook into WP's admin_init action hook.
		 */
		public function admin_init() {
			// Register your plugin's settings.
			register_setting( 'sift_ninja-group', 'sift_ninja_api_key' );
			register_setting( 'sift_ninja-group', 'sift_ninja_account_name' );
			register_setting( 'sift_ninja-group', 'sift_ninja_channel' );
			register_setting( 'sift_ninja-group', 'sift_ninja_trash_on_response' );

			// Add your settings section.
			add_settings_section(
				'sift_ninja-section',
				'Sift Ninja',
				array( &$this, 'settings_section_text' ),
				'sift_ninja'
			);

			// Add your setting's fields.
				add_settings_field(
					'sift_ninja-api_key',
					'API Key',
					array( &$this, 'settings_field_input_text' ),
					'sift_ninja',
					'sift_ninja-section',
					array(
						'field' => 'sift_ninja_api_key',
						'help_text' => 'Your Sift Ninja API key',
						'default' => '',
					)
				);

				add_settings_field(
					'sift_ninja-account_name',
					'Account Name',
					array( &$this, 'settings_field_input_text' ),
					'sift_ninja',
					'sift_ninja-section',
					array(
						'field' => 'sift_ninja_account_name',
						'help_text' => 'Your Sift Ninja Account name',
						'default' => '',
					)
				);

				add_settings_field(
					'sift_ninja-channel',
					'Channel',
					array( &$this, 'settings_field_input_text' ),
					'sift_ninja',
					'sift_ninja-section',
					array(
						'field' => 'sift_ninja_channel',
						'help_text' => 'Your Sift Ninja channel to use',
						'default' => 'comments',
					)
				);

				add_settings_field(
					'sift_ninja-trash',
					'Send to Trash',
					array( &$this, 'settings_field_input_checkbox' ),
					'sift_ninja',
					'sift_ninja-section',
					array(
						'field' => 'sift_ninja_trash_on_response',
						'help_text' => 'If checked, then comments that Sift Ninja marks as bad will be sent directly to trash rather than moderation',
						'default' => '0',
					)
				);

				// Possibly do additional admin_init tasks.
		} // END public static function activate.

		public function settings_section_text() {
				// Think of this as help text for the section.
				echo '<h1>';
				echo esc_html( 'These settings are for configuring Sift Ninja' );
				echo '</h1>';

				echo esc_html( 'If you do not have a Sift ninja account, you can visit <a href="http://www.siftninja.com">Sift Ninja</a> to create your free account.' );
		}

		/**
		 * This function provides text inputs for settings fields.
		 */
		public function settings_field_input_text( $args ) {
				// Get the field name from the $args array.
				$field = $args['field'];
				$default = $args['default'];
				$help_text = $args['help_text'];
				// Get the value of this setting.
				$value = get_option( $field, $default );
				echo sprintf(
					'<input type="text" name="%s" id="%s" value="%s" /> <span>%s</span>',
					esc_attr( $field ),
					esc_attr( $field ),
					esc_attr( $value ),
					esc_html( $help_text )
				);
		} // END public function settings_field_input_text( $args)

		/**
		 * This function provides checkbox inputs for settings fields.
		 */
		public function settings_field_input_checkbox( $args ) {
				// Get the field name and default value from the $args array.
				$field = $args['field'];
				$default = $args['default'];
				$help_text = $args['help_text'];
				// Get the value of this setting, using default.
				$value = get_option( $field, $default );
				// Get the 'checked' string.
				$checked = checked( '1', $value, false );
				//error_log( "value: $value, field: $field, default: $default, checked: $checked" );
				// Echo a proper input type="checkbox".
				echo sprintf(
					'<input type="checkbox" name="%s" id="%s" value="%s" %s /> <span>%s</span>',
					esc_attr( $field ),
					esc_attr( $field ),
					'1',
					esc_attr( $checked ),
					esc_html( $help_text )
				);
		} // END public function settings_field_input_text( $args)

		/**
		 * Add a menu.
		 */
		public function add_menu() {
				// Add a page to manage this plugin's settings.
			add_options_page(
				'Sift Ninja Settings',
				'Sift Ninja',
				'manage_options',
				'sift_ninja',
				array( &$this, 'plugin_settings_page' )
			);
		} // END public function add_menu()

		/**
		 * Menu Callback
		 */
		public function plugin_settings_page() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html( 'You do not have sufficient permissions to access this page.' ) );
			}

			// Render the settings template.
			include( sprintf( '%s/templates/settings.php', dirname( __FILE__ ) ) );
		} // END public function plugin_settings_page()
	} // END class WP_Plugin_Template_Settings
} // END if(!class_exists( 'Sift_Ninja_Settings' ))
