<?php
/*
Plugin Name: Automatic Post Date Filler
Plugin URI: https://wordpress.org/plugins/automatic-post-date-filler/
Description: Automatically sets custom date and time when editing posts.
Version: 1.1
Author: Devtard
Author URI: http://devtard.com
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html
*/

/*
Copyright (C) 2013-2015  Devtard (gmail.com ID: devtard)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS For A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

defined('ABSPATH') or exit; //prevents direct access to the file

class Automatic_Post_Date_Filler{
	private static $instance;

	public function __construct(){
		if(isset(self::$instance)){
			wp_die(get_class($this) .' is a singleton class; only one instance is allowed.');
		}

		self::$instance = $this;

		register_activation_hook(__FILE__,  array($this, 'install_plugin'));
		register_deactivation_hook(__FILE__,  array($this, 'deactivate_plugin'));

		if(is_admin()){
			add_action('admin_menu', array($this, 'menu_link'));
			add_action('admin_notices', array($this, 'plugin_admin_notices'), 20);
			add_action('admin_init', array($this, 'admin_init_actions'));

			global $pagenow;
			if(in_array($pagenow, array('plugins.php', 'update-core.php', 'update.php')) or ($pagenow == 'options-general.php' and isset($_GET['page']) and $_GET['page'] == 'automatic-post-tagger')){
				add_action('plugins_loaded', array($this, 'update_plugin'));
			}
		}
	}

	/**
	 * Various vatiables
	 * @param	int	$var	Variable type
	 * @return	
	 */
	public function get_variable($variable_name){
		$variables = array(
			'plugin_url' => WP_PLUGIN_URL . "/" . basename(dirname(__FILE__)) . "/",
			'plugin_basename' => plugin_basename(__FILE__),
			'options_page_name' => 'automatic-post-date-filler',
			'option_name_meta' => 'automatic_post_date_filler_meta',
			'option_name_settings' => 'automatic_post_date_filler_settings',
			'options_group' => 'apdf_options_group',
			'meta_array' => get_option('automatic_post_date_filler_meta'),
			'settings_array' => get_option('automatic_post_date_filler_settings'),
			'message_updated' => '<div class="updated"><p>',
			'message_error' => '<div class="notice is-dismissible error"><p>',
			'message_note' => '<div class="notice is-dismissible apdf_note"><p>',
			'message_suffix' => '</p></div>',

			'default_meta' => array(
				'plugin_version' => $this->get_plugin_version(),
				'admin_notice_install' => '1',
				'admin_notice_update' => '0',
				),

			'default_settings' => array(
				'affected_post_types' => array('post'),
				'affected_post_statuses' => array('auto-draft', 'draft', 'pending'),
				'analyzed_post_types' => array('post'),
				'analyzed_post_statuses' => array('publish', 'future'),
				'custom_date_reference' => '2',
				'custom_date_difference' => '1',
				'custom_date_operation' => '1',
				'custom_time_reference' => '4',
				'custom_time_operation' => '1',
				'custom_time_difference' => '60',
				'custom_time_hours' => '00',
				'custom_time_minutes' => '00'
				)
			);

		return $variables[$variable_name];
	}

	/**
	 * Various actions and filters
	 */
	public function admin_init_actions(){
		global $pagenow;

		if($pagenow == 'plugins.php'){
			add_filter('plugin_action_links_'. $this->get_variable('plugin_basename'), array($this, 'plugin_action_links'), 10, 1);
			add_filter('plugin_row_meta',  array($this, 'plugin_meta_links'), 10, 2);
		}

		if($pagenow == 'options-general.php' and isset($_GET['page']) and $_GET['page'] == $this->get_variable('options_page_name')){
			add_action('admin_enqueue_scripts', array($this, 'load_options_page_css'));
		}

		if(in_array($pagenow, array('post.php', 'post-new.php'))){
			add_action('admin_head', array($this,'calculation_init' ));
		}
	}

	/**
	 * Creates default plugin data
	 */
	public function install_plugin(){
		if($this->get_variable('settings_array') === false){
			add_option($this->get_variable('option_name_settings'), $this->get_variable('default_settings'), '', 'no');
		}
		if($this->get_variable('meta_array') === false){
			add_option($this->get_variable('option_name_meta'), $this->get_variable('default_meta'), '', 'no');
		}
	}

	/**
	 * Unregisters the plugin's setting
	 */
	public function deactivate_plugin(){
		unregister_setting($this->get_variable('options_group'), $this->get_variable('option_name_settings'));
	}

	/**
	 * Updates the satabase structure every time a new version is available
	 */
	public function update_plugin(){
		if(current_user_can('manage_options')){
			$settings = $this->get_variable('settings_array');
			$meta = $this->get_variable('meta_array');
			$current_version = $this->get_plugin_version();

			$old_settings = get_option('automatic_post_date_filler');

			if($meta['plugin_version'] != $current_version or $old_settings !== false){
				$this->install_plugin();
				$settings = $this->get_variable('settings_array');
				$meta = $this->get_variable('meta_array');

				if($old_settings['apdf_plugin_version'] == '1.0'){
					$meta['admin_notice_install'] = 0;
					$settings['affected_post_statuses'] = explode(',', $old_settings['apdf_post_statuses']);

					if($old_settings['apdf_custom_date'] == 1){
						$settings['custom_date_difference'] = '0';
					}
					elseif($old_settings['apdf_custom_date'] == 2){
						$settings['custom_date_difference'] = $old_settings['apdf_custom_date_2_extra_days'];
					}
					elseif($old_settings['apdf_custom_date'] == 3){
						$settings['custom_date_difference'] = $old_settings['apdf_custom_date_3_extra_days'];
					}

					if($old_settings['apdf_custom_time'] == 1){
						$settings['custom_time_difference'] = '0';
					}
					elseif($old_settings['apdf_custom_time'] == 2){
						$settings['custom_time_difference'] = $old_settings['apdf_custom_time_2_extra_minutes'];
					}
					elseif($old_settings['apdf_custom_time'] == 3 or $old_settings['apdf_custom_time'] == 4){
						$settings['custom_time_difference'] = $old_settings['apdf_custom_time_3_extra_minutes'];
					}


					if($old_settings['apdf_custom_date'] == 1 or $old_settings['apdf_custom_date'] == 2){
						$settings['custom_date_reference'] = '1';
					}
					elseif($old_settings['apdf_custom_date'] == 3){
						$settings['custom_date_reference'] = '2';
					}

					if($old_settings['apdf_custom_time'] == 1 or $old_settings['apdf_custom_time'] == 2){
						$settings['custom_time_reference'] = '1';
					}
					elseif($old_settings['apdf_custom_time'] == 3){
						$settings['custom_time_reference'] = '2';
					}
					elseif($old_settings['apdf_custom_time'] == 4){
						$settings['custom_time_reference'] = '4';
					}

					$settings['custom_time_hours'] = $old_settings['apdf_custom_time_4_hours'];
					$settings['custom_time_minutes'] = $old_settings['apdf_custom_time_4_minutes'];

					delete_option('automatic_post_date_filler');
				}

				if($old_settings['apdf_plugin_version'] == '1.0.2'){
					$meta['admin_notice_install'] = 0;
					$settings['affected_post_statuses'] = explode(',', $old_settings['apdf_post_statuses']);

					if($old_settings['apdf_custom_date'] == 1){
						$settings['custom_date_difference'] = '0';
					}
					elseif($old_settings['apdf_custom_date'] == 2){
						$settings['custom_date_difference'] = $old_settings['apdf_custom_date_2_extra_days'];
					}
					elseif($old_settings['apdf_custom_date'] == 3){
						$settings['custom_date_difference'] = $old_settings['apdf_custom_date_3_extra_days'];
					}
					elseif($old_settings['apdf_custom_date'] == 4){
						$settings['custom_date_difference'] = $old_settings['apdf_custom_date_4_extra_days'];
					}

					if($old_settings['apdf_custom_time'] == 1){
						$settings['custom_time_difference'] = '0';
					}
					elseif($old_settings['apdf_custom_time'] == 2){
						$settings['custom_time_difference'] = $old_settings['apdf_custom_time_2_extra_minutes'];
					}
					elseif($old_settings['apdf_custom_time'] == 3 or $old_settings['apdf_custom_time'] == 4){
						$settings['custom_time_difference'] = $old_settings['apdf_custom_time_3_extra_minutes'];
					}
					elseif($old_settings['apdf_custom_time'] == 5){
						$settings['custom_time_difference'] = $old_settings['apdf_custom_time_5_extra_minutes'];
					}

					if($old_settings['apdf_custom_date'] == 1 or $old_settings['apdf_custom_date'] == 2){
						$settings['custom_date_reference'] = '1';
					}
					elseif($old_settings['apdf_custom_date'] == 3){
						$settings['custom_date_reference'] = '2';
					}
					elseif($old_settings['apdf_custom_date'] == 4){
						$settings['custom_date_reference'] = '3';
					}

					if($old_settings['apdf_custom_time'] == 1 or $old_settings['apdf_custom_time'] == 2){
						$settings['custom_time_reference'] = '1';
					}
					elseif($old_settings['apdf_custom_time'] == 3){
						$settings['custom_time_reference'] = '2';
					}
					elseif($old_settings['apdf_custom_time'] == 4){
						$settings['custom_time_reference'] = '4';
					}
					elseif($old_settings['apdf_custom_time'] == 5){
						$settings['custom_time_reference'] = '3';
					}

					$settings['custom_time_hours'] = $old_settings['apdf_custom_time_4_hours'];
					$settings['custom_time_minutes'] = $old_settings['apdf_custom_time_4_minutes'];

					$settings['custom_date_operation'] = $old_settings['apdf_custom_date_4_operation'];
					$settings['custom_time_operation'] = $old_settings['apdf_custom_time_5_operation'];

					delete_option('automatic_post_date_filler');
				}

/* v1.2
				if($meta['plugin_version'] == '1.1'){

				}
*/
				########################################################
				$meta['admin_notice_update'] = '1';
				$meta['plugin_version'] = $current_version;
				update_option($this->get_variable('option_name_settings'), $settings);
				update_option($this->get_variable('option_name_meta'), $meta);

				$this->set_default_suboptions();
				$this->unset_nondefault_suboptions();
				########################################################

			} //-if
		}
	}

	/**
	 * Adds default suboptions to the settings array if they're missing (executed after plugin updates to make sure that the plugin will work even if some suboptions weren't added during the update)
	 * @return	int	$missing_suboptions Number of missing suboptions
	 */
	private function set_default_suboptions(){
		$settings = $this->get_variable('settings_array');
		$missing_suboptions = 0;

		foreach($this->get_variable('default_settings') as $default_suboption_name => $default_suboption_value){
			if(array_key_exists($default_suboption_name, $settings)){
				continue;
			}
			else{
				$missing_suboptions++;
				$settings[$default_suboption_name] = $default_suboption_value;
			}
		}

		if($missing_suboptions > 0){
			update_option($this->get_variable('option_name_settings'), $settings); //save settings
		}

		return $missing_suboptions;
	}

	/**
	 * Removed non-default suboptions from the settings array (cleans up suboptions after plugin updates)
	 * @return	int	$nondefault_suboptions	Number of non-default suboptions
	 */
	private function unset_nondefault_suboptions(){
		$settings = $this->get_variable('settings_array');
		$nondefault_suboptions = 0;

		foreach($settings as $current_suboption_name => $current_suboption_value){
			if(!array_key_exists($current_suboption_name, $this->get_variable('default_settings'))){
				$nondefault_suboptions++;
				unset($settings[$current_suboption_name]);
			}
		}

		if($nondefault_suboptions > 0){
			update_option($this->get_variable('option_name_settings'), $settings); //save settings
		}

		return $nondefault_suboptions;
	}

	/**
	 * Returns the current plugin version
	 * @return	string
	 */
	public function get_plugin_version(){
		if(!function_exists('get_plugin_data')){
			require_once(ABSPATH .'wp-admin/includes/plugin.php');
		}

		$this_data = get_plugin_data( __FILE__, false, false);
		return $this_data['Version'];
	}

	/**
	 * Registers the options page and its setting
	 */
	public function menu_link(){
		add_options_page('Automatic Post Date Filler', 'Automatic Post Date Filler', 'manage_options', $this->get_variable('options_page_name'), array($this, 'render_options_page'));
		add_action('admin_init', array($this, 'register_settings'));
	}

	/**
	 * Adds the Settings link to plugin action links
	 * @param	array	$links
	 */
	public function plugin_action_links($links){
		$links[] = '<a href="'. admin_url('options-general.php?page='. $this->get_variable('options_page_name')) .'">' . __('Settings') . '</a>';
		return $links;
	}

	/**
	 * Adds various links to meta links
	 * @param	array	$links
	 * @param	string	$file
	 */
	public function plugin_meta_links($links, $file){
		if($file == $this->get_variable('plugin_basename')){
			$links[] = '<a href="https://wordpress.org/support/plugin/automatic-post-date-filler">Support forum</a>';
			$links[] = '<a href="https://wordpress.org/support/view/plugin-reviews/automatic-post-date-filler">Rate the plugin</a>';
		}
		return $links;
	}

	/**
	 * Displays various messages
	 */
	public function plugin_admin_notices(){
		if(current_user_can('manage_options')){
			global $pagenow;

			$meta = $this->get_variable('meta_array');

			if($pagenow == 'options-general.php' and isset($_GET['page']) and $_GET['page'] == $this->get_variable('options_page_name')){
				### messages displayed if GET variables are passed
				if($meta['admin_notice_install'] == 1){
					$meta['admin_notice_install'] = '0'; //hide install notice
					update_option($this->get_variable('option_name_meta'), $meta);

					echo $this->get_variable('message_note') .'<strong>Note:</strong> Now you need to modify the settings for calculating custom date and time which will replace default values after clicking the "Edit" link next to "Publish immediately" in the post editor\'s Publish module. <span class="apdf_help" title="Modified values in the &quot;At a glance section&quot; and inputs in the Publish module will be highlighted with a yellowish color.">i</span>'. $this->get_variable('message_suffix'); //display quick info for new users
				}

				### TODO: each version must have a unique update notice
				if($meta['admin_notice_update'] == 1){
					$meta['admin_notice_update'] = 0; //hide update notice
					update_option($this->get_variable('option_name_meta'), $meta);

					echo $this->get_variable('message_note') .'<strong>What\'s new in APT v'. $meta['plugin_version'] .'?</strong><br />

					<ul class="apdf_custom_list">
						<li>Full post type and post status support for analyzed and affected posts</li>
						<li>Oldest post date/time can now be used as a date/time of reference</li>
						<li>Modified timestamp values in the Publish module are now highlighted with a yellowish color</li>
						<li>Minor appearance changes</li>
					</ul>'. $this->get_variable('message_suffix');

				} //-update notice

				settings_errors($this->get_variable('options_group'), false, true); //third argument must be "true" to hide duplicate messages when the form is submitted
			}


			### install/update messages
			if($meta['admin_notice_install'] == 1){
				echo $this->get_variable('message_updated') .'<strong>Automatic Post Date Filler</strong> has been installed. <a href="'. admin_url('options-general.php?page='. $this->get_variable('options_page_name')) .'">Set up the plugin &raquo;</a>'. $this->get_variable('message_suffix');
			}
			if($meta['admin_notice_update'] == 1){
				echo $this->get_variable('message_updated') .'<strong>Automatic Post Date Filler</strong> has been updated to version <strong>'. $meta['plugin_version'] .'</strong>. <a href="'. admin_url('options-general.php?page='. $this->get_variable('options_page_name')) .'">Find out what\'s new &raquo;</a>'. $this->get_variable('message_suffix');
			}
		} //-if user can manage options
	}

	/**
	 * Registers settings on the options page
	 */
	public function register_settings(){
		register_setting($this->get_variable('options_group'), $this->get_variable('option_name_settings'), array($this, 'validate_setting'));

		add_settings_section('affected_posts', 'Affected posts', array($this, 'affected_posts_callback'), $this->get_variable('options_page_name'));
		add_settings_field('affected_post_types', 'Post types:', array($this, 'affected_post_types_callback'), $this->get_variable('options_page_name'), 'affected_posts');
		add_settings_field('affected_post_statuses', 'Post statuses:', array($this, 'affected_post_statuses_callback'), $this->get_variable('options_page_name'), 'affected_posts');

		add_settings_section('analyzed_posts', 'Analyzed posts', array($this, 'analyzed_posts_callback'), $this->get_variable('options_page_name'));
		add_settings_field('analyzed_post_types', 'Post types:', array($this, 'analyzed_post_types_callback'), $this->get_variable('options_page_name'), 'analyzed_posts');
		add_settings_field('analyzed_post_statuses', 'Post statuses:', array($this, 'analyzed_post_statuses_callback'), $this->get_variable('options_page_name'), 'analyzed_posts');

		add_settings_section('timestamp_calculation', 'Custom date & time calculation', array($this, 'timestamp_calculation_callback'), $this->get_variable('options_page_name'));
		add_settings_field('custom_date', 'Custom date:', array($this, 'custom_date_callback'), $this->get_variable('options_page_name'), 'timestamp_calculation');
		add_settings_field('custom_time', 'Custom time:', array($this, 'custom_time_callback'), $this->get_variable('options_page_name'), 'timestamp_calculation');
	}

	/**
	 * Registers "affected posts" section on the options page
	 */
	public function affected_posts_callback(){
		echo '<p>Only dates/times of posts with specified types and statuses will be modified. <span class="apdf_help" title="Custom date and time will replace default values after clicking the &quot;Edit&quot; link next to &quot;Publish immediately&quot; in the post editor\'s Publish module. Modified values in the &quot;At a glance section&quot; and inputs in the Publish module will be highlighted with a yellowish color. (Use a comma to separate values.)">i</span></p>';
	}

	/**
	 * Registers "analyzed posts" section on the options page
	 */
	public function analyzed_posts_callback(){
		echo '<p>Only dates/times of posts with specified types and statuses will be used as dates/times of reference. <span class="apdf_help" title="Use a comma to separate values.">i</span></p>';
	}

	/**
	 * Registers "timestamp calculation" section on the options page
	 */
	public function timestamp_calculation_callback(){
		echo '<p>Settings used to calculate custom date and time. <span class="apdf_help" title="Date and time are calculated separately, meaning that incremented/decremented custom time won\'t affect the date.">i</span></p>';
	}

	/**
	 * Registers the "post statuses" field on the options page
	 */
	public function affected_post_statuses_callback(){
		$ouptut_array = $this->get_variable('settings_array')['affected_post_statuses'];
		$output_array_sanitized = array_map(array($this, 'sanitize_output_text'), $ouptut_array);
		$output_array_to_string = implode(',', $output_array_sanitized);

		echo '<input type="text" name="'. $this->get_variable('option_name_settings') .'[affected_post_statuses]" value="'. $output_array_to_string .'" maxlength="5000">';
	}

	/**
	 * Registers the "post types" field on the options page
	 */
	public function affected_post_types_callback(){
		$ouptut_array = $this->get_variable('settings_array')['affected_post_types'];
		$output_array_sanitized = array_map(array($this, 'sanitize_output_text'), $ouptut_array);
		$output_array_to_string = implode(',', $output_array_sanitized);

		 echo '<input type="text" name="'. $this->get_variable('option_name_settings') .'[affected_post_types]" value="'. $output_array_to_string .'" maxlength="5000">';
	}

	/**
	 * Registers the "post statuses" field on the options page
	 */
	public function analyzed_post_statuses_callback(){
		$ouptut_array = $this->get_variable('settings_array')['analyzed_post_statuses'];
		$output_array_sanitized = array_map(array($this, 'sanitize_output_text'), $ouptut_array);
		$output_array_to_string = implode(',', $output_array_sanitized);

		echo '<input type="text" name="'. $this->get_variable('option_name_settings') .'[analyzed_post_statuses]" value="'. $output_array_to_string .'" maxlength="5000">';
	}

	/**
	 * Registers the "post types" field on the options page
	 */
	public function analyzed_post_types_callback(){
		$ouptut_array = $this->get_variable('settings_array')['analyzed_post_types'];
		$output_array_sanitized = array_map(array($this, 'sanitize_output_text'), $ouptut_array);
		$output_array_to_string = implode(',', $output_array_sanitized);

		 echo '<input type="text" name="'. $this->get_variable('option_name_settings') .'[analyzed_post_types]" value="'. $output_array_to_string .'" maxlength="5000">';
	}

	/**
	 * Registers the "custom date" field on the options page
	 */
	public function custom_date_callback(){
		$custom_date_reference = $this->get_variable('settings_array')['custom_date_reference'];
		$custom_date_operation = $this->get_variable('settings_array')['custom_date_operation'];
		$custom_date_difference = $this->get_variable('settings_array')['custom_date_difference'];

		$custom_date_checked_1 = checked($custom_date_reference, 1, false);
		$custom_date_checked_2 = checked($custom_date_reference, 2, false);
		$custom_date_checked_3 = checked($custom_date_reference, 3, false);

		$custom_date_operation_1 = selected($custom_date_operation, 1, false);
		$custom_date_operation_2 = selected($custom_date_operation, 2, false);

		echo '<table>
				<tr>
					<th class="apdf_reference_width">Date of reference</th>
					<th>Operation</th>
					<th>Difference</th>
				</tr>
				<tr>
					<td class="apdf_reference_width">
						<input type="radio" name="'. $this->get_variable('option_name_settings') .'[custom_date_reference]" id="custom_date_reference_1" value="1"'. $custom_date_checked_1 .'> <label for="custom_date_reference_1">Current date</label> <span class="apdf_small apdf_gray">('. $this->display_timestamp(1, 'Y-m-d') .')</span><br />
						<input type="radio" name="'. $this->get_variable('option_name_settings') .'[custom_date_reference]" id="custom_date_reference_2" value="2" '. $custom_date_checked_2 .'> <label for="custom_date_reference_2">Most future post date</label> <span class="apdf_small apdf_gray">('. $this->display_timestamp(2, 'Y-m-d') .')</span><br />
						<input type="radio" name="'. $this->get_variable('option_name_settings') .'[custom_date_reference]" id="custom_date_reference_3" value="3" '. $custom_date_checked_3 .'> <label for="custom_date_reference_3">Oldest post date</label> <span class="apdf_small apdf_gray">('. $this->display_timestamp(3, 'Y-m-d') .')</span>
					</td>
					<td>
						<select name="'. $this->get_variable('option_name_settings') .'[custom_date_operation]">
							<option value="1" '. $custom_date_operation_1 .'>+</option>
							<option value="2" '. $custom_date_operation_2 .'>-</option>
						</select>
					</td>
					<td>
						<input type="text" class="apdf_number" name="'. $this->get_variable('option_name_settings') .'[custom_date_difference]" value="'. $custom_date_difference .'" maxlength="255"> days
					</td>
				</tr>
			</table>';
	}

	/**
	 * Registers the "custom time" field on the options page
	 */
	public function custom_time_callback(){
		$custom_time_reference = $this->get_variable('settings_array')['custom_time_reference'];
		$custom_time_operation = $this->get_variable('settings_array')['custom_time_operation'];
		$custom_time_difference = $this->get_variable('settings_array')['custom_time_difference'];
		$custom_time_hours = $this->get_variable('settings_array')['custom_time_hours'];
		$custom_time_minutes = $this->get_variable('settings_array')['custom_time_minutes'];

		$custom_time_checked_1 = checked($custom_time_reference, 1, false);
		$custom_time_checked_2 = checked($custom_time_reference, 2, false);
		$custom_time_checked_3 = checked($custom_time_reference, 3, false);
		$custom_time_checked_4 = checked($custom_time_reference, 4, false);

		$custom_time_operation_1 = selected($custom_time_operation, 1, false);
		$custom_time_operation_2 = selected($custom_time_operation, 2, false);

		echo '<table>
				<tr>
					<th class="apdf_reference_width">Time of reference</th>
					<th>Operation</th>
					<th>Difference</th>
				</tr>
				<tr>
					<td class="apdf_reference_width">
						<input type="radio" name="'. $this->get_variable('option_name_settings') .'[custom_time_reference]" id="custom_time_reference_1" value="1"'. $custom_time_checked_1 .'> <label for="custom_time_reference_1">Current time</label> <span class="apdf_small apdf_gray">('. $this->display_timestamp(1, 'H:i') .')</span><br />
						<input type="radio" name="'. $this->get_variable('option_name_settings') .'[custom_time_reference]" id="custom_time_reference_2" value="2" '. $custom_time_checked_2 .'> <label for="custom_time_reference_2">Most future post time</label> <span class="apdf_small apdf_gray">('. $this->display_timestamp(2, 'H:i') .')</span><br />
						<input type="radio" name="'. $this->get_variable('option_name_settings') .'[custom_time_reference]" id="custom_time_reference_3" value="3" '. $custom_time_checked_3 .'> <label for="custom_time_reference_3">Oldest post time</label> <span class="apdf_small apdf_gray">('. $this->display_timestamp(3, 'H:i') .')</span><br />
						<input type="radio" name="'. $this->get_variable('option_name_settings') .'[custom_time_reference]" id="custom_time_reference_4" value="4" '. $custom_time_checked_4 .'> <label for="custom_time_reference_4">Specific time: </label> <input type="text" class="apdf_number" name="'. $this->get_variable('option_name_settings') .'[custom_time_hours]" value="'. $custom_time_hours .'" maxlength="2"> : <input type="text" class="apdf_number" name="'. $this->get_variable('option_name_settings') .'[custom_time_minutes]" value="'. $custom_time_minutes .'" maxlength="2"> <span class="apdf_help" title="If this option is selected, the time won\'t be modified further using the &quot;Operation&quot; and &quot;Difference&quot; variables.">i</span>
					</td>
					<td>
						<select name="'. $this->get_variable('option_name_settings') .'[custom_time_operation]">
							<option value="1" '. $custom_time_operation_1 .'>+</option>
							<option value="2" '. $custom_time_operation_2 .'>-</option>
						</select>
					</td>
					<td>
						<input type="text" class="apdf_number" name="'. $this->get_variable('option_name_settings') .'[custom_time_difference]" value="'. $custom_time_difference .'" maxlength="255"> minutes
					</td>
				</tr>
			</table>';
	}

	/**
	 * Sanitizes inputs from the form
	 * @param	array	$input
	 */
	public function validate_setting($input){
		$settings = $this->get_variable('settings_array');

		$validated_text_fields['affected_post_statuses'] = $this->validate_text($input['affected_post_statuses'], 2);
		$validated_text_fields['affected_post_types'] = $this->validate_text($input['affected_post_types'], 1);
		$validated_text_fields['analyzed_post_statuses'] = $this->validate_text($input['analyzed_post_statuses'], 2);
		$validated_text_fields['analyzed_post_types'] = $this->validate_text($input['analyzed_post_types'], 1);

		### save $input only if it's valid, otherwise resave old settings
		if($validated_text_fields['affected_post_statuses'] !== false){
			$settings['affected_post_statuses'] = $validated_text_fields['affected_post_statuses'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'post_statuses_invalid', 'Submitted affected post statuses were invalid; previously used value was restored.');
		}

		if($validated_text_fields['affected_post_types'] !== false){
			$settings['affected_post_types'] = $validated_text_fields['affected_post_types'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'post_types_invalid', 'Submitted affected post types were invalid; previously used value was restored.');
		}

		if($validated_text_fields['analyzed_post_statuses'] !== false){
			$settings['analyzed_post_statuses'] = $validated_text_fields['analyzed_post_statuses'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'post_statuses_invalid', 'Submitted analyzed post statuses were invalid; previously used value was restored.');
		}

		if($validated_text_fields['analyzed_post_types'] !== false){
			$settings['analyzed_post_types'] = $validated_text_fields['analyzed_post_types'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'post_types_invalid', 'Submitted analyzed post types were invalid; previously used value was restored.');
		}


		$validated_radios['custom_date_reference'] = $this->validate_radio($input['custom_date_reference']);
		$validated_selects['custom_date_operation'] = $this->validate_select($input['custom_date_operation']);
		$validated_numbers['custom_date_difference'] = $this->validate_number($input['custom_date_difference'], 1);

		### save $input only if it's valid, otherwise resave old settings
		if($validated_radios['custom_date_reference'] !== false){
			$settings['custom_date_reference'] = $validated_radios['custom_date_reference'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'invalid_radio', 'Submitted radio input was invalid; previously used value was restored.');
		}
		if($validated_selects['custom_date_operation'] !== false){
			$settings['custom_date_operation'] = $validated_selects['custom_date_operation'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'invalid_radio', 'Submitted select input was invalid; previously used value was restored.');
		}
		if($validated_numbers['custom_date_difference'] !== false){
			$settings['custom_date_difference'] = $validated_numbers['custom_date_difference'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'non_negative_integer', 'Submitted date difference wasn\'t a non-negative integer; previously used value was restored.');
		}

		$validated_radios['custom_time_reference'] = $this->validate_radio($input['custom_time_reference']);
		$validated_selects['custom_time_operation'] = $this->validate_select($input['custom_time_operation']);
		$validated_numbers['custom_time_difference'] = $this->validate_number($input['custom_time_difference'], 1);
		$validated_numbers['custom_time_hours'] = $this->validate_number($input['custom_time_hours'], 2);
		$validated_numbers['custom_time_minutes'] = $this->validate_number($input['custom_time_minutes'], 3);

		### save $input only if it's valid, otherwise resave old settings
		if($validated_radios['custom_time_reference'] !== false){
			$settings['custom_time_reference'] = $validated_radios['custom_time_reference'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'invalid_radio', 'Submitted radio input was invalid; previously used value was restored.');
		}
		if($validated_selects['custom_time_operation'] !== false){
			$settings['custom_time_operation'] = $validated_selects['custom_time_operation'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'invalid_radio', 'Submitted select input was invalid; previously used value was restored.');
		}
		if($validated_numbers['custom_time_difference'] !== false){
			$settings['custom_time_difference'] = $validated_numbers['custom_time_difference'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'non_negative_integer', 'Submitted time difference wasn\'t a non-negative integer; previously used value was restored.');
		}
		if($validated_numbers['custom_time_hours'] !== false){
			$settings['custom_time_hours'] = $validated_numbers['custom_time_hours'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'non_negative_integer', 'Submitted specific time (hours) was invalid; previously used value was restored.');
		}
		if($validated_numbers['custom_time_minutes'] !== false){
			$settings['custom_time_minutes'] = $validated_numbers['custom_time_minutes'];
		}
		else{
			add_settings_error($this->get_variable('options_group'), 'non_negative_integer', 'Submitted specific time (minutes) was invalid; previously used value was restored.');
		}

		add_settings_error($this->get_variable('options_group'), 'saved', 'Settings saved.', 'updated');
		return $settings;
	}

	/**
	 * Ensures text fields are valid
	 * @param	string		$input
	 * @param	string		$type	(1: post types | 2: post statuses)
	 * @return	string|bool
	 */
	private function validate_text($input, $type){
		$input = $this->sanitize_text($input);
		$invalid_items = array();

		if($type == 1){
			$error_code = 'invalid_post_types';
			$error_type = 'post types';
		}
		elseif($type == 2){
			$error_code = 'invalid_post_statuses';
			$error_type = 'post statuses';
		}

		if(!empty($input)){
			$array = explode(',', $input);

			### remove unregistered items
			foreach($array as $key => $value){
				if($type == 1){
					if(!post_type_exists($value)){
						array_push($invalid_items, $array[$key]);
						unset($array[$key]);
					}
				}
				elseif($type == 2){
					if(get_post_status_object($value) === null){
						array_push($invalid_items, $array[$key]);
						unset($array[$key]);
					}
				}
			}

			### display errors if some items were removed
			if(!empty($invalid_items)){
				$output_array_sanitized = array_map(array($this, 'sanitize_output_text'), $invalid_items);
				$output_array_to_string = implode('", "', $output_array_sanitized);

				add_settings_error($this->get_variable('options_group'), $error_code, 'Unregistered '. $error_type .' "'. $output_array_to_string .'" weren\'t saved.', 'apdf_warning');
			}

			if(!empty($array)){
				return $array;
			}
			else{
				return false;
			}
		}
		else{
			return false;
		}
	}

	/**
	 * Ensures number fields are valid
	 * @param	string		$input
	 * @param	string		$type	(1: non-negative integer| 2: hours | 3: minutes)
	 * @return	string|bool
	 */
	private function validate_number($input, $type){
		if($type == 1){
			$input = $this->sanitize_number($input, true);

			if(preg_match('/^(0|[1-9][0-9]*){1}$/', $input)){
				return $input;
			}
		}
		elseif($type == 2){
			$input = $this->sanitize_number($input, false, true);

			if(preg_match('/^[01][0-9]|2[0-3]$/', $input)){
				return $input;
			}
		}
		elseif($type == 3){
			$input = $this->sanitize_number($input, false, true);

			if(preg_match('/^[0-5][0-9]$/', $input)){
				return $input;
			}
		}

		return false;
	}

	/**
	 * Ensures submitted radio values are valid
	 * @param	string		$input
	 * @return	string|bool
	 */
	private function validate_radio($input){
		if(preg_match('/^([1-9])$/', $input)){
			return $input;
		}

		return false;
	}

	/**
	 * Ensures submitted select values are valid
	 * @param	string		$input
	 * @return	string|bool
	 */
	private function validate_select($input){
		if(preg_match('/^([1-2])$/', $input)){
			return $input;
		}

		return false;
	}

	/**
	 * Sanitizes output
	 * @param	string	$input
	 * @return	string
	 */
	private function sanitize_output_text($input){
		return esc_html($input);
	}

	/**
	 * Sanitizes input text
	 * @param	string	$input
	 * @return	string	$output
	 */
	private function sanitize_text($input){
		$output = preg_replace(array('/\s+/', '/[\t\n]/'), '', $input); //removing whitespace characters
		$output = preg_replace('/'. preg_quote(',', '/') .'{2,}/', ',', $output); //replacing multiple separators with one
		$output = preg_replace('/('. preg_quote(',') .' )/', ',', $output); //replacing separators with extra spaces with one separator
		$output = preg_replace('/( '. preg_quote(',') .')/', ',', $output); //replacing separators with extra spaces with one separator
		$output = trim($output, ',');
		return $output;
	}

	/**
	 * Sanitizes numbers
	 * @param	string	$input
	 * @param	bool	$remove_zeros	Remove unnecessary zeros
	 * @param	bool	$add_zeros		Add necessary zeros
	 * @return	string	$output
	 */
	private function sanitize_number($input, $remove_zeros = false, $add_zeros = false){
		$output = trim($input);

		if($remove_zeros === true){
			if(trim($output, '0') == ''){
				$output = '0';
			}
			else{
				$output = preg_replace('/^0+/', '', $output);
			}
		}

		if($add_zeros === true){
			if(strlen($output) == 1 and $output != '0'){
				$output = '0'. $output;
			}
		}

		return $output;
	}

	/**
	 * Loads CSS on the options page
	 */
	public function load_options_page_css(){
		wp_enqueue_style('style', $this->get_variable('plugin_url') .'assets/style.css');
	}

	/**
	 * Loads everything that's needed for date modification
	 */
	public function calculation_init(){
		global $post;
		$settings = $this->get_variable('settings_array');

		if(in_array(get_post_type($post->ID), $settings['affected_post_types']) and in_array(get_post_status($post->ID), $settings['affected_post_statuses'])){	
			add_action('admin_enqueue_scripts' , array($this, 'load_jquery'));
			add_action('admin_print_footer_scripts', array($this, 'insert_scripts'));
		}
	}

	/**
	 * Loads jQuery
	 */
	public function load_jquery(){
		wp_enqueue_script('jQuery');
	}

	/**
	 * Inserts new default values when a users clicks on the "Edit" link + highlights input backgrounds
	 */
	public function insert_scripts(){
		$calculated_timestamp = $this->calculate_timestamp();

		if(!empty($calculated_timestamp)){
			if(array_key_exists('month', $calculated_timestamp)){
				$css_items['month'] = '#mm';
				$jquery_items['month'] = 'jQuery("#mm").val("'. $calculated_timestamp['month'] .'");';
			}
			if(array_key_exists('day', $calculated_timestamp)){
				$css_items['day'] = '[name=jj]';
				$jquery_items['day'] = 'jQuery("input[name=jj]").val("'. $calculated_timestamp['day'] .'");';
			}
			if(array_key_exists('year', $calculated_timestamp)){
				$css_items['year'] = '[name=aa]';
				$jquery_items['year'] = 'jQuery("input[name=aa]").val("'. $calculated_timestamp['year'] .'");';
			}
			if(array_key_exists('hour', $calculated_timestamp)){
				$css_items['hour'] = '[name=hh]';
				$jquery_items['hour'] = 'jQuery("input[name=hh]").val("'. $calculated_timestamp['hour'] .'");';
			}
			if(array_key_exists('minute', $calculated_timestamp)){
				$css_items['minute'] = '[name=mn]';
				$jquery_items['minute'] = 'jQuery("input[name=mn]").val("'. $calculated_timestamp['minute'] .'");';
			}

			$css_items_string = implode(', ', $css_items);
			$jquery_string = implode(' ', $jquery_items);

			?>
				<!-- Automatic Post Date Filler -->
				<script type="text/javascript">
					jQuery(document).ready(function(){
						jQuery("a[href=#edit_timestamp].edit-timestamp").click(function(){<?php echo $jquery_string; ?>});
					});
				</script>

				<style type="text/css">
					#timestampdiv <?php echo $css_items_string; ?> {background: #fcf084 !important;}
				</style>
				<!-- Automatic Post Date Filler -->
			<?php
		}
	}

	/**
	 * Displays the timestamp in a provided format
	 * @param	int		$type	(1: current | 2: future | 3: oldest)
	 * @param	string	$format
	 * @return	string
	 */
	private function display_timestamp($type, $format){
		$timestamp = $this->get_post_timestamp($type);

		if($timestamp != false){
			return get_date_from_gmt(date('Y-m-d H:i:s', $timestamp), $format);
		}
		else{
			return 'n/a';
		}
	}

	/**
	 * Returns post timestamp
	 * @param	int		$type		(1: current | 2: future | 3: oldest)
	 * @return	int		$timestamp
	 */
	private function get_post_timestamp($type){
		if($type == 1){
			return current_time('timestamp');
		}

		global $wpdb;
		$settings = $this->get_variable('settings_array');
		$analyzed_post_types_string = '(post_type = "'. implode('" OR post_type = "', $settings['analyzed_post_types']) .'")';
		$analyzed_post_statuses_string = '(post_status = "'. implode('" OR post_status = "', $settings['analyzed_post_statuses']) .'")';
		$analyzed_post_statuses_sql = 'SELECT ID FROM '. $wpdb->posts .' WHERE '. $analyzed_post_types_string .' AND '. $analyzed_post_statuses_string .' ORDER BY post_date';

		$reference_timestamp = false;

		if($type == 2){
			$most_future_post_id = $wpdb->get_var($analyzed_post_statuses_sql .' DESC LIMIT 1');

			if(!empty($most_future_post_id)){
				$reference_timestamp = get_the_time('U', $most_future_post_id);
			}
		}
		elseif($type == 3){
			$oldest_post_id = $wpdb->get_var($analyzed_post_statuses_sql . ' ASC LIMIT 1'); 

			if(!empty($oldest_post_id)){ 
				$reference_timestamp = get_the_time('U', $oldest_post_id);
			}
		}

		return $reference_timestamp;
	}

	/**
	 * Calculates new date and time
	 */
	private function calculate_timestamp(){
		$settings = $this->get_variable('settings_array');

		$difference_seconds = 0;
		$reference_date = false;
		$reference_time = $reference_date;

		### timestamp for the current date
		if($settings['custom_date_reference'] == 1 and $settings['custom_date_difference'] > 0){
			$reference_date = $this->get_post_timestamp(1);
		}

		if($settings['custom_time_reference'] == 1 and $settings['custom_time_difference'] > 0){
			$reference_time = $this->get_post_timestamp(1);
		}

		### timestamp for the most future date
		if($settings['custom_time_reference'] == 2 or $settings['custom_date_reference'] == 2){
			$reference_timestamp = $this->get_post_timestamp(2);

			if($reference_timestamp !== false){
				if($settings['custom_time_reference'] == 2){
					$reference_time = $this->get_post_timestamp(2);
				}
				if($settings['custom_date_reference'] == 2){
					$reference_date = $this->get_post_timestamp(2);
				}
			}
		}

		### timestamp for the oldest date
		if($settings['custom_time_reference'] == 3 or $settings['custom_date_reference'] == 3){
			$reference_timestamp = $this->get_post_timestamp(3);

			if($reference_timestamp !== false){
				if($settings['custom_time_reference'] == 3){
					$reference_time = $this->get_post_timestamp(3);
				}
				if($settings['custom_date_reference'] == 3){
					$reference_date = $this->get_post_timestamp(3);
				}
			}
		}

		### DATE CALCULATION
		if($reference_date !== false){
			if($settings['custom_date_operation'] == 1){
				$operation_date = 1;
			}
			elseif($settings['custom_date_operation'] == 2){
				$operation_date = -1;
			}

			$date_difference = ($settings['custom_date_difference'] * 60 * 60 * 24) * $operation_date;
			$datestamp = $reference_date + $date_difference;

			if($settings['custom_date_reference'] != 1){
				$calculated_timestamp['month'] = date('m', $datestamp);
				$calculated_timestamp['day'] = date('d', $datestamp);
				$calculated_timestamp['year'] = date('Y', $datestamp);
			}
		}

		if($settings['custom_date_reference'] == 1 and $settings['custom_date_difference'] > 0){
			$datestamp = $reference_date + $date_difference;

			$calculated_timestamp['month'] = date('m', $datestamp);
			$calculated_timestamp['day'] = date('d', $datestamp);
			$calculated_timestamp['year'] = date('Y', $datestamp);
		}


		### TIME CALCULATION
		if($reference_time !== false){
			if($settings['custom_time_operation'] == 1){
				$operation_time = 1;
			}
			elseif($settings['custom_time_operation'] == 2){
				$operation_time = -1;
			}

			$time_difference = ($settings['custom_time_difference'] * 60) * $operation_time;
			$timestamp = $reference_time + $time_difference;

			if($settings['custom_time_reference'] == 2 or $settings['custom_time_reference'] == 3 or ($settings['custom_time_reference'] == 1 and $settings['custom_time_difference'] > 0)){
				$calculated_timestamp['hour'] = date('H', $timestamp);
				$calculated_timestamp['minute'] = date('i', $timestamp);
			}
		}

		if($settings['custom_time_reference'] == 4){ //specific time
			$calculated_timestamp['hour'] = $settings['custom_time_hours'];
			$calculated_timestamp['minute'] = $settings['custom_time_minutes'];
		}


		if(!empty($calculated_timestamp)){
			return $calculated_timestamp;
		}
		else{
			return false;
		}
	}

	/**
	 * The options page
	 */
	public function render_options_page(){
		$settings = $this->get_variable('settings_array');
		$invalid_nonce_message = 'Sorry, your nonce did not verify, your request couldn\'t be executed. Please try again.';
		$calculated_timestamp = $this->calculate_timestamp();
	?>

	<div class="wrap">
		<h2>Automatic Post Date Filler</h2>

		<div id="poststuff" class="metabox-holder has-right-sidebar">
			<div class="inner-sidebar">
				<div id="side-sortables" class="meta-box-sortabless" style="position:relative;">

					<!-- postbox -->
					<div class="postbox">
						<h3 class="hndle"><span>At a glance</span></h3>
						<div class="inside">
							<ul>
								<?php
									if($calculated_timestamp === false){
								?>
										<li><strong>Status:</strong> <span class="apdf_red apdf_right">Date/time filling disabled</span></li>
								<?php
									}
									else{
								?>
										<li><strong>Status:</strong> <span class="apdf_green apdf_right">Date/time filling enabled</span></li>
										<li>Calculated date: 
											<span class="apdf_right">
												<?php if(isset($calculated_timestamp['year']) and isset($calculated_timestamp['month']) and isset($calculated_timestamp['day'])){echo '<span class="apdf_highlighted" title="This value will replace the default date in the post editor\'s Publish module.">'. $calculated_timestamp['year'] .'-'. $calculated_timestamp['month'] .'-'. $calculated_timestamp['day'] .'</span>';}else{echo date('Y-m-d', current_time('timestamp'));} ?>
												<?php if(isset($calculated_timestamp['hour']) and isset($calculated_timestamp['minute'])){echo '<span class="apdf_highlighted" title="This value will replace the default time in the post editor\'s Publish module.">'. $calculated_timestamp['hour'] .':'. $calculated_timestamp['minute'] .'</span>';}else{echo date('H:i', current_time('timestamp'));} ?>
											</span>
										</li>
								<?php
									}
								?>
							</ul>
						</div>
					</div>
					<!-- //-postbox -->

					<!-- postbox -->
					<div class="postbox">
						<h3 class="hndle"><span>Links</span></h3>
						<div class="inside">
							<ul>
								<li><a href="https://wordpress.org/plugins/automatic-post-date-filler/"><span class="apdf_icon wp"></span>Plugin homepage</a></li>
								<li><a href="https://wordpress.org/support/plugin/automatic-post-date-filler"><span class="apdf_icon wp"></span>Support forum</a></li>
								<li><a href="https://wordpress.org/support/view/plugin-reviews/automatic-post-date-filler"><span class="apdf_icon apdf_rate"></span>Rate the plugin</a></li>
							</ul>
						</div>
					</div>
					<!-- //-postbox -->
			
				</div><!-- //-side-sortables -->
			</div><!-- //-inner-sidebar -->

			<div class="has-sidebar sm-padded">
				<div id="post-body-content" class="has-sidebar-content">
					<div class="meta-box-sortabless">

						<!-- postbox -->
						<form action="options.php" method="post">
							<?php settings_fields($this->get_variable('options_group')); ?>
							<?php do_settings_sections($this->get_variable('options_page_name')); ?>
							<?php submit_button(); ?>
						</form>
						<!-- //-postbox -->

					</div>
				</div>
			</div>

		</div>
	</div>

	<?php
	} //-options page
} //-class

new Automatic_Post_Date_Filler();
