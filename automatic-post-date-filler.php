<?php
/*
Plugin Name: Automatic Post Date Filler
Plugin URI: http://wordpress.org/plugins/automatic-post-date-filler
Description: This plugin automatically sets custom date and time when scheduling a post.
Version: 1.0
Author: Devtard
Author URI: http://devtard.com
License: GPLv2 or later
*/

/*
	Copyright (C) 2013 Devtard (gmail.com ID: devtard)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License along
	with this program; if not, write to the Free Software Foundation, Inc.,
	51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

## Bug reports are appreciated: http://wordpress.org/support/plugin/automatic-post-date-filler

## =========================================================================
## ### BASIC DECLARATIONS
## =========================================================================

$apdf_plugin_url = WP_PLUGIN_URL . "/" . basename(dirname(__FILE__)) . "/";
$apdf_plugin_basename = plugin_basename(__FILE__); //automatic-post-date-filler/automatic-post-date-filler.php

$apdf_message_html_prefix_updated = '<div id="message" class="updated"><p>';
$apdf_message_html_prefix_error = '<div id="message" class="error"><p>';
$apdf_message_html_suffix = '</p></div>';

## ===================================
## ### GET PLUGIN VERSION
## ===================================

function apdf_get_plugin_version(){ //return plugin version
	//this must not be removed or the function get_plugin_data won't work
	if(!function_exists('get_plugin_data')){
		require_once(ABSPATH .'wp-admin/includes/plugin.php');
	}

	$apdf_plugin_data = get_plugin_data( __FILE__, FALSE, FALSE);
	$apdf_plugin_version = $apdf_plugin_data['Version'];
	return $apdf_plugin_version;
}

## ===================================
## ### INSTALL FUNCTION
## ===================================

function apdf_install_plugin(){ //runs only after MANUAL activation
	if(get_option('automatic_post_date_filler') == FALSE){ //create the option only if it isn't defined yet
		$apdf_default_settings = array(
			'apdf_plugin_version' => apdf_get_plugin_version(), //for future updates of the plugin
			'apdf_admin_notice_install' => '1', //option for displaying installation notice
			'apdf_admin_notice_update' => '0', //option for displaying update notice
			'apdf_admin_notice_prompt' => '1', //option for displaying a notice asking the user to do stuff (plugin rating, sharing the plugin etc.)
			'apdf_stats_install_date' => time(),

			'apdf_custom_time' => '4',
			'apdf_custom_time_2_extra_minutes' => '120',
			'apdf_custom_time_3_extra_minutes' => '120',
			'apdf_custom_time_4_hours' => '00',
			'apdf_custom_time_4_minutes' => '00',

			'apdf_custom_date' => '3',
			'apdf_custom_date_2_extra_days' => '2',
			'apdf_custom_date_3_extra_days' => '2',

			'apdf_post_statuses' => 'auto-draft,draft,pending', //we are not using "future", "publish" (it makes no sense to change date of already scheduled/published post), "trash" and "inherit"
			'apdf_timestamp_calculation' => '1'
		);

		add_option('automatic_post_date_filler', $apdf_default_settings, '', 'no'); //single option for saving default settings
	}//-if the option doesn't exist
}

## ===================================
## ### UPDATE FUNCTION
## ===================================

/* TODO v1.1
function apdf_update_plugin(){ //runs when all plugins are loaded
	$apdf_settings = get_option('automatic_post_date_filler');

	if(current_user_can('manage_options')){
		$apdf_current_version = apdf_get_plugin_version();

		if(($apdf_settings['apdf_plugin_version'] != FALSE) AND ($apdf_settings['apdf_plugin_version'] <> $apdf_current_version)){ //check if the saved version is not equal to the current version -- the FALSE check is there to determine if the option exists
			#### now comes everything what must be changed in the new version

			if($apdf_settings['apdf_plugin_version'] == '1.0' AND $apdf_current_version == '1.1'){

			}

			#### -/changes
			#### update version and show the update notice

			//modify settings
			$apdf_settings['apdf_admin_notice_update'] = 1; //we want to show the admin notice after upgrading
			$apdf_settings['apdf_plugin_version'] = $apdf_current_version; //update plugin version in DB

			//update settings
			update_option('automatic_post_date_filler', $apdf_settings); 

		}//-if different versions
	}//if current user can
}
*/

## ===================================
## ### UNINSTALL FUNCTION
## ===================================

function apdf_uninstall_plugin(){ //runs after uninstalling of the plugin
	delete_option('automatic_post_date_filler');
}

## =========================================================================
## ### HOOKS
## =========================================================================

if(is_admin()){ //these functions will be executed only if the admin panel is being displayed (for performance reasons)
	add_action('admin_menu', 'apdf_menu_link');
	add_action('admin_notices', 'apdf_plugin_admin_notices', 20); //check for admin notices

	//saving resources to avoid performance issues

	if($GLOBALS['pagenow'] == 'plugins.php'){ //check if the user is on page plugins.php
		add_filter('plugin_action_links', 'apdf_plugin_action_links', 10, 2);
		add_filter('plugin_row_meta', 'apdf_plugin_meta_links', 10, 2);
	}

	if(in_array($GLOBALS['pagenow'], array('plugins.php', 'update-core.php', 'update.php'))){ //check if the user is on pages update-core.php, plugins.php or update.php
		//add_action('plugins_loaded', 'apdf_update_plugin'); TODO v1.1
		register_activation_hook(__FILE__, 'apdf_install_plugin');
		register_uninstall_hook(__FILE__, 'apdf_uninstall_plugin');
	}

	if($GLOBALS['pagenow'] == 'options-general.php' AND isset($_GET['page']) AND $_GET['page'] == 'automatic-post-date-filler'){ //check if the user is on page options-general.php?page=automatic-post-tagger
		add_action('admin_enqueue_scripts', 'apdf_load_options_page_css'); //load js and css on the options page
	}

	if(in_array($GLOBALS['pagenow'], array('post.php', 'post-new.php'))){
		add_action('admin_enqueue_scripts' , 'apdf_load_jquery');
		add_action('admin_print_footer_scripts', 'apdf_insert_javascript');
	}
}

## ===================================
## ### ACTION + META LINKS
## ===================================

function apdf_plugin_action_links($links, $file){
	global $apdf_plugin_basename;

	if($file == $apdf_plugin_basename){
 		$apdf_settings_link = '<a href="'. admin_url('options-general.php?page=automatic-post-date-filler') .'">' . __('Settings') . '</a>';
		$links = array_merge($links, array($apdf_settings_link)); 
	}
 	return $links;
}

function apdf_plugin_meta_links($links, $file){
	global $apdf_plugin_basename;

	if($file == $apdf_plugin_basename){
		$links[] = '<a href="http://wordpress.org/plugins/automatic-post-date-filler/faq">FAQ</a>';
		$links[] = '<a href="http://wordpress.org/support/plugin/automatic-post-date-filler">Support</a>';
		$links[] = '<a href="http://devtard.com/donate">Donate</a>';
	}
	return $links;
}

## ===================================
## ### MENU LINK
## ===================================

function apdf_menu_link(){
	$page = add_options_page('Automatic Post Date Filler', 'Automatic Post Date Filler', 'manage_options', 'automatic-post-date-filler', 'apdf_options_page');
}

## ===================================
## ### ADMIN NOTICES
## ===================================

function apdf_plugin_admin_notices(){
	if(current_user_can('manage_options')){

		global $apdf_message_html_prefix_updated,
		$apdf_message_html_suffix;

		$apdf_settings = get_option('automatic_post_date_filler');

		if($GLOBALS['pagenow'] == 'options-general.php' AND $_GET['page'] == 'automatic-post-date-filler'){ //check if the user is on page options-general.php?page=automatic-post-date-filler
			## ===================================
			## ### ACTIONS BASED ON GET DATA
			## ===================================
			//must be before other checks
			//nonces are used for better security
			//isset checks must be there or the nonce check will cause the page to die

			if(isset($_GET['n']) AND $_GET['n'] == 1 AND check_admin_referer('apdf_admin_notice_install_nonce')){
				$apdf_settings['apdf_admin_notice_install'] = 0; //hide install notice
				update_option('automatic_post_date_filler', $apdf_settings); //save settings

				echo $apdf_message_html_prefix_updated .'<b>Note:</b> The customized date and time of scheduled posts will be automatically inserted after clicking the "Edit" link next to "Publish immediately" in the Publish module.
				<br />You are still free to modify the value to whatever you want afterwards.'. $apdf_message_html_suffix; //display quick info for beginners
			}

			/* TODO v1.1: each version must have a unique notice
			if(isset($_GET['n']) AND $_GET['n'] == 2 AND check_admin_referer('apdf_admin_notice_update_nonce')){
				$apdf_settings['apdf_admin_notice_update'] = 0; //hide update notice
				update_option('automatic_post_date_filler', $apdf_settings); //save settings

				echo $apdf_message_html_prefix_updated .'<b>What\'s new in APDF v1.5?</b>

				If something won\'t work, reinstall the plugin and post a new bug report on the <a href="http://wordpress.org/support/plugin/automatic-post-date-filler">support forum</a>, please. -- <em>Devtard</em>'. $apdf_message_html_suffix; //show new functions (should be same as the upgrade notice in readme.txt)
			}
			*/


			//prompt notice dismissing
			if(isset($_GET['n']) AND $_GET['n'] == 3 AND check_admin_referer('apdf_admin_notice_prompt_3_nonce')){
				$apdf_settings['apdf_admin_notice_prompt'] = 0; //hide prompt notice
				update_option('automatic_post_date_filler', $apdf_settings); //save settings

			}
			if(isset($_GET['n']) AND $_GET['n'] == 4 AND check_admin_referer('apdf_admin_notice_prompt_4_nonce')){
				$apdf_settings['apdf_admin_notice_prompt'] = 0; //hide prompt notice and display another notice (below)
				update_option('automatic_post_date_filler', $apdf_settings); //save settings

				echo $apdf_message_html_prefix_updated .'<b>Thank you.</b>'. $apdf_message_html_suffix; //show "thank you" message
			}
		}//-options page check


		## ===================================
		## ### ACTIONS BASED ON DATABASE DATA
		## ===================================
		if($apdf_settings['apdf_admin_notice_install'] == 1){ //show link to the setting page after installing
			echo $apdf_message_html_prefix_updated .'<b>Automatic Post Date Filler</b> has been installed. <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-date-filler&n=1'), 'apdf_admin_notice_install_nonce') .'">Set up the plugin &raquo;</a>'. $apdf_message_html_suffix;
		}

		/* TODO v1.1
		if($apdf_settings['apdf_admin_notice_update'] == 1){ //show link to the setting page after updating
			echo $apdf_message_html_prefix_updated .'<b>Automatic Post Date Filler</b> has been updated to version <b>'. $apdf_settings['apdf_plugin_version'] .'</b>. <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-date-filler&n=2'), 'apdf_admin_notice_update_nonce') .'">Find out what\'s new &raquo;</a>'. $apdf_message_html_suffix;
		}
		*/

		//prompt notice
		if($apdf_settings['apdf_admin_notice_prompt'] == 1){ //determine whether the prompt notice was not dismissed yet
			if(((time() - $apdf_settings['apdf_stats_install_date']) >= 2629743) AND ($apdf_settings['apdf_admin_notice_update'] == 0) AND !isset($_GET['n'])){ //show prompt notice ONLY after a month (2629743 seconds), if the update notice isn't currently displayed and if any other admin notice isn't active

				//the style="float:right;" MUST NOT be deleted, since the message can be displayed anywhere where APDF's CSS styles aren't loaded!
				echo $apdf_message_html_prefix_updated .'
					<b>Thanks for using <acronym title="Automatic Post Date Filler">APDF</acronym>!</b> You\'ve installed this plugin over a month ago. If you are satisfied with the results,
					could you please <a href="http://wordpress.org/support/view/plugin-reviews/automatic-post-date-filler" target="_blank">rate the plugin</a> and share it with others?
					Positive feedback is a good motivation for further development. <em>-- Devtard</em>

					<span style="float:right;"><small>
					<a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-date-filler&n=4'), 'apdf_admin_notice_prompt_4_nonce') .'" title="Hide this notification"><b>OK, but I\'ve done that already!</b></a>
					| <a href="'. wp_nonce_url(admin_url('options-general.php?page=automatic-post-date-filler&n=3'), 'apdf_admin_notice_prompt_3_nonce') .'" title="Hide this notification">Don\'t bug me anymore!</a>
					</small></span>
				'. $apdf_message_html_suffix;
			}//-if time + tag count check
		}//-if prompt

	}//-if can manage options check
}

## ===================================
## ### TIME CALCULATION
## ===================================

function apdf_calculate_timestamp(){ //this calculates new date and time
	global $wpdb;
//	$wpdb->show_errors(); //for debugging

	$apdf_settings = get_option('automatic_post_date_filler');
	$apdf_local_time = current_time('timestamp', 0);
	$apdf_timestap_increment = 0;
	$apdf_calculated_timestamp = array();

	//find a timestamp for a post scheduled in the furthest future
	if($apdf_settings['apdf_custom_time'] == 3 OR $apdf_settings['apdf_custom_date'] == 3){
		$apdf_scheduled_post_id = $wpdb->get_var('SELECT ID FROM '. $wpdb->posts .' WHERE post_status = "future" ORDER BY post_date DESC LIMIT 1');

		if(empty($apdf_scheduled_post_id)){ //if no post id was found, use current timestamp
			$apdf_furthest_scheduled_post_timestamp = $apdf_local_time;
		}
		else{
			$apdf_furthest_scheduled_post_timestamp = get_the_time('U', $apdf_scheduled_post_id); //retrieve the local unix time for this post id
		}
	} //-retrieve scheduled post timestamp


	if(in_array(get_post_status(), explode(',', $apdf_settings['apdf_post_statuses']))){ //is the post status of the current post is in the option specified by the user

		## ===================================
		## ### TIME CALCULATION
		## ===================================

		###we don't need a condition for setting the current time ($apdf_settings['apdf_custom_time'] == 1)

		if($apdf_settings['apdf_custom_time'] == 2){ //current time + XY minutes
			$apdf_timestap_increment = $apdf_settings['apdf_custom_time_2_extra_minutes'] * 60;
			$apdf_new_timestamp = $apdf_local_time + $apdf_timestap_increment;

			//generating time
			$apdf_calculated_timestamp['hour'] = date('H', $apdf_new_timestamp);
			$apdf_calculated_timestamp['minute'] = date('i', $apdf_new_timestamp);
		}
		if($apdf_settings['apdf_custom_time'] == 3){ //time of the furthest scheduled post + minutes
			$apdf_timestap_increment = $apdf_settings['apdf_custom_time_3_extra_minutes'] * 60;
			$apdf_new_timestamp = $apdf_furthest_scheduled_post_timestamp + $apdf_timestap_increment;

			//generating time
			$apdf_calculated_timestamp['hour'] = date('H', $apdf_new_timestamp);
			$apdf_calculated_timestamp['minute'] = date('i', $apdf_new_timestamp);
		}
		if($apdf_settings['apdf_custom_time'] == 4){ //specific time
			//retrieving time from the database
			$apdf_calculated_timestamp['hour'] = $apdf_settings['apdf_custom_time_4_hours'];
			$apdf_calculated_timestamp['minute'] = $apdf_settings['apdf_custom_time_4_minutes'];
		}

		## ===================================
		## ### DATE CALCULATION
		## ===================================

		###we don't need a condition for setting the current date ($apdf_settings['apdf_custom_date'] == 1) UNLESS apdf_custom_time == 2 OR 3
		if($apdf_settings['apdf_custom_date'] == 1 AND ($apdf_settings['apdf_custom_time'] == 2 OR $apdf_settings['apdf_custom_time'] == 3)){ //current date + minutes
			$apdf_new_datestamp = $apdf_local_time + $apdf_timestap_increment;

			//generating date
			$apdf_calculated_timestamp['month'] = date('m', $apdf_new_datestamp);
			$apdf_calculated_timestamp['day'] = date('d', $apdf_new_datestamp);
			$apdf_calculated_timestamp['year'] = date('Y', $apdf_new_datestamp);
		}
		if($apdf_settings['apdf_custom_date'] == 2){ //current date + days
			$apdf_new_datestamp = $apdf_local_time + ($apdf_settings['apdf_custom_date_2_extra_days'] * 60 * 60 * 24) + $apdf_timestap_increment;

			//generating date
			$apdf_calculated_timestamp['month'] = date('m', $apdf_new_datestamp);
			$apdf_calculated_timestamp['day'] = date('d', $apdf_new_datestamp);
			$apdf_calculated_timestamp['year'] = date('Y', $apdf_new_datestamp);
		}
		if($apdf_settings['apdf_custom_date'] == 3){ //date of the furthest scheduled post + days
			$apdf_new_datestamp = $apdf_furthest_scheduled_post_timestamp + ($apdf_settings['apdf_custom_date_3_extra_days'] * 60 * 60 * 24) + $apdf_timestap_increment;

			//generating date
			$apdf_calculated_timestamp['month'] = date('m', $apdf_new_datestamp);
			$apdf_calculated_timestamp['day'] = date('d', $apdf_new_datestamp);
			$apdf_calculated_timestamp['year'] = date('Y', $apdf_new_datestamp);
		}

		if(!empty($apdf_calculated_timestamp)){
//echo "apdf_furthest_scheduled_post_timestamp ". $apdf_furthest_scheduled_post_timestamp ." apdf_new_datetamp: ". $apdf_new_datestamp . "apdf_timestap_increment ". $apdf_timestap_increment; //for debugging
			return $apdf_calculated_timestamp;
		} //-if JS needs to be printed
	} //-if post statuses check
}

## ===================================
## ### CSS
## ===================================

function apdf_load_options_page_css(){ //load CSS on the options page
	global $apdf_plugin_url;
	wp_enqueue_style('apdf_style', $apdf_plugin_url .'css/apdf_style.css'); //load CSS
}

## ===================================
## ### JAVASCRIPT
## ===================================

function apdf_load_jquery(){
    wp_enqueue_script('jQuery');
}

function apdf_insert_javascript(){ //this inserts new default values when a users clicks on the "Edit" link
	$apdf_calculated_timestamp = apdf_calculate_timestamp();

	if(!empty($apdf_calculated_timestamp)){ //print JS if the variable is not empty
?>
<!-- Automatic Post Date Filler -->
<script type="text/javascript">
jQuery(document).ready(function(){
	jQuery("a[href=#edit_timestamp].edit-timestamp").click(function(){
		<?php if(isset($apdf_calculated_timestamp['month'])){ ?>jQuery("#mm").val('<?php echo $apdf_calculated_timestamp['month']; ?>');
		<?php } if(isset($apdf_calculated_timestamp['day'])){ ?>jQuery("input[name=jj]").val('<?php echo $apdf_calculated_timestamp['day']; ?>');
		<?php } if(isset($apdf_calculated_timestamp['year'])){ ?>jQuery("input[name=aa]").val('<?php echo $apdf_calculated_timestamp['year']; ?>');
		<?php } if(isset($apdf_calculated_timestamp['hour'])){ ?>jQuery("input[name=hh]").val('<?php echo $apdf_calculated_timestamp['hour']; ?>');
		<?php } if(isset($apdf_calculated_timestamp['minute'])){ ?>jQuery("input[name=mn]").val('<?php echo $apdf_calculated_timestamp['minute']; ?>');
<?php } ?>
	});
});
</script>
<!-- //-Automatic Post Date Filler -->
<?php
	}//-if timestamp has been calclated
}

## =========================================================================
## ### OPTIONS PAGE
## =========================================================================

function apdf_options_page(){ //load options page
	global $apdf_message_html_prefix_updated,
	$apdf_message_html_prefix_error,
	$apdf_message_html_suffix;

	$apdf_settings = get_option('automatic_post_date_filler');
	$apdf_invalid_nonce_message = 'Sorry, your nonce did not verify, your request couldn\'t be executed. Please try again.';
?>

<div class="wrap">
<div id="icon-options-general" class="icon32"><br></div>
<h2>Automatic Post Date Filler</h2>

<?php

## ===================================
## ### OPTIONS SAVING
## ===================================

if(isset($_POST['apdf_save_settings_button'])){//saving all settings
	if(wp_verify_nonce($_POST['apdf_save_settings_hash'],'apdf_save_settings_nonce')){ //save only if the nonce was verified

		### settings saved to a single array which will be updated at the end of this condition

		## radio inputs
		$apdf_settings['apdf_custom_time'] = $_POST['apdf_custom_time'];
		$apdf_settings['apdf_custom_date'] = $_POST['apdf_custom_date'];
		//TODO v1.1 $apdf_settings['apdf_timestamp_calculation'] = $_POST['apdf_timestamp_calculation'];

		## text inputs

		if(ctype_digit($_POST['apdf_custom_time_2_extra_minutes']) AND $_POST['apdf_custom_time_2_extra_minutes'] > 0){ //is this value natural?
			$apdf_settings['apdf_custom_time_2_extra_minutes'] = $_POST['apdf_custom_time_2_extra_minutes'];
		}
		else{
			echo $apdf_message_html_prefix_error .'<b>Error:</b> The option "apdf_custom_time_2_extra_minutes" couldn\'t be saved because the sent value wasn\'t natural.'. $apdf_message_html_suffix; //user-moron scenario
		}

		if(ctype_digit($_POST['apdf_custom_time_3_extra_minutes']) AND $_POST['apdf_custom_time_3_extra_minutes'] > 0){ //is this value natural?
			$apdf_settings['apdf_custom_time_3_extra_minutes'] = $_POST['apdf_custom_time_3_extra_minutes'];
		}
		else{
			echo $apdf_message_html_prefix_error .'<b>Error:</b> The option "apdf_custom_time_3_extra_minutes" couldn\'t be saved because the sent value wasn\'t natural.'. $apdf_message_html_suffix; //user-moron scenario
		}

		if(ctype_digit($_POST['apdf_custom_time_4_hours'])){ //is this value natural? - this can be 00
			$apdf_settings['apdf_custom_time_4_hours'] = $_POST['apdf_custom_time_4_hours'];
		}
		else{
			echo $apdf_message_html_prefix_error .'<b>Error:</b> The option "apdf_custom_time_4_hours" couldn\'t be saved because the sent value wasn\'t natural.'. $apdf_message_html_suffix; //user-moron scenario
		}

		if(ctype_digit($_POST['apdf_custom_time_4_minutes'])){ //is this value natural? - this can be 00
			$apdf_settings['apdf_custom_time_4_minutes'] = $_POST['apdf_custom_time_4_minutes'];
		}
		else{
			echo $apdf_message_html_prefix_error .'<b>Error:</b> The option "apdf_custom_time_4_minutes" couldn\'t be saved because the sent value wasn\'t natural.'. $apdf_message_html_suffix; //user-moron scenario
		}

		if(ctype_digit($_POST['apdf_custom_date_2_extra_days']) AND $_POST['apdf_custom_date_2_extra_days'] > 0){ //is this value natural?
			$apdf_settings['apdf_custom_date_2_extra_days'] = $_POST['apdf_custom_date_2_extra_days'];
		}
		else{
			echo $apdf_message_html_prefix_error .'<b>Error:</b> The option "apdf_custom_date_2_extra_days" couldn\'t be saved because the sent value wasn\'t natural.'. $apdf_message_html_suffix; //user-moron scenario
		}

		if(ctype_digit($_POST['apdf_custom_date_3_extra_days']) AND $_POST['apdf_custom_date_3_extra_days'] > 0){ //is this value natural?
			$apdf_settings['apdf_custom_date_3_extra_days'] = $_POST['apdf_custom_date_3_extra_days'];
		}
		else{
			echo $apdf_message_html_prefix_error .'<b>Error:</b> The option "apdf_custom_date_3_extra_days" couldn\'t be saved because the sent value wasn\'t natural.'. $apdf_message_html_suffix; //user-moron scenario
		}

		if(!empty($_POST['apdf_post_statuses'])){ //is this value not empty?
			$apdf_settings['apdf_post_statuses'] = trim($_POST['apdf_post_statuses'], ','); //trim unnecessary separators
		}
		else{
			echo $apdf_message_html_prefix_error .'<b>Error:</b> The option "apdf_post_statuses" couldn\'t be saved because the sent value was empty.'. $apdf_message_html_suffix; //user-moron scenario
		}


		update_option('automatic_post_date_filler', $apdf_settings); //save settings

		echo $apdf_message_html_prefix_updated .'Your settings have been saved.'. $apdf_message_html_suffix; //confirm message
	}//-nonce check
	else{//the nonce is invalid
		die($apdf_invalid_nonce_message);
	}
}

## =========================================================================
## ### USER INTERFACE
## =========================================================================
?>

<div id="poststuff" class="metabox-holder has-right-sidebar">
	<div class="inner-sidebar">
		<div id="side-sortables" class="meta-box-sortabless" style="position:relative;">

			<!-- postbox -->
			<div class="postbox">
				<h3 class="hndle"><span>Useful links</span></h3>
				<div class="inside">
						<ul>
							<li><a href="http://wordpress.org/plugins/automatic-post-date-filler/"><span class="apdf_icon apdf_wp"></span>Plugin homepage</a></li>
							<li><a href="http://wordpress.org/plugins/automatic-post-date-filler/faq"><span class="apdf_icon apdf_wp"></span>Frequently asked questions</a> </li>
							<li><a href="http://wordpress.org/support/plugin/automatic-post-date-filler" title="Bug reports and feature requests"><span class="apdf_icon apdf_wp"></span>Support forum</a></li>
						</ul>
						<ul>
							<li><a href="http://devtard.com"><span class="apdf_icon apdf_devtard"></span>Devtard's blog</a></li>
							<li><a href="http://twitter.com/devtard_com"><span class="apdf_icon apdf_twitter"></span>Devtard's Twitter</a></li>
						</ul>
				</div>
			</div>
			<!-- //-postbox -->

			<!-- postbox -->
			<div class="postbox">
				<h3 class="hndle"><span>Show some love!</span></h3>
				<div class="inside">
					<p>If you find this plugin useful, please give it a good rating and share it with others.</p>
						<ul>
							<li><a href="http://wordpress.org/support/view/plugin-reviews/automatic-post-date-filler"><span class="apdf_icon apdf_rate"></span>Rate plugin at WordPress.org</a></li>
							<li><a href="http://twitter.com/home?status=Automatic Post Date Filler - useful WP plugin that automatically sets custom date and time when scheduling a post. http://wordpress.org/plugins/automatic-post-date-filler/"><span class="apdf_icon apdf_twitter"></span>Post a link to Twitter</a></li>
						</ul>
					<p>Thank you.</p>
				</div>
			</div><!-- //-postbox -->
			
			<!-- postbox -->
			<div class="postbox">
				<h3 class="hndle"><span>My other plugins</span></h3>
				<div class="inside">
					<ul>
						<li><a href="http://wordpress.org/plugins/automatic-post-tagger/"><span class="apdf_icon apdf_wp"></span>Automatic Post Tagger</a> - automatically adds user-defined tags to posts
							<small>[<?php if(is_plugin_active('automatic-post-tagger/automatic-post-tagger.php')){echo '<span class="apdf_already_installed">Installed</span>';} else{echo '<a href="'. admin_url('plugin-install.php?tab=search&type=term&s=&quot;Automatic+Post+Tagger&quot;') .'">Install now</a>';} ?>]</small>
						</li>
					</ul>
				</div>
			</div><!-- //-postbox -->
		</div><!-- //-side-sortables -->
	</div><!-- //-inner-sidebar -->

	<div class="has-sidebar sm-padded">
		<div id="post-body-content" class="has-sidebar-content">
			<div class="meta-box-sortabless">

				<!-- postbox -->
				<form action="<?php echo admin_url('options-general.php?page=automatic-post-date-filler'); ?>" method="post">
					<table class="form-table">

						<tr valign="top">
							<th scope="row">
								Custom date
								<span class="apdf_help" title="Set the date that you want to insert instead of the default value. It may be affected by added extra minutes.">i</span>
							</th>
							<td>
								<input type="radio" name="apdf_custom_date" id="apdf_custom_date_1" value="1" <?php if($apdf_settings['apdf_custom_date'] == 1) echo 'checked="checked"'; ?>> <label for="apdf_custom_date_1">Current date</label><br />
								<input type="radio" name="apdf_custom_date" id="apdf_custom_date_2" value="2" <?php if($apdf_settings['apdf_custom_date'] == 2) echo 'checked="checked"'; ?>> <label for="apdf_custom_date_2">Current date</label> + <input type="text" name="apdf_custom_date_2_extra_days" id="apdf_custom_date_2_extra_days" value="<?php echo $apdf_settings['apdf_custom_date_2_extra_days']; ?>" maxlength="255" size="3"> days<br />
								<input type="radio" name="apdf_custom_date" id="apdf_custom_date_3" value="3" <?php if($apdf_settings['apdf_custom_date'] == 3) echo 'checked="checked"'; ?>> <label for="apdf_custom_date_3">Date of the furthest scheduled post</label> + <input type="text" name="apdf_custom_date_3_extra_days" id="apdf_custom_date_3_extra_days" value="<?php echo $apdf_settings['apdf_custom_date_3_extra_days']; ?>" maxlength="255" size="3"> days <span class="apdf_help" title="If there isn't any scheduled post, the current date + specified number of days will be used instead.">i</span>

							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								Custom time
								<span class="apdf_help" title="Set the time that you want to insert instead of the default value.">i</span>
							</th>
							<td>
								<input type="radio" name="apdf_custom_time" id="apdf_custom_time_1" value="1" <?php if($apdf_settings['apdf_custom_time'] == 1) echo 'checked="checked"'; ?>> <label for="apdf_custom_time_1">Current time</label><br />
								<input type="radio" name="apdf_custom_time" id="apdf_custom_time_2" value="2" <?php if($apdf_settings['apdf_custom_time'] == 2) echo 'checked="checked"'; ?>> <label for="apdf_custom_time_2">Current time</label> + <input type="text" name="apdf_custom_time_2_extra_minutes" id="apdf_custom_time_2_extra_minutes" value="<?php echo $apdf_settings['apdf_custom_time_2_extra_minutes']; ?>" maxlength="255" size="3"> minutes<br />
								<input type="radio" name="apdf_custom_time" id="apdf_custom_time_3" value="3" <?php if($apdf_settings['apdf_custom_time'] == 3) echo 'checked="checked"'; ?>> <label for="apdf_custom_time_3">Time of the furthest scheduled post</label> + <input type="text" name="apdf_custom_time_3_extra_minutes" id="apdf_custom_time_3_extra_minutes" value="<?php echo $apdf_settings['apdf_custom_time_3_extra_minutes']; ?>" maxlength="255" size="3"> minutes <span class="apdf_help" title="If there isn't any scheduled post, the current time + specified number of minutes will be used instead.">i</span><br />
								<input type="radio" name="apdf_custom_time" id="apdf_custom_time_4" value="4" <?php if($apdf_settings['apdf_custom_time'] == 4) echo 'checked="checked"'; ?>> <label for="apdf_custom_time_4">Specific time:</label> <input type="text" name="apdf_custom_time_4_hours" id="apdf_custom_time_4_hours" value="<?php echo $apdf_settings['apdf_custom_time_4_hours']; ?>" maxlength="2" size="2"> : <input type="text" name="apdf_custom_time_4_minutes" id="apdf_custom_time_4_minutes" value="<?php echo $apdf_settings['apdf_custom_time_4_minutes']; ?>" maxlength="2" size="2"> <span class="apdf_help" title="This option will always insert specific time, which won't be affected by any other calculations.">i</span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row">
								<label for="apdf_post_statuses">Affected post types</label>
								<span class="apdf_help" title="The following settings will affect only posts with specified post statuses (separated by commas). You can use these values: &quot;auto-draft&quot;, &quot;draft&quot;, &quot;future&quot;, &quot;inherit&quot;, &quot;pending&quot;, &quot;private&quot;, &quot;publish&quot;, &quot;trash&quot;.">i</span>
							</th>
							<td>
								<input type="text" name="apdf_post_statuses" id="apdf_post_statuses" value="<?php echo $apdf_settings['apdf_post_statuses']; ?>" maxlength="255" size="30">
							</td>
						</tr>

<!-- TODO v1.1
						<tr valign="top">
							<th scope="row">
								Date & time calculation
								<span class="apdf_help" title="When should the custom date and time be generated?">i</span>
							</th>
							<td>
								<input type="radio" name="apdf_timestamp_calculation" id="apdf_timestamp_calculation_1" value="1" <hp if($apdf_settings['apdf_timestamp_calculation'] == 1) echo 'checked="checked"'; ?>> <label for="apdf_timestamp_calculation_1">When the page loads</label>
								<span class="apdf_help" title="Every time a page is loaded, the default values are calculated.">i</span><br />

								<input type="radio" name="apdf_timestamp_calculation" id="apdf_timestamp_calculation_2" value="2" <hp if($apdf_settings['apdf_timestamp_calculation'] == 2) echo 'checked="checked"'; ?>> <label for="apdf_timestamp_calculation_2">When "Edit" link is clicked</label>
								<span class="apdf_help" title="Every time the &quote;Edit&quote; link is clicked, the default values are generated. (This option requires AJAX and may be slightly slower but always accurate (this is useful when posts are constantly being scheduled).">i</span>
							</td>
						</tr>
-->
					</table>

					<p class="submit">
						<input class="button button-primary" type="submit" name="apdf_save_settings_button" value="Save settings"> 
					</p>
	
				<?php wp_nonce_field('apdf_save_settings_nonce','apdf_save_settings_hash'); ?>
				</form>
				<!-- //-postbox -->

			</div>
		</div>
	</div>

</div>
</div>

<?php
} //-function options page
?>
